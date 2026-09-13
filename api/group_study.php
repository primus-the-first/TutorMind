<?php
/**
 * Group Study API
 *
 * One student ("the teacher") explains a concept to their peers; the AI
 * facilitates — diagnosing gaps in the explanation and inviting the group to
 * challenge it — but never teaches the concept itself. See
 * api/services/facilitator_service.php for the prompt that enforces that.
 *
 * Actions:
 *   create        — host starts a new session, gets back a join code
 *   join          — a student joins an existing session via its join code
 *   teach         — any participant sends a message (explanation, challenge,
 *                   addition); the AI facilitator responds
 *   advance_turn  — hand the "teaching" role to a different participant
 *   poll          — cheap incremental sync: new messages + current session
 *                   state since a given message id (no WebSockets — see
 *                   migration 015's header for why: this hosting can't
 *                   sustain persistent connections, and nothing else in the
 *                   app polls today either, so this is new ground)
 *
 * Kept fully separate from the 1:1 chat pipeline in server_mysql.php on
 * purpose — reuses callGeminiAPI() from ai_service.php (same AI call, same
 * fallback chain), but with its own tables and its own prompt.
 */

require_once __DIR__ . '/../includes/check_auth.php';
require_once __DIR__ . '/../includes/db_mysql.php';
require_once __DIR__ . '/services/ai_service.php';
require_once __DIR__ . '/services/facilitator_service.php';

header('Content-Type: application/json');

// --------------------------------------------------------------------------
// Config + DB
// --------------------------------------------------------------------------
$configFiles = [__DIR__ . '/../includes/config-sql.ini', __DIR__ . '/../includes/config.ini'];
$config = null;
foreach ($configFiles as $f) {
    if (file_exists($f)) {
        $parsed = parse_ini_file($f);
        if ($parsed !== false) { $config = $parsed; break; }
    }
}

$GEMINI_KEY = $config['GEMINI_API_KEY'] ?? null;
$GROQ_KEY   = $config['GROQ_API_KEY'] ?? null;
$DEEPSEEK_KEY = $config['DEEPSEEK_API_KEY'] ?? null;

try {
    $pdo     = getDbConnection();
    $user_id = (int) $_SESSION['user_id'];
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}

$displayName = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
if ($displayName === '') {
    $displayName = $_SESSION['username'] ?? 'Student';
}

// --------------------------------------------------------------------------
// Router
// --------------------------------------------------------------------------
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create':       handleCreate($pdo, $user_id, $displayName, $body); break;
        case 'join':         handleJoin($pdo, $user_id, $displayName, $body); break;
        case 'teach':        handleTeach($pdo, $user_id, $body, $GEMINI_KEY, $GROQ_KEY, $DEEPSEEK_KEY); break;
        case 'advance_turn': handleAdvanceTurn($pdo, $user_id, $body); break;
        case 'poll':         handlePoll($pdo, $user_id, $body); break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} catch (Throwable $e) {
    error_log("group_study.php action '{$action}' failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Group study feature unavailable']);
}

// --------------------------------------------------------------------------
// Helpers
// --------------------------------------------------------------------------

/** 6-char join code — excludes 0/O/1/I/L so it's unambiguous read aloud or handwritten. */
function generateJoinCode(PDO $pdo): string {
    $charset = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $charset[random_int(0, strlen($charset) - 1)];
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM group_sessions WHERE join_code = ?");
        $stmt->execute([$code]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $code;
        }
    }
    throw new Exception('Could not generate a unique join code — try again.');
}

/** Participants of a session, keyed by user_id, each with display_name + has_taught. */
function getParticipants(PDO $pdo, int $sessionId): array {
    $stmt = $pdo->prepare("SELECT user_id, display_name, has_taught FROM group_session_participants WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[(int) $row['user_id']] = $row;
    }
    return $out;
}

