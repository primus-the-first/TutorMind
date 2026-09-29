<?php
/**
 * Group Study API — a study chat room with an AI facilitator ("Q").
 *
 * One participant teaches the topic at a time; everyone else can chat,
 * challenge and help. Q doesn't answer every line: it replies to the current
 * teacher, to the person it last called on, and to anyone who mentions @Q.
 * Every other reply or so it calls on whoever has been quietest, by name, to
 * hear their understanding; if they get it wrong, Q hands it to the group
 * (or gives one hint) rather than correcting them itself — the prompt that
 * enforces "never teach, point at gaps" is api/services/facilitator_service.php.
 *
 * Actions (POST JSON { action, ... }):
 *   create     { topic }                      host opens a room, gets a join code
 *   join       { join_code }                  enter (or re-enter) a room by code
 *   open       { session_id }                 resume a room you're already in
 *   mine       {}                             your open rooms, for "Rejoin"
 *   send       { session_id, message }        post to the room (returns at once)
 *   reply      { session_id, message_id }     Q answers that message, if it should
 *   typing     { session_id }                 "is typing" ping (throttled client-side)
 *   poll       { session_id, since_id }       new messages + room state; also presence
 *   pass_turn  { session_id, next_user_id }   current teacher (or host) hands off
 *   end        { session_id }                 host ends the session for everyone
 *   leave      { session_id }                 leave the room (rejoinable)
 *
 * No WebSockets (see migration 015): clients poll. Presence, typing and
 * "Q is thinking" are all timestamps the poll reads (migrations 016/017).
 * Reuses the AI fallback chain from ai_service.php with its own prompt.
 */

require_once __DIR__ . '/../includes/check_auth.php';
require_once __DIR__ . '/../includes/db_mysql.php';
require_once __DIR__ . '/services/ai_service.php';
require_once __DIR__ . '/services/facilitator_service.php';

header('Content-Type: application/json');

const GS_ONLINE_SECONDS = 20;   // seen by a poll within this window = "here"
const GS_TYPING_SECONDS = 6;    // 2 × the 3s poll: survives one missed tick
const GS_AI_LEASE_SECONDS = 90; // longest one facilitator reply may hold the room
const GS_STALE_HOURS = 6;       // untouched this long = ended
const GS_HISTORY_MESSAGES = 40; // most recent lines sent to the AI
const GS_MAX_MESSAGE = 2000;

// --------------------------------------------------------------------------
// Config + DB
// --------------------------------------------------------------------------
$config = null;
foreach ([__DIR__ . '/../includes/config-sql.ini', __DIR__ . '/../includes/config.ini'] as $f) {
    if (file_exists($f) && ($parsed = parse_ini_file($f)) !== false) { $config = $parsed; break; }
}
$AI_KEYS = [
    'gemini'   => $config['GEMINI_API_KEY'] ?? null,
    'groq'     => $config['GROQ_API_KEY'] ?? null,
    'deepseek' => $config['DEEPSEEK_API_KEY'] ?? null,
];

try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$displayName = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
if ($displayName === '') $displayName = $_SESSION['username'] ?? 'Student';
// Nothing below writes the session: release its lock so a slow AI reply
// doesn't stall this user's polls and typing pings.
session_write_close();

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create':    gsCreate($pdo, $user_id, $displayName, $body); break;
        case 'join':      gsJoin($pdo, $user_id, $displayName, $body); break;
        case 'open':      gsOpen($pdo, $user_id, $body); break;
        case 'mine':      gsMine($pdo, $user_id); break;
        case 'send':      gsSend($pdo, $user_id, $body); break;
        case 'reply':     gsReply($pdo, $user_id, $body, $AI_KEYS); break;
        case 'typing':    gsTyping($pdo, $user_id, $body); break;
        case 'poll':      gsPoll($pdo, $user_id, $body); break;
        case 'pass_turn': gsPassTurn($pdo, $user_id, $body); break;
        case 'end':       gsEnd($pdo, $user_id, $body); break;
        case 'leave':     gsLeave($pdo, $user_id, $body); break;
        default:          gsFail(400, 'Unknown action');
    }
} catch (Throwable $e) {
    error_log("group_study.php action '{$action}' failed: " . $e->getMessage());
    gsFail(500, 'Group study is unavailable right now. Try again in a moment.');
}

// --------------------------------------------------------------------------
// Helpers
// --------------------------------------------------------------------------

function gsFail(int $code, string $error): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $error]);
}

/** 6-char join code — no 0/O/1/I/L, so it's unambiguous read aloud or handwritten. */
function gsJoinCode(PDO $pdo): string {
    $charset = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $code = '';
        for ($i = 0; $i < 6; $i++) $code .= $charset[random_int(0, strlen($charset) - 1)];
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM group_sessions WHERE join_code = ?");
        $stmt->execute([$code]);
        if ((int) $stmt->fetchColumn() === 0) return $code;
    }
    throw new Exception('Could not generate a unique join code.');
}