/** Fire-and-forget push notification — a failed push must never break the actual state change. */
function pushTurnNotification(PDO $pdo, int $userId, string $sessionTopic): void {
    try {
        require_once __DIR__ . '/../includes/webpush_config.php';
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
        }
        if (!class_exists('Minishlink\\WebPush\\WebPush')) return;

        $stmt = $pdo->prepare("SELECT endpoint, endpoint_hash, p256dh, auth FROM push_subscriptions WHERE user_id = ?");
        $stmt->execute([$userId]);
        $subs = $stmt->fetchAll();
        if (!$subs) return;

        $webPushConfig = getWebPushConfig();
        $webPush = new \Minishlink\WebPush\WebPush([
            'VAPID' => [
                'subject'    => $webPushConfig['vapid_subject'],
                'publicKey'  => $webPushConfig['vapid_public_key'],
                'privateKey' => $webPushConfig['vapid_private_key'],
            ],
        ]);

        $payload = json_encode([
            'title' => "It's your turn!",
            'body'  => "Your study group is ready for you to teach \"{$sessionTopic}\".",
        ]);

        foreach ($subs as $sub) {
            $subscription = \Minishlink\WebPush\Subscription::create([
                'endpoint'  => $sub['endpoint'],
                'publicKey' => $sub['p256dh'],
                'authToken' => $sub['auth'],
            ]);
            $webPush->queueNotification($subscription, $payload);
        }

        $delStmt = $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint_hash = ?");
        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess() && $report->isSubscriptionExpired()) {
                $delStmt->execute([hash('sha256', $report->getEndpoint())]);
            }
        }
    } catch (Throwable $e) {
        error_log("group_study: turn-notification push failed: " . $e->getMessage());
    }
}

// --------------------------------------------------------------------------
// Handlers
// --------------------------------------------------------------------------

function handleCreate($pdo, $user_id, $displayName, $data) {
    $topic = trim($data['topic'] ?? '');
    if ($topic === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'A topic is required to start a session.']);
        return;
    }

    $joinCode = generateJoinCode($pdo);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO group_sessions (host_user_id, topic, join_code, status) VALUES (?, ?, ?, 'waiting')");
        $stmt->execute([$user_id, $topic, $joinCode]);
        $sessionId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO group_session_participants (session_id, user_id, display_name) VALUES (?, ?, ?)");
        $stmt->execute([$sessionId, $user_id, $displayName]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    echo json_encode([
        'success'    => true,
        'session_id' => $sessionId,
        'join_code'  => $joinCode,
        'topic'      => $topic,
        'status'     => 'waiting',
    ]);
}

function handleJoin($pdo, $user_id, $displayName, $data) {
    $joinCode = strtoupper(trim($data['join_code'] ?? ''));
    if ($joinCode === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Enter a join code.']);
        return;
    }

    $stmt = $pdo->prepare("SELECT id, topic, status, host_user_id, current_teacher_user_id FROM group_sessions WHERE join_code = ?");
    $stmt->execute([$joinCode]);
    $session = $stmt->fetch();

    if (!$session) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'No session found with that code.']);
        return;
    }
    if ($session['status'] === 'completed') {
        http_response_code(410);
        echo json_encode(['success' => false, 'error' => 'That session has already ended.']);
        return;
    }

    $sessionId = (int) $session['id'];

    $stmt = $pdo->prepare("
        INSERT INTO group_session_participants (session_id, user_id, display_name)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE display_name = VALUES(display_name)
    ");
    $stmt->execute([$sessionId, $user_id, $displayName]);

    $participants = getParticipants($pdo, $sessionId);

    // Room needs at least 2 people before teaching makes sense — flip
    // waiting -> active and open the first turn (host teaches first) the
    // moment a second participant shows up.
    if ($session['status'] === 'waiting' && count($participants) >= 2) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE group_sessions SET status = 'active', current_teacher_user_id = ? WHERE id = ?");
            $stmt->execute([$session['host_user_id'], $sessionId]);

            $stmt = $pdo->prepare("INSERT INTO group_session_turns (session_id, teacher_user_id) VALUES (?, ?)");
            $stmt->execute([$sessionId, $session['host_user_id']]);

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        $session['status'] = 'active';
        $session['current_teacher_user_id'] = $session['host_user_id'];
    }

    echo json_encode([
        'success'      => true,
        'session_id'   => $sessionId,
        'topic'        => $session['topic'],
        'status'       => $session['status'],
        'current_teacher_user_id' => $session['current_teacher_user_id'] !== null ? (int) $session['current_teacher_user_id'] : null,
        'participants' => array_values($participants),
    ]);
}

function handleTeach($pdo, $user_id, $data, $geminiKey, $groqKey, $deepseekKey) {
    $sessionId   = (int) ($data['session_id'] ?? 0);
    $explanation = trim($data['message'] ?? '');

    if ($sessionId <= 0 || $explanation === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'A message is required.']);
        return;
    }

    $stmt = $pdo->prepare("SELECT topic, status, current_teacher_user_id FROM group_sessions WHERE id = ?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();

    if (!$session) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Session not found.']);
        return;
    }
    if ($session['status'] !== 'active') {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'This session is not active yet — waiting for more participants.']);
        return;
    }

    $participants = getParticipants($pdo, $sessionId);
    if (!isset($participants[$user_id])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You are not part of this session.']);
        return;
    }

    // Find (or, defensively, create) the currently-open turn for this session.
    $stmt = $pdo->prepare("SELECT id FROM group_session_turns WHERE session_id = ? AND status != 'completed' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$sessionId]);
    $turnId = $stmt->fetchColumn();
    if (!$turnId) {
        $stmt = $pdo->prepare("INSERT INTO group_session_turns (session_id, teacher_user_id) VALUES (?, ?)");
        $stmt->execute([$sessionId, $session['current_teacher_user_id']]);
        $turnId = (int) $pdo->lastInsertId();
    }

    $stmt = $pdo->prepare("INSERT INTO group_session_messages (session_id, turn_id, sender_type, student_user_id, content) VALUES (?, ?, 'student', ?, ?)");
    $stmt->execute([$sessionId, $turnId, $user_id, $explanation]);

    // Build the Gemini-format history for this session so far, prefixing
    // each student line with who said it — Gemini's roles are only
    // user/model, so speaker identity has to travel in the text itself.
    $stmt = $pdo->prepare("SELECT sender_type, student_user_id, content FROM group_session_messages WHERE session_id = ? ORDER BY id ASC");
    $stmt->execute([$sessionId]);
    $contents = [];
    foreach ($stmt->fetchAll() as $row) {
        if ($row['sender_type'] === 'ai') {
            $contents[] = ['role' => 'model', 'parts' => [['text' => $row['content']]]];
        } else {
            $speaker = $participants[(int) $row['student_user_id']]['display_name'] ?? 'A student';
            $contents[] = ['role' => 'user', 'parts' => [['text' => "[{$speaker}]: {$row['content']}"]]];
        }
    }

    $teacherId   = (int) $session['current_teacher_user_id'];
    $teacherName = $participants[$teacherId]['display_name'] ?? 'The teacher';
    $untaughtNames = array_values(array_map(
        fn($p) => $p['display_name'],
        array_filter($participants, fn($p, $uid) => (int) $p['has_taught'] === 0 && $uid !== $teacherId, ARRAY_FILTER_USE_BOTH)
    ));

    $systemPrompt = buildFacilitatorPrompt($session['topic'], $teacherName, $untaughtNames);

    $payload = json_encode([
        'contents' => $contents,
        'system_instruction' => ['role' => 'system', 'parts' => [['text' => $systemPrompt]]],
        'generationConfig' => ['maxOutputTokens' => 1024, 'temperature' => 0.7],
    ]);

    try {
        $responseData = callGeminiAPI($payload, $geminiKey);
    } catch (Exception $geminiError) {
        try {
            $responseData = callGroqAPI($contents, $systemPrompt, $groqKey);
        } catch (Exception $groqError) {
            $responseData = callDeepSeekAPI($contents, $systemPrompt, $deepseekKey);
        }
    }

    $aiText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if (!$aiText) {
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'The facilitator had no response — try again.']);
        return;
    }

    $stmt = $pdo->prepare("INSERT INTO group_session_messages (session_id, turn_id, sender_type, content) VALUES (?, ?, 'ai', ?)");
    $stmt->execute([$sessionId, $turnId, $aiText]);
    $aiMessageId = (int) $pdo->lastInsertId();

    echo json_encode([
        'success'    => true,
        'message_id' => $aiMessageId,
        'ai_message' => $aiText,
    ]);
}