/** Session row, ending it first if it's gone stale. */
function gsSession(PDO $pdo, int $sessionId): ?array {
    $pdo->prepare("
        UPDATE group_sessions SET status = 'completed', ended_at = NOW()
        WHERE id = ? AND status != 'completed' AND updated_at < NOW() - INTERVAL " . GS_STALE_HOURS . " HOUR
    ")->execute([$sessionId]);
    $stmt = $pdo->prepare("SELECT id, host_user_id, topic, join_code, status, current_teacher_user_id,
        (ai_busy_until IS NOT NULL AND ai_busy_until > NOW()) AS ai_thinking FROM group_sessions WHERE id = ?");
    $stmt->execute([$sessionId]);
    $s = $stmt->fetch();
    return $s ?: null;
}

/** Participants keyed by user_id, with presence and how much each has said. */
function gsParticipants(PDO $pdo, int $sessionId): array {
    $stmt = $pdo->prepare("
        SELECT p.user_id, p.display_name, p.has_taught, p.left_at IS NOT NULL AS has_left,
               (p.left_at IS NULL AND p.last_seen_at > NOW() - INTERVAL " . GS_ONLINE_SECONDS . " SECOND) AS online,
               (SELECT COUNT(*) FROM group_session_messages m
                 WHERE m.session_id = p.session_id AND m.sender_type = 'student' AND m.student_user_id = p.user_id) AS said,
               (SELECT MAX(m.id) FROM group_session_messages m
                 WHERE m.session_id = p.session_id AND m.sender_type = 'student' AND m.student_user_id = p.user_id) AS last_said_id
        FROM group_session_participants p WHERE p.session_id = ? ORDER BY p.id
    ");
    $stmt->execute([$sessionId]);
    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $out[(int) $r['user_id']] = [
            'user_id'      => (int) $r['user_id'],
            'display_name' => $r['display_name'],
            'has_taught'   => (bool) $r['has_taught'],
            'left'         => (bool) $r['has_left'],
            'online'       => (bool) $r['online'],
            'said'         => (int) $r['said'],
            'last_said_id' => (int) $r['last_said_id'],
        ];
    }
    return $out;
}

function gsSystem(PDO $pdo, int $sessionId, string $text): void {
    $pdo->prepare("INSERT INTO group_session_messages (session_id, sender_type, content) VALUES (?, 'system', ?)")
        ->execute([$sessionId, $text]);
    gsTouch($pdo, $sessionId);
}

function gsTouch(PDO $pdo, int $sessionId): void {
    $pdo->prepare("UPDATE group_sessions SET updated_at = NOW() WHERE id = ?")->execute([$sessionId]);
}

function gsFirstName(string $name): string {
    return preg_split('/\s+/', trim($name))[0] ?: $name;
}

// Models (the Groq/DeepSeek fallbacks especially) sometimes drop the fences
// around the hand-off block, or relabel it ```json. Store the one canonical
// form the client parses and later prompts see — otherwise the room shows raw
// JSON and Q starts imitating its own broken output from the history.
function gsNormalizeHandoff(string $text): string {
    $re = '/(?:```[ \t]*(?:tm-chips|json)?[ \t]*\r?\n|(?:^|\n)[ \t]*tm-chips[ \t]*\r?\n)?[ \t]*(\{[^{}]*"options"[^{}]*\})[ \t]*\r?\n?(?:```)?\s*$/';
    if (!preg_match($re, $text, $m, PREG_OFFSET_CAPTURE)) return $text;

    $before = rtrim(substr($text, 0, $m[0][1]));
    $spec = json_decode($m[1][0], true);
    if (!is_array($spec) || !isset($spec['options']) || !is_array($spec['options'])) return $before;

    $block = "```tm-chips\n" . json_encode([
        'q'       => $spec['q'] ?? "Who's teaching next?",
        'options' => array_values($spec['options']),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n```";
    return $before === '' ? $block : $before . "\n\n" . $block;
}

/** The facilitator's latest call-on, if the person hasn't answered yet. */
function gsOpenAsk(PDO $pdo, int $sessionId): ?array {
    $stmt = $pdo->prepare("SELECT id, addressed_user_id FROM group_session_messages
        WHERE session_id = ? AND sender_type = 'ai' AND addressed_user_id IS NOT NULL ORDER BY id DESC LIMIT 1");
    $stmt->execute([$sessionId]);
    $ask = $stmt->fetch();
    if (!$ask) return null;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM group_session_messages
        WHERE session_id = ? AND sender_type = 'student' AND student_user_id = ? AND id > ?");
    $stmt->execute([$sessionId, $ask['addressed_user_id'], $ask['id']]);
    return (int) $stmt->fetchColumn() === 0 ? ['message_id' => (int) $ask['id'], 'user_id' => (int) $ask['addressed_user_id']] : null;
}

/** A name nobody else in the room has, so the AI and the UI can't mix two people up. */
function gsUniqueName(PDO $pdo, int $sessionId, int $userId, string $name): string {
    $stmt = $pdo->prepare("SELECT display_name FROM group_session_participants WHERE session_id = ? AND user_id != ?");
    $stmt->execute([$sessionId, $userId]);
    $taken = array_column($stmt->fetchAll(), 'display_name');
    $candidate = $name;
    for ($n = 2; in_array($candidate, $taken, true); $n++) $candidate = "{$name} ({$n})";
    return $candidate;
}

/** Fire-and-forget push — a failed push must never break the actual state change. */
function gsPushTurn(PDO $pdo, int $userId, string $topic): void {
    try {
        require_once __DIR__ . '/../includes/webpush_config.php';
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) require_once __DIR__ . '/../vendor/autoload.php';
        if (!class_exists('Minishlink\\WebPush\\WebPush')) return;

        $stmt = $pdo->prepare("SELECT endpoint, endpoint_hash, p256dh, auth FROM push_subscriptions WHERE user_id = ?");
        $stmt->execute([$userId]);
        $subs = $stmt->fetchAll();
        if (!$subs) return;

        $cfg = getWebPushConfig();
        $webPush = new \Minishlink\WebPush\WebPush(['VAPID' => [
            'subject' => $cfg['vapid_subject'], 'publicKey' => $cfg['vapid_public_key'], 'privateKey' => $cfg['vapid_private_key'],
        ]]);
        $payload = json_encode(['title' => "It's your turn!", 'body' => "Your study group is ready for you to teach \"{$topic}\"."]);
        foreach ($subs as $sub) {
            $webPush->queueNotification(\Minishlink\WebPush\Subscription::create([
                'endpoint' => $sub['endpoint'], 'publicKey' => $sub['p256dh'], 'authToken' => $sub['auth'],
            ]), $payload);
        }
        $del = $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint_hash = ?");
        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess() && $report->isSubscriptionExpired()) $del->execute([hash('sha256', $report->getEndpoint())]);
        }
    } catch (Throwable $e) {
        error_log("group_study: turn-notification push failed: " . $e->getMessage());
    }
}

/** Load a room the caller belongs to, or fail the request. */
function gsRequireMember(PDO $pdo, int $userId, int $sessionId, bool $mustBeActive = false): ?array {
    $session = $sessionId > 0 ? gsSession($pdo, $sessionId) : null;
    if (!$session) { gsFail(404, 'That room no longer exists.'); return null; }
    $participants = gsParticipants($pdo, $sessionId);
    if (!isset($participants[$userId])) { gsFail(403, "You're not in this room."); return null; }
    if ($mustBeActive && $session['status'] !== 'active') {
        gsFail(409, $session['status'] === 'completed' ? 'This session has ended.' : 'Waiting for someone else to join.');
        return null;
    }
    return [$session, $participants];
}

/** Hand the teacher role to $nextId (caller already validated). */
function gsHandTurn(PDO $pdo, array $session, ?int $nextId, string $line): void {
    $sessionId = (int) $session['id'];
    $current = $session['current_teacher_user_id'] !== null ? (int) $session['current_teacher_user_id'] : null;
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE group_session_turns SET status = 'completed', ended_at = NOW() WHERE session_id = ? AND status != 'completed'")
            ->execute([$sessionId]);
        if ($current !== null) {
            $pdo->prepare("UPDATE group_session_participants SET has_taught = 1 WHERE session_id = ? AND user_id = ?")
                ->execute([$sessionId, $current]);
        }
        $pdo->prepare("UPDATE group_sessions SET current_teacher_user_id = ? WHERE id = ?")->execute([$nextId, $sessionId]);
        if ($nextId !== null) {
            $pdo->prepare("INSERT INTO group_session_turns (session_id, teacher_user_id) VALUES (?, ?)")->execute([$sessionId, $nextId]);
        }
        gsSystem($pdo, $sessionId, $line);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    if ($nextId !== null) gsPushTurn($pdo, $nextId, $session['topic']);
}

// --------------------------------------------------------------------------
// Handlers
// --------------------------------------------------------------------------

function gsCreate(PDO $pdo, int $userId, string $name, array $data): void {
    $topic = mb_substr(trim($data['topic'] ?? ''), 0, 255);
    if ($topic === '') { gsFail(400, 'Give the session a topic first.'); return; }

    $code = gsJoinCode($pdo);
    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO group_sessions (host_user_id, topic, join_code, status) VALUES (?, ?, ?, 'waiting')")
            ->execute([$userId, $topic, $code]);
        $sessionId = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO group_session_participants (session_id, user_id, display_name, last_seen_at) VALUES (?, ?, ?, NOW())")
            ->execute([$sessionId, $userId, $name]);
        gsSystem($pdo, $sessionId, gsFirstName($name) . " opened the room.");
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    echo json_encode(['success' => true, 'session_id' => $sessionId, 'join_code' => $code]);
}

function gsJoin(PDO $pdo, int $userId, string $name, array $data): void {
    $code = strtoupper(preg_replace('/\s+/', '', $data['join_code'] ?? ''));
    if (!preg_match('/^[A-Z0-9]{6}$/', $code)) { gsFail(400, 'Join codes are 6 letters and numbers.'); return; }

    $stmt = $pdo->prepare("SELECT id FROM group_sessions WHERE join_code = ?");
    $stmt->execute([$code]);
    $sessionId = (int) $stmt->fetchColumn();
    $session = $sessionId ? gsSession($pdo, $sessionId) : null;
    if (!$session) { gsFail(404, 'No room found with that code.'); return; }
    if ($session['status'] === 'completed') { gsFail(410, 'That session has already ended.'); return; }

    $stmt = $pdo->prepare("SELECT display_name, left_at FROM group_session_participants WHERE session_id = ? AND user_id = ?");
    $stmt->execute([$sessionId, $userId]);
    $existing = $stmt->fetch();

    if (!$existing) {
        $pdo->prepare("INSERT INTO group_session_participants (session_id, user_id, display_name, last_seen_at) VALUES (?, ?, ?, NOW())")
            ->execute([$sessionId, $userId, gsUniqueName($pdo, $sessionId, $userId, $name)]);
        gsSystem($pdo, $sessionId, gsFirstName($name) . " joined.");
    } else {
        $pdo->prepare("UPDATE group_session_participants SET left_at = NULL, last_seen_at = NOW() WHERE session_id = ? AND user_id = ?")
            ->execute([$sessionId, $userId]);
        if ($existing['left_at'] !== null) gsSystem($pdo, $sessionId, gsFirstName($existing['display_name']) . " is back.");
    }

    // Two people in the room: open the first turn — the host teaches first.
    $present = array_filter(gsParticipants($pdo, $sessionId), fn($p) => !$p['left']);
    if ($session['status'] === 'waiting' && count($present) >= 2) {
        $pdo->prepare("UPDATE group_sessions SET status = 'active', current_teacher_user_id = ? WHERE id = ?")
            ->execute([$session['host_user_id'], $sessionId]);
        $pdo->prepare("INSERT INTO group_session_turns (session_id, teacher_user_id) VALUES (?, ?)")
            ->execute([$sessionId, $session['host_user_id']]);
        $host = gsParticipants($pdo, $sessionId)[(int) $session['host_user_id']]['display_name'] ?? 'The host';
        gsSystem($pdo, $sessionId, gsFirstName($host) . " is teaching first.");
    }
    echo json_encode(['success' => true, 'session_id' => $sessionId]);
}

function gsOpen(PDO $pdo, int $userId, array $data): void {
    $sessionId = (int) ($data['session_id'] ?? 0);
    if (!($m = gsRequireMember($pdo, $userId, $sessionId))) return;
    if ($m[0]['status'] === 'completed') { gsFail(410, 'That session has already ended.'); return; }
    gsJoin($pdo, $userId, '', ['join_code' => $m[0]['join_code']]);
}

function gsMine(PDO $pdo, int $userId): void {
    // idle_seconds is worked out in SQL: PHP and MySQL don't share a timezone here
    $stmt = $pdo->prepare("
        SELECT s.id, s.topic, s.join_code, s.status, s.host_user_id = ? AS is_host,
               p.left_at IS NOT NULL AS i_left, TIMESTAMPDIFF(SECOND, s.updated_at, NOW()) AS idle_seconds
        FROM group_sessions s JOIN group_session_participants p ON p.session_id = s.id AND p.user_id = ?
        WHERE s.status != 'completed' AND s.updated_at > NOW() - INTERVAL " . GS_STALE_HOURS . " HOUR
        ORDER BY s.updated_at DESC LIMIT 6
    ");
    $stmt->execute([$userId, $userId]);
    $rows = $stmt->fetchAll();

    // Who's still in each room (not left), and who's here right now
    $members = [];
    if ($rows) {
        $ids = array_map(fn($r) => (int) $r['id'], $rows);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $m = $pdo->prepare("
            SELECT session_id, user_id, display_name,
                   last_seen_at > NOW() - INTERVAL " . GS_ONLINE_SECONDS . " SECOND AS online
            FROM group_session_participants
            WHERE session_id IN ($in) AND left_at IS NULL
            ORDER BY joined_at
        ");
        $m->execute($ids);
        foreach ($m->fetchAll() as $p) {
            $members[(int) $p['session_id']][] = ['name' => gsFirstName($p['display_name']),
                'online' => (bool) $p['online'], 'me' => (int) $p['user_id'] === $userId];
        }
    }

    $rooms = array_map(fn($r) => ['session_id' => (int) $r['id'], 'topic' => $r['topic'], 'join_code' => $r['join_code'],
        'status' => $r['status'], 'is_host' => (bool) $r['is_host'], 'i_left' => (bool) $r['i_left'],
        'idle_seconds' => max(0, (int) $r['idle_seconds']), 'members' => $members[(int) $r['id']] ?? []], $rows);
    echo json_encode(['success' => true, 'rooms' => $rooms]);
}

function gsTyping(PDO $pdo, int $userId, array $data): void {
    $pdo->prepare("UPDATE group_session_participants SET typing_at = NOW(), last_seen_at = NOW() WHERE session_id = ? AND user_id = ?")
        ->execute([(int) ($data['session_id'] ?? 0), $userId]);
    echo json_encode(['success' => true]);
}

function gsSend(PDO $pdo, int $userId, array $data): void {
    $sessionId = (int) ($data['session_id'] ?? 0);
    $text = trim($data['message'] ?? '');
    if ($text === '') { gsFail(400, 'Type a message first.'); return; }
    if (mb_strlen($text) > GS_MAX_MESSAGE) { gsFail(400, 'That message is too long — split it up.'); return; }
    if (!($m = gsRequireMember($pdo, $userId, $sessionId, true))) return;
    [$session, $participants] = $m;
    if ($participants[$userId]['left']) { gsFail(409, 'Rejoin the room to send messages.'); return; }

    $openAsk = gsOpenAsk($pdo, $sessionId);
    $teacherId = $session['current_teacher_user_id'] !== null ? (int) $session['current_teacher_user_id'] : null;

    $stmt = $pdo->prepare("SELECT id FROM group_session_turns WHERE session_id = ? AND status != 'completed' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$sessionId]);
    $turnId = $stmt->fetchColumn() ?: null;

    $pdo->prepare("INSERT INTO group_session_messages (session_id, turn_id, sender_type, student_user_id, content) VALUES (?, ?, 'student', ?, ?)")
        ->execute([$sessionId, $turnId, $userId, $text]);
    $messageId = (int) $pdo->lastInsertId();
    $pdo->prepare("UPDATE group_session_participants SET typing_at = NULL, last_seen_at = NOW() WHERE session_id = ? AND user_id = ?")
        ->execute([$sessionId, $userId]);
    gsTouch($pdo, $sessionId);

    // Returns at once so the sender's composer frees up; if Q should answer,
    // the client follows with `reply`, which does the slow AI call.
    $mode = gsReplyMode($openAsk, $userId, $teacherId, $text);
    echo json_encode(['success' => true, 'message_id' => $messageId, 'q_replies' => $mode !== null]);
}

/** Does Q answer this message? The person it called on, an @Q mention, or the teacher. */
function gsReplyMode(?array $openAsk, int $senderId, ?int $teacherId, string $text): ?string {
    if ($openAsk && $openAsk['user_id'] === $senderId) return 'answer';
    if (preg_match('/(^|[^\w@])@q\b/i', $text)) return 'mention';
    if ($senderId === $teacherId) return 'teacher';
    return null;
}

/** Q's reply to one of the caller's messages (the second half of `send`). */
function gsReply(PDO $pdo, int $userId, array $data, array $keys): void {
    $sessionId = (int) ($data['session_id'] ?? 0);
    $messageId = (int) ($data['message_id'] ?? 0);
    if (!($m = gsRequireMember($pdo, $userId, $sessionId, true))) return;
    [$session, $participants] = $m;

    $stmt = $pdo->prepare("SELECT id, turn_id, content FROM group_session_messages
        WHERE id = ? AND session_id = ? AND sender_type = 'student' AND student_user_id = ? AND created_at > NOW() - INTERVAL 5 MINUTE");
    $stmt->execute([$messageId, $sessionId, $userId]);
    $msg = $stmt->fetch();
    if (!$msg) { gsFail(404, 'Nothing to reply to.'); return; }
    // Already answered (e.g. a retried request)?
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM group_session_messages WHERE session_id = ? AND sender_type = 'ai' AND id > ?");
    $stmt->execute([$sessionId, $messageId]);
    if ((int) $stmt->fetchColumn() > 0) { echo json_encode(['success' => true, 'replied' => false]); return; }

    // Re-derive why Q answers, as of when the message was sent
    $stmt = $pdo->prepare("SELECT id, addressed_user_id FROM group_session_messages
        WHERE session_id = ? AND sender_type = 'ai' AND id < ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$sessionId, $messageId]);
    $lastQ = $stmt->fetch();
    $openAsk = null;
    if ($lastQ && $lastQ['addressed_user_id'] !== null && (int) $lastQ['addressed_user_id'] === $userId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM group_session_messages
            WHERE session_id = ? AND sender_type = 'student' AND student_user_id = ? AND id > ? AND id < ?");
        $stmt->execute([$sessionId, $userId, $lastQ['id'], $messageId]);
        if ((int) $stmt->fetchColumn() === 0) $openAsk = ['message_id' => (int) $lastQ['id'], 'user_id' => $userId];
    }
    $teacherId = $session['current_teacher_user_id'] !== null ? (int) $session['current_teacher_user_id'] : null;
    $mode = gsReplyMode($openAsk, $userId, $teacherId, $msg['content']);
    if ($mode === null) { echo json_encode(['success' => true, 'replied' => false]); return; }

    // One reply at a time per room: take the lease, or leave it to the reply
    // in flight (which reads the recent history, so it'll see this message).
    $lease = $pdo->prepare("UPDATE group_sessions SET ai_busy_until = NOW() + INTERVAL " . GS_AI_LEASE_SECONDS . " SECOND
        WHERE id = ? AND (ai_busy_until IS NULL OR ai_busy_until < NOW())");
    $lease->execute([$sessionId]);
    if ($lease->rowCount() === 0) { echo json_encode(['success' => true, 'replied' => false]); return; }

    try {
        $reply = gsFacilitate($pdo, $session, $participants, $userId, $mode, $openAsk, $msg['turn_id'], $keys);
    } finally {
        $pdo->prepare("UPDATE group_sessions SET ai_busy_until = NULL WHERE id = ?")->execute([$sessionId]);
    }
    if ($reply === null) { gsFail(502, "Q couldn't reply just now."); return; }
    echo json_encode(['success' => true, 'replied' => true]);
}

/** Build the prompt, pick who (if anyone) Q calls on, call the AI, store the reply. */
function gsFacilitate(PDO $pdo, array $session, array $participants, int $senderId, string $mode, ?array $openAsk, $turnId, array $keys): ?int {
    $sessionId = (int) $session['id'];
    $teacherId = $session['current_teacher_user_id'] !== null ? (int) $session['current_teacher_user_id'] : null;

    // Recent history, oldest first. Speaker names travel in the text (the
    // model only has user/model roles); system lines give it the room's events.
    $stmt = $pdo->prepare("SELECT * FROM (SELECT id, sender_type, student_user_id, content FROM group_session_messages
        WHERE session_id = ? ORDER BY id DESC LIMIT " . GS_HISTORY_MESSAGES . ") t ORDER BY id ASC");
    $stmt->execute([$sessionId]);
    $contents = [];
    foreach ($stmt->fetchAll() as $row) {
        if ($row['sender_type'] === 'ai') {
            $contents[] = ['role' => 'model', 'parts' => [['text' => $row['content']]]];
        } else {
            $who = $row['sender_type'] === 'system' ? 'Room'
                : ($participants[(int) $row['student_user_id']]['display_name'] ?? 'A student');
            $contents[] = ['role' => 'user', 'parts' => [['text' => "[{$who}]: {$row['content']}"]]];
        }
    }
    // Gemini wants alternating turns; merge consecutive user lines into one.
    $merged = [];
    foreach ($contents as $c) {
        $last = count($merged) - 1;
        if ($last >= 0 && $merged[$last]['role'] === $c['role']) $merged[$last]['parts'][0]['text'] .= "\n" . $c['parts'][0]['text'];
        else $merged[] = $c;
    }
    if ($merged && $merged[0]['role'] === 'model') array_shift($merged);

    // Who to call on: the quietest person who's here, isn't teaching, didn't
    // just speak, and wasn't the last one asked — at most every other Q reply.
    $callOn = null;
    if ($mode !== 'answer') {
        $stmt = $pdo->prepare("SELECT addressed_user_id FROM group_session_messages WHERE session_id = ? AND sender_type = 'ai' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$sessionId]);
        $recentQ = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $qHasSpoken = count($recentQ) > 0;
        $askedRecently = array_filter(array_map('intval', array_filter($recentQ, fn($v) => $v !== null)));
        if ($qHasSpoken && !$askedRecently && !$openAsk) {
            $pool = array_filter($participants, fn($p) => $p['online'] && !$p['left']
                && $p['user_id'] !== $teacherId && $p['user_id'] !== $senderId);
            if ($pool) {
                usort($pool, fn($a, $b) => [$a['said'], $a['last_said_id']] <=> [$b['said'], $b['last_said_id']]);
                $quietest = $pool[0];
                $others = array_filter($participants, fn($p) => $p['user_id'] !== $quietest['user_id'] && !$p['left']);
                $avg = $others ? array_sum(array_column($others, 'said')) / count($others) : 0;
                if ($quietest['said'] <= $avg) $callOn = $quietest;
            }
        }
    }

    $roster = [];
    foreach ($participants as $p) {
        if ($p['left']) continue;
        $roster[] = ['name' => $p['display_name'], 'teaching' => $p['user_id'] === $teacherId,
            'has_taught' => $p['has_taught'], 'said' => $p['said'], 'here' => $p['online']];
    }
    $untaught = array_values(array_map(fn($p) => $p['display_name'], array_filter($participants,
        fn($p) => !$p['has_taught'] && !$p['left'] && $p['user_id'] !== $teacherId)));

    $systemPrompt = buildFacilitatorPrompt([
        'topic'    => $session['topic'],
        'teacher'  => $teacherId !== null ? ($participants[$teacherId]['display_name'] ?? null) : null,
        'roster'   => $roster,
        'untaught' => $untaught,
        'mode'     => $mode,
        'sender'   => $participants[$senderId]['display_name'],
        'call_on'  => $callOn['display_name'] ?? null,
    ]);

    $payload = json_encode([
        'contents' => $merged,
        'system_instruction' => ['role' => 'system', 'parts' => [['text' => $systemPrompt]]],
        // Replies are 2-4 sentences, but gemini-flash-latest's hidden "thinking"
        // tokens count against this budget: at 1024 replies got cut mid-sentence
        'generationConfig' => ['maxOutputTokens' => 4096, 'temperature' => 0.7],
    ]);
    try {
        $response = callGeminiAPI($payload, $keys['gemini']);
    } catch (Exception $e) {
        try {
            $response = callGroqAPI($merged, $systemPrompt, $keys['groq']);
        } catch (Exception $e2) {
            $response = callDeepSeekAPI($merged, $systemPrompt, $keys['deepseek']);
        }
    }
    $text = trim($response['candidates'][0]['content']['parts'][0]['text'] ?? '');
    $text = gsNormalizeHandoff($text);
    if ($text === '') return null;

    // Only mark it addressed if Q actually spoke to them by name.
    $addressed = null;
    if ($callOn && stripos($text, gsFirstName($callOn['display_name'])) !== false) $addressed = $callOn['user_id'];

    $pdo->prepare("INSERT INTO group_session_messages (session_id, turn_id, sender_type, content, addressed_user_id) VALUES (?, ?, 'ai', ?, ?)")
        ->execute([$sessionId, $turnId, $text, $addressed]);
    gsTouch($pdo, $sessionId);
    return (int) $pdo->lastInsertId();
}

function gsPoll(PDO $pdo, int $userId, array $data): void {
    $sessionId = (int) ($data['session_id'] ?? 0);
    $sinceId = (int) ($data['since_id'] ?? 0);
    if (!($m = gsRequireMember($pdo, $userId, $sessionId))) return;
    [$session] = $m;

    $pdo->prepare("UPDATE group_session_participants SET last_seen_at = NOW() WHERE session_id = ? AND user_id = ? AND left_at IS NULL")
        ->execute([$sessionId, $userId]);
    $participants = gsParticipants($pdo, $sessionId);

    $stmt = $pdo->prepare("SELECT id, turn_id, sender_type, student_user_id, content, addressed_user_id
        FROM group_session_messages WHERE session_id = ? AND id > ? ORDER BY id ASC LIMIT 200");
    $stmt->execute([$sessionId, $sinceId]);
    $messages = [];
    foreach ($stmt->fetchAll() as $r) {
        $uid = $r['student_user_id'] !== null ? (int) $r['student_user_id'] : null;
        $messages[] = [
            'id'        => (int) $r['id'],
            'type'      => $r['sender_type'],
            'user_id'   => $uid,
            'sender'    => $r['sender_type'] === 'ai' ? 'Q' : ($r['sender_type'] === 'system' ? null : ($participants[$uid]['display_name'] ?? 'Someone who left')),
            'content'   => $r['content'],
            'turn_id'   => $r['turn_id'] !== null ? (int) $r['turn_id'] : null,
            'addressed_user_id' => $r['addressed_user_id'] !== null ? (int) $r['addressed_user_id'] : null,
        ];
    }

    $stmt = $pdo->prepare("SELECT user_id FROM group_session_participants
        WHERE session_id = ? AND user_id != ? AND left_at IS NULL AND typing_at > NOW() - INTERVAL " . GS_TYPING_SECONDS . " SECOND");
    $stmt->execute([$sessionId, $userId]);
    $typing = array_values(array_filter(array_map(fn($id) => $participants[(int) $id]['display_name'] ?? null, $stmt->fetchAll(PDO::FETCH_COLUMN))));

    $stmt = $pdo->prepare("SELECT id FROM group_session_turns WHERE session_id = ? AND status != 'completed' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$sessionId]);
    $teacherId = $session['current_teacher_user_id'] !== null ? (int) $session['current_teacher_user_id'] : null;

    echo json_encode([
        'success'      => true,
        'me'           => $userId,
        'status'       => $session['status'],
        'topic'        => $session['topic'],
        'join_code'    => $session['join_code'],
        'host_user_id' => (int) $session['host_user_id'],
        'teacher_user_id' => $teacherId,
        'turn_id'      => ($t = $stmt->fetchColumn()) ? (int) $t : null,
        'q_thinking'   => (bool) $session['ai_thinking'],
        'open_ask'     => gsOpenAsk($pdo, $sessionId),
        'participants' => array_values(array_map(fn($p) => array_diff_key($p, ['last_said_id' => 1]), $participants)),
        'typing'       => $typing,
        'messages'     => $messages,
    ]);
}

function gsPassTurn(PDO $pdo, int $userId, array $data): void {
    $sessionId = (int) ($data['session_id'] ?? 0);
    $nextId = (int) ($data['next_user_id'] ?? 0);
    if (!($m = gsRequireMember($pdo, $userId, $sessionId, true))) return;
    [$session, $participants] = $m;

    $teacherId = $session['current_teacher_user_id'] !== null ? (int) $session['current_teacher_user_id'] : null;
    if ($userId !== $teacherId && $userId !== (int) $session['host_user_id']) {
        gsFail(403, 'Only the person teaching (or the host) can pass the turn.'); return;
    }
    if (!isset($participants[$nextId]) || $participants[$nextId]['left'] || $nextId === $teacherId) {
        gsFail(400, "Pick someone who's in the room and isn't teaching now."); return;
    }
    $from = gsFirstName($participants[$teacherId]['display_name'] ?? 'The teacher');
    $to = gsFirstName($participants[$nextId]['display_name']);
    gsHandTurn($pdo, $session, $nextId, "{$from} passed the turn to {$to}. {$to} is teaching now.");
    echo json_encode(['success' => true]);
}

function gsEnd(PDO $pdo, int $userId, array $data): void {
    $sessionId = (int) ($data['session_id'] ?? 0);
    if (!($m = gsRequireMember($pdo, $userId, $sessionId))) return;
    [$session, $participants] = $m;
    if ($userId !== (int) $session['host_user_id']) { gsFail(403, 'Only the host can end the session.'); return; }
    if ($session['status'] === 'completed') { echo json_encode(['success' => true]); return; }

    $pdo->prepare("UPDATE group_session_turns SET status = 'completed', ended_at = NOW() WHERE session_id = ? AND status != 'completed'")
        ->execute([$sessionId]);
    $pdo->prepare("UPDATE group_sessions SET status = 'completed', ended_at = NOW() WHERE id = ?")->execute([$sessionId]);
    gsSystem($pdo, $sessionId, gsFirstName($participants[$userId]['display_name']) . " ended the session.");
    echo json_encode(['success' => true]);
}

function gsLeave(PDO $pdo, int $userId, array $data): void {
    $sessionId = (int) ($data['session_id'] ?? 0);
    if (!($m = gsRequireMember($pdo, $userId, $sessionId))) return;
    [$session, $participants] = $m;
    if ($session['status'] === 'completed' || $participants[$userId]['left']) { echo json_encode(['success' => true]); return; }

    $pdo->prepare("UPDATE group_session_participants SET left_at = NOW(), typing_at = NULL WHERE session_id = ? AND user_id = ?")
        ->execute([$sessionId, $userId]);
    $name = gsFirstName($participants[$userId]['display_name']);
    gsSystem($pdo, $sessionId, "{$name} left.");

    $remaining = array_filter($participants, fn($p) => !$p['left'] && $p['user_id'] !== $userId);
    if (!$remaining) {
        // Last one out: nobody left to study with
        $pdo->prepare("UPDATE group_sessions SET status = 'completed', ended_at = NOW() WHERE id = ?")->execute([$sessionId]);
    } elseif ($session['status'] === 'active' && (int) $session['current_teacher_user_id'] === $userId) {
        // The teacher left: hand it to someone here who hasn't taught, else anyone here
        usort($remaining, fn($a, $b) => [$a['has_taught'], !$a['online']] <=> [$b['has_taught'], !$b['online']]);
        $next = reset($remaining);
        gsHandTurn($pdo, $session, $next['user_id'], gsFirstName($next['display_name']) . " is teaching now.");
    }
    echo json_encode(['success' => true]);
}