function handleAdvanceTurn($pdo, $user_id, $data) {
    $sessionId    = (int) ($data['session_id'] ?? 0);
    $nextTeacher  = trim($data['next_teacher_name'] ?? '');

    if ($sessionId <= 0 || $nextTeacher === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'A session and next teacher are required.']);
        return;
    }

    $stmt = $pdo->prepare("SELECT topic, status, current_teacher_user_id FROM group_sessions WHERE id = ?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();
    if (!$session || $session['status'] !== 'active') {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Session not found or not active.']);
        return;
    }

    $participants = getParticipants($pdo, $sessionId);
    if (!isset($participants[$user_id])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You are not part of this session.']);
        return;
    }

    $nextTeacherId = null;
    foreach ($participants as $uid => $p) {
        if ($p['display_name'] === $nextTeacher) { $nextTeacherId = $uid; break; }
    }
    if ($nextTeacherId === null) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Could not find that participant.']);
        return;
    }

    $currentTeacherId = (int) $session['current_teacher_user_id'];

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE group_session_turns SET status = 'completed', ended_at = NOW() WHERE session_id = ? AND status != 'completed'")
            ->execute([$sessionId]);
        $pdo->prepare("UPDATE group_session_participants SET has_taught = 1 WHERE session_id = ? AND user_id = ?")
            ->execute([$sessionId, $currentTeacherId]);
        $pdo->prepare("UPDATE group_sessions SET current_teacher_user_id = ? WHERE id = ?")
            ->execute([$nextTeacherId, $sessionId]);
        $pdo->prepare("INSERT INTO group_session_turns (session_id, teacher_user_id) VALUES (?, ?)")
            ->execute([$sessionId, $nextTeacherId]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    pushTurnNotification($pdo, $nextTeacherId, $session['topic']);

    echo json_encode([
        'success' => true,
        'current_teacher_user_id' => $nextTeacherId,
        'current_teacher_name'    => $nextTeacher,
    ]);
}

function handlePoll($pdo, $user_id, $data) {
    $sessionId = (int) ($data['session_id'] ?? $_GET['session_id'] ?? 0);
    $sinceId   = (int) ($data['since_id'] ?? $_GET['since_id'] ?? 0);

    if ($sessionId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'session_id is required.']);
        return;
    }

    $stmt = $pdo->prepare("SELECT topic, status, current_teacher_user_id FROM group_sessions WHERE id = ?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();
    if (!$session) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Session not found.']);
        return;
    }

    $participants = getParticipants($pdo, $sessionId);
    if (!isset($participants[$user_id])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You are not part of this session.']);
        return;
    }

    $stmt = $pdo->prepare("SELECT id, sender_type, student_user_id, content, created_at FROM group_session_messages WHERE session_id = ? AND id > ? ORDER BY id ASC");
    $stmt->execute([$sessionId, $sinceId]);
    $messages = [];
    foreach ($stmt->fetchAll() as $row) {
        $messages[] = [
            'id'      => (int) $row['id'],
            'sender'  => $row['sender_type'] === 'ai' ? 'AI Facilitator' : ($participants[(int) $row['student_user_id']]['display_name'] ?? 'A student'),
            'is_ai'   => $row['sender_type'] === 'ai',
            'content' => $row['content'],
        ];
    }

    $currentTeacherId = $session['current_teacher_user_id'] !== null ? (int) $session['current_teacher_user_id'] : null;

    echo json_encode([
        'success' => true,
        'status'  => $session['status'],
        'topic'   => $session['topic'],
        'current_teacher_user_id' => $currentTeacherId,
        'current_teacher_name'    => $currentTeacherId !== null ? ($participants[$currentTeacherId]['display_name'] ?? null) : null,
        'participants' => array_values($participants),
        'messages' => $messages,
    ]);
}
