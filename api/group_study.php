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
 * Huddle rounds (migration 020): a question the whole room answers at once.
 * Answers stay hidden until everyone here has locked in (or the host reveals,
 * or GS_ROUND_TIMEOUT_SECONDS pass), then show together, and Q calls on someone
 * from the split. Every revealed round lays a stone on the room's bridge.
 *   round_start   { session_id, source }        host starts one; source 'timer' = the
 *                                               focus timer ran out (any client may ask;
 *                                               one atomically claims it)
 *   round_answer  { session_id, round_id, choice }   lock in (final)
 *   round_reveal  { session_id, round_id }      host reveals with whoever's in
 *   react         { session_id, message_id, kind }    toggle a reaction
 *   focus_start   { session_id, minutes }       host starts the shared focus timer
 *   focus_stop    { session_id }                host stops it
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
const GS_ROUND_NAME = 'Huddle round';      // what the room calls a round (shown in Q's lines)
const GS_ROUND_TIMEOUT_SECONDS = 120;      // reveal with whoever's in, so one idle tab can't stall the room
const GS_BRIDGE_STONES = 5;                // revealed rounds per finished bridge
const GS_FOCUS_MINUTES = [10, 15, 25];
const GS_REACTIONS = ['clicked', 'wait', 'same', 'cheer'];

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
// Own Pulse lane per action: the 3s poll and a 40s AI reply must not share one P99.
if (function_exists('pulse_label')) pulse_label($action ?: 'none');

try {
    switch ($action) {
        case 'create':    gsCreate($pdo, $user_id, $displayName, $body); break;
        case 'join':      gsJoin($pdo, $user_id, $displayName, $body); break;
        case 'open':      gsOpen($pdo, $user_id, $body); break;
        case 'mine':      gsMine($pdo, $user_id); break;
        case 'history':   gsHistory($pdo, $user_id); break;
        case 'send':      gsSend($pdo, $user_id, $body); break;
        case 'reply':     gsReply($pdo, $user_id, $body, $AI_KEYS); break;
        case 'typing':    gsTyping($pdo, $user_id, $body); break;
        case 'poll':      gsPoll($pdo, $user_id, $body); break;
        case 'pass_turn': gsPassTurn($pdo, $user_id, $body); break;
        case 'end':       gsEnd($pdo, $user_id, $body); break;
        case 'leave':     gsLeave($pdo, $user_id, $body); break;
        case 'round_start':  gsRoundStart($pdo, $user_id, $body, $AI_KEYS); break;
        case 'round_answer': gsRoundAnswer($pdo, $user_id, $body); break;
        case 'round_reveal': gsRoundRevealAction($pdo, $user_id, $body); break;
        case 'react':        gsReact($pdo, $user_id, $body); break;
        case 'focus_start':  gsFocusStart($pdo, $user_id, $body); break;
        case 'focus_stop':   gsFocusStop($pdo, $user_id, $body); break;
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

/** Session row, ending it first if it's gone stale. A stale room ended when it went
 *  quiet, not when someone next opened it — and assigning updated_at to itself stops
 *  ON UPDATE CURRENT_TIMESTAMP from bumping it (room history sorts by these). */
function gsSession(PDO $pdo, int $sessionId): ?array {
    $pdo->prepare("
        UPDATE group_sessions SET status = 'completed', ended_at = updated_at, updated_at = updated_at
        WHERE id = ? AND status != 'completed' AND updated_at < NOW() - INTERVAL " . GS_STALE_HOURS . " HOUR
    ")->execute([$sessionId]);
    // focus_left is worked out in SQL: PHP and MySQL don't share a timezone here
    $stmt = $pdo->prepare("SELECT id, host_user_id, topic, join_code, status, current_teacher_user_id,
        (ai_busy_until IS NOT NULL AND ai_busy_until > NOW()) AS ai_thinking,
        focus_minutes, IF(focus_ends_at IS NULL, NULL, TIMESTAMPDIFF(SECOND, NOW(), focus_ends_at)) AS focus_left
        FROM group_sessions WHERE id = ?");
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

/** Rooms you were in that have ended (by the host, the last one leaving, or going stale).
 *  Read-only afterwards: poll still serves a completed room's transcript. */
function gsHistory(PDO $pdo, int $userId): void {
    $stmt = $pdo->prepare("
        SELECT s.id, s.topic, s.host_user_id = ? AS is_host,
               TIMESTAMPDIFF(SECOND, COALESCE(s.ended_at, s.updated_at), NOW()) AS ended_seconds_ago,
               (SELECT COUNT(*) FROM group_session_participants x WHERE x.session_id = s.id) AS people,
               (SELECT COUNT(*) FROM group_session_messages m WHERE m.session_id = s.id AND m.sender_type = 'student') AS said
        FROM group_sessions s JOIN group_session_participants p ON p.session_id = s.id AND p.user_id = ?
        WHERE s.status = 'completed' OR s.updated_at < NOW() - INTERVAL " . GS_STALE_HOURS . " HOUR
        ORDER BY COALESCE(s.ended_at, s.updated_at) DESC LIMIT 10
    ");
    $stmt->execute([$userId, $userId]);
    $rooms = array_map(fn($r) => ['session_id' => (int) $r['id'], 'topic' => $r['topic'], 'is_host' => (bool) $r['is_host'],
        'ended_seconds_ago' => max(0, (int) $r['ended_seconds_ago']), 'people' => (int) $r['people'], 'said' => (int) $r['said']],
        $stmt->fetchAll());
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
    // callDeepSeekAPI throws on an empty reply; all providers failing just means Q stays quiet
    try {
        $response = aiComplete('group', gsProviderCalls($keys, [
            'gemini'   => fn() => callGeminiAPI($payload, $keys['gemini']),
            'groq'     => fn() => callGroqAPI($merged, $systemPrompt, $keys['groq']),
            'deepseek' => fn() => callDeepSeekAPI($merged, $systemPrompt, $keys['deepseek']),
        ]))['response'];
    } catch (AiProvidersFailed $e) {
        error_log('group_study facilitate: ' . $e->getMessage());
        return null;
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

    // A round nobody else is going to finish (an idle tab, someone who wandered
    // off) reveals with whoever's in once it's been open long enough.
    if ($session['status'] === 'active') {
        $stmt = $pdo->prepare("SELECT id FROM group_rounds WHERE session_id = ? AND status = 'open'
            AND created_at < NOW() - INTERVAL " . GS_ROUND_TIMEOUT_SECONDS . " SECOND
            AND EXISTS (SELECT 1 FROM group_round_answers a WHERE a.round_id = group_rounds.id)");
        $stmt->execute([$sessionId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $rid) gsRoundReveal($pdo, $session, (int) $rid, $participants);
    }

    $stmt = $pdo->prepare("SELECT id, turn_id, sender_type, student_user_id, content, addressed_user_id, round_id
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
            'round_id'  => $r['round_id'] !== null ? (int) $r['round_id'] : null,
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
        'rounds'       => gsRoundsFor($pdo, $sessionId, $userId),
        'reactions'    => gsReactionsFor($pdo, $sessionId, $userId),
        'bridge'       => gsBridge($pdo, $sessionId),
        // A positive number while the shared timer runs; 0 or less = it's due (any
        // client then asks round_start{source:'timer'}, and one wins the claim)
        'focus'        => $session['focus_left'] === null ? null
            : ['seconds_left' => (int) $session['focus_left'], 'minutes' => (int) $session['focus_minutes']],
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
    // They might have been the last one a round was waiting on
    if ($remaining && $session['status'] === 'active') gsRevealIfEveryoneIn($pdo, $session, null);
    echo json_encode(['success' => true]);
}

// --------------------------------------------------------------------------
// Huddle rounds
// --------------------------------------------------------------------------

/** Keep only the providers this install has a key for, so aiComplete never wastes
 *  a try on one it can't call. Closures are keyed by provider name. */
function gsProviderCalls(array $keys, array $calls): array {
    return array_filter($calls, fn($name) => !empty($keys[$name]), ARRAY_FILTER_USE_KEY);
}

/** Ask the AI for one JSON object. A provider that answers with broken or empty
 *  JSON is rejected and the next one is tried instead of failing the round (same
 *  lesson as api/quiz.php); the order and fallback live in aiComplete(). */
function gsAskJson(string $system, string $user, array $keys, callable $valid): ?array {
    $history = [['role' => 'user', 'parts' => [['text' => $user]]]];
    $calls = gsProviderCalls($keys, [
        'gemini' => fn() => callGeminiAPI(json_encode([
            'contents' => $history,
            'system_instruction' => ['role' => 'system', 'parts' => [['text' => $system]]],
            // Flash's hidden thinking counts against this budget (see gsFacilitate)
            'generationConfig' => ['response_mime_type' => 'application/json', 'maxOutputTokens' => 4096, 'temperature' => 0.6],
        ]), $keys['gemini']),
        'groq'     => fn() => callGroqAPI($history, $system, $keys['groq']),
        'deepseek' => fn() => callDeepSeekAPI($history, $system, $keys['deepseek']),
    ]);
    $accept = function ($response) use ($valid) {
        $text = trim($response['candidates'][0]['content']['parts'][0]['text'] ?? '');
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end < $start) return null;
        $data = json_decode(substr($text, $start, $end - $start + 1), true);
        if (is_array($data) && $valid($data)) return $data;
        error_log('group_study round: unusable JSON: ' . substr($text, 0, 200));
        return null;
    };
    try {
        return aiComplete('group', $calls, $accept)['value'];
    } catch (AiProvidersFailed $e) {
        error_log('group_study round: ' . $e->getMessage());
        return null;
    }
}

/** One multiple-choice question built from what the room has actually said.
 *  $kind 'predict' = about the idea in play right now; 'recall' = something the
 *  room covered earlier, to answer from memory at the end of a focus session. */
function gsWriteRound(PDO $pdo, array $session, array $participants, string $kind, array $keys): ?array {
    $stmt = $pdo->prepare("SELECT * FROM (SELECT id, sender_type, student_user_id, content FROM group_session_messages
        WHERE session_id = ? AND round_id IS NULL ORDER BY id DESC LIMIT " . GS_HISTORY_MESSAGES . ") t ORDER BY id ASC");
    $stmt->execute([(int) $session['id']]);
    $lines = [];
    foreach ($stmt->fetchAll() as $row) {
        $who = $row['sender_type'] === 'ai' ? 'Q' : ($row['sender_type'] === 'system' ? 'Room'
            : ($participants[(int) $row['student_user_id']]['display_name'] ?? 'A student'));
        $lines[] = "[{$who}]: " . mb_substr($row['content'], 0, 600);
    }
    $transcript = $lines ? implode("\n", $lines) : '(nothing said yet)';

    $focus = $kind === 'recall'
        ? 'It closes a focus session, so ask about something the room covered EARLIER in the conversation, answerable from memory without scrolling back.'
        : 'Ask about the idea the room is working on RIGHT NOW: have them predict an outcome or apply the idea to a new case, not recall wording.';
    $system = <<<TXT
You write ONE multiple-choice question for a live study round in a group chat on "{$session['topic']}". Everyone answers at once, then the answers are revealed together, so a question that splits the room is ideal.
{$focus}
Rules:
- Base it on what the room actually discussed (below). If they've barely started, ask about the topic's core idea.
- 3 or 4 short options, exactly one correct, each on a single line (write program output as "2, 1, Go!"). Wrong options should be real misconceptions: ideally ones that came up in the chat, otherwise common slips.
- Plain text only: no markdown, LaTeX or code fences. Keep the question under 200 characters and each option under 80.
- Never ask who said what in the chat.
- "explanation": one sentence on why the correct answer is right.
Reply with JSON only: {"question": "...", "options": ["...", "...", "..."], "answer": <0-based index of the correct option>, "explanation": "..."}
TXT;
    $valid = function ($d) {
        if (!isset($d['question'], $d['options'], $d['answer']) || !is_string($d['question']) || !is_array($d['options'])) return false;
        $opts = array_values(array_filter(array_map(fn($o) => is_string($o) ? trim($o) : '', $d['options']), 'strlen'));
        return mb_strlen(trim($d['question'])) >= 5 && count($opts) >= 2 && count($opts) <= 4
            && count($opts) === count($d['options']) && count(array_unique($opts)) === count($opts)
            && is_numeric($d['answer']) && (int) $d['answer'] >= 0 && (int) $d['answer'] < count($opts);
    };
    $d = gsAskJson($system, "The room's conversation so far:\n" . $transcript, $keys, $valid);
    if (!$d) return null;

    // One line each: program output like "2\n1\nGo!" reads as "2 / 1 / Go!" on a card and in Q's follow-up
    $flat = fn($s) => trim(preg_replace('/\s*\R\s*/u', ' / ', trim($s)));
    // Shuffle so the right answer isn't always where the model tends to put it
    $options = array_map(fn($o) => mb_substr($flat($o), 0, 120), array_values($d['options']));
    $correctText = $options[(int) $d['answer']];
    shuffle($options);
    return [
        'question'    => mb_substr($flat($d['question']), 0, 300),
        'options'     => $options,
        'correct'     => array_search($correctText, $options, true),
        'explanation' => isset($d['explanation']) && is_string($d['explanation']) ? mb_substr(trim($d['explanation']), 0, 300) : null,
    ];
}

function gsOpenRound(PDO $pdo, int $sessionId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM group_rounds WHERE session_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$sessionId]);
    $r = $stmt->fetch();
    return $r ?: null;
}

function gsRoundStart(PDO $pdo, int $userId, array $data, array $keys): void {
    $sessionId = (int) ($data['session_id'] ?? 0);
    $source = ($data['source'] ?? '') === 'timer' ? 'timer' : 'host';
    if (!($m = gsRequireMember($pdo, $userId, $sessionId, true))) return;
    [$session, $participants] = $m;
    if ($source === 'host' && $userId !== (int) $session['host_user_id']) { gsFail(403, 'Only the host can start a round.'); return; }
    if (gsOpenRound($pdo, $sessionId)) {
        // The timer waits for the round in play; it'll fire on a later poll
        echo json_encode(['success' => true, 'started' => false, 'reason' => 'round_open']);
        return;
    }

    // One AI job per room at a time (the same lease as Q's replies), so the room
    // shows "Q is thinking" while the question is written
    $lease = $pdo->prepare("UPDATE group_sessions SET ai_busy_until = NOW() + INTERVAL " . GS_AI_LEASE_SECONDS . " SECOND
        WHERE id = ? AND (ai_busy_until IS NULL OR ai_busy_until < NOW())");
    $lease->execute([$sessionId]);
    if ($lease->rowCount() === 0) {
        if ($source === 'host') { gsFail(409, 'Q is in the middle of something. Try again in a moment.'); return; }
        echo json_encode(['success' => true, 'started' => false, 'reason' => 'busy']);
        return;
    }
    try {
        if ($source === 'timer') {
            // Every client sees the timer run out; exactly one gets to fire it
            $claim = $pdo->prepare("UPDATE group_sessions SET focus_ends_at = NULL
                WHERE id = ? AND focus_ends_at IS NOT NULL AND focus_ends_at <= NOW()");
            $claim->execute([$sessionId]);
            if ($claim->rowCount() === 0) { echo json_encode(['success' => true, 'started' => false, 'reason' => 'not_due']); return; }
        }

        $round = gsWriteRound($pdo, $session, $participants, $source === 'timer' ? 'recall' : 'predict', $keys);
        if (!$round) {
            if ($source === 'timer') gsSystem($pdo, $sessionId, "Focus session done. Q couldn't put a recall question together this time.");
            gsFail(502, "Q couldn't put a question together just now. Try again in a moment.");
            return;
        }

        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO group_rounds (session_id, source, started_by_user_id, question, options, correct_index, explanation)
            VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$sessionId, $source, $source === 'host' ? $userId : null, $round['question'],
                json_encode($round['options'], JSON_UNESCAPED_UNICODE), $round['correct'], $round['explanation']]);
        $roundId = (int) $pdo->lastInsertId();
        // Q's line carries the round (the room renders its card). The question and
        // options travel in the text too, so Q's later replies know what was asked.
        $intro = $source === 'timer'
            ? "Time's up! **Recall round:** answer from memory, no scrolling back. Answers stay hidden until everyone locks in."
            : "**" . GS_ROUND_NAME . "!** Everyone answer. Answers stay hidden until the whole room locks in.";
        $letters = ['A', 'B', 'C', 'D'];
        $listing = implode("\n", array_map(fn($o, $i) => "{$letters[$i]}) {$o}", $round['options'], array_keys($round['options'])));
        $stmt = $pdo->prepare("SELECT id FROM group_session_turns WHERE session_id = ? AND status != 'completed' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$sessionId]);
        $pdo->prepare("INSERT INTO group_session_messages (session_id, turn_id, sender_type, content, round_id) VALUES (?, ?, 'ai', ?, ?)")
            ->execute([$sessionId, $stmt->fetchColumn() ?: null, "{$intro}\n\nQuestion: {$round['question']}\n{$listing}", $roundId]);
        gsTouch($pdo, $sessionId);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    } finally {
        $pdo->prepare("UPDATE group_sessions SET ai_busy_until = NULL WHERE id = ?")->execute([$sessionId]);
    }
    echo json_encode(['success' => true, 'started' => true, 'round_id' => $roundId]);
}

function gsRoundAnswer(PDO $pdo, int $userId, array $data): void {
    $sessionId = (int) ($data['session_id'] ?? 0);
    $roundId = (int) ($data['round_id'] ?? 0);
    $choice = (int) ($data['choice'] ?? -1);
    if (!($m = gsRequireMember($pdo, $userId, $sessionId, true))) return;
    [$session, $participants] = $m;
    if ($participants[$userId]['left']) { gsFail(409, 'Rejoin the room to answer.'); return; }

    $stmt = $pdo->prepare("SELECT id, options, status FROM group_rounds WHERE id = ? AND session_id = ?");
    $stmt->execute([$roundId, $sessionId]);
    $round = $stmt->fetch();
    if (!$round) { gsFail(404, 'That round is gone.'); return; }
    if ($round['status'] !== 'open') { gsFail(409, 'Too late, that round has been revealed.'); return; }
    if ($choice < 0 || $choice >= count(json_decode($round['options'], true) ?: [])) { gsFail(400, 'Pick one of the answers.'); return; }

    // Locked means locked: a second answer from the same person is ignored
    $pdo->prepare("INSERT IGNORE INTO group_round_answers (round_id, user_id, choice) VALUES (?, ?, ?)")
        ->execute([$roundId, $userId, $choice]);
    $pdo->prepare("UPDATE group_session_participants SET last_seen_at = NOW() WHERE session_id = ? AND user_id = ?")
        ->execute([$sessionId, $userId]);
    gsTouch($pdo, $sessionId);
    gsRevealIfEveryoneIn($pdo, $session, $roundId);
    echo json_encode(['success' => true]);
}

/** Reveal the open round once everyone who's here has locked in. */
function gsRevealIfEveryoneIn(PDO $pdo, array $session, ?int $roundId): void {
    $round = gsOpenRound($pdo, (int) $session['id']);
    if (!$round || ($roundId !== null && (int) $round['id'] !== $roundId)) return;
    $participants = gsParticipants($pdo, (int) $session['id']);
    $stmt = $pdo->prepare("SELECT user_id FROM group_round_answers WHERE round_id = ?");
    $stmt->execute([(int) $round['id']]);
    $answered = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    if (!$answered) return;
    $here = array_keys(array_filter($participants, fn($p) => !$p['left'] && $p['online']));
    if (array_diff($here, $answered)) return;
    gsRoundReveal($pdo, $session, (int) $round['id'], $participants);
}

function gsRoundRevealAction(PDO $pdo, int $userId, array $data): void {
    $sessionId = (int) ($data['session_id'] ?? 0);
    $roundId = (int) ($data['round_id'] ?? 0);
    if (!($m = gsRequireMember($pdo, $userId, $sessionId, true))) return;
    [$session, $participants] = $m;
    if ($userId !== (int) $session['host_user_id']) { gsFail(403, 'Only the host can reveal early.'); return; }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM group_round_answers WHERE round_id = ?");
    $stmt->execute([$roundId]);
    if ((int) $stmt->fetchColumn() === 0) { gsFail(409, 'Wait for at least one answer.'); return; }
    gsRoundReveal($pdo, $session, $roundId, $participants);
    echo json_encode(['success' => true]);
}

/** Flip a round over (once, whoever gets there first), then Q picks up the split:
 *  it calls on someone who went with the most popular wrong answer (the quietest
 *  of them) to talk through their thinking. A wrong answer is the most useful
 *  thing in the room, so that's who gets asked. Lays a stone on the room's bridge. */
function gsRoundReveal(PDO $pdo, array $session, int $roundId, array $participants): void {
    $sessionId = (int) $session['id'];
    $flip = $pdo->prepare("UPDATE group_rounds SET status = 'revealed', revealed_at = NOW() WHERE id = ? AND session_id = ? AND status = 'open'");
    $flip->execute([$roundId, $sessionId]);
    if ($flip->rowCount() === 0) return;

    $stmt = $pdo->prepare("SELECT source, options, correct_index FROM group_rounds WHERE id = ?");
    $stmt->execute([$roundId]);
    $round = $stmt->fetch();
    $options = json_decode($round['options'], true) ?: [];
    $correct = (int) $round['correct_index'];
    $stmt = $pdo->prepare("SELECT user_id, choice FROM group_round_answers WHERE round_id = ?");
    $stmt->execute([$roundId]);
    $answers = $stmt->fetchAll();

    $total = count($answers);
    $right = count(array_filter($answers, fn($a) => (int) $a['choice'] === $correct));
    $rightText = $options[$correct] ?? '';
    $quietest = function (array $userIds) use ($participants) {
        $pool = array_values(array_filter(array_map(fn($id) => $participants[$id] ?? null, $userIds),
            fn($p) => $p && !$p['left']));
        if (!$pool) return null;
        usort($pool, fn($a, $b) => [!$a['online'], $a['said'], $a['last_said_id']] <=> [!$b['online'], $b['said'], $b['last_said_id']]);
        return $pool[0];
    };

    $wrong = array_filter($answers, fn($a) => (int) $a['choice'] !== $correct);
    $callOn = null;
    if ($wrong) {
        $byChoice = [];
        foreach ($wrong as $a) $byChoice[(int) $a['choice']][] = (int) $a['user_id'];
        uasort($byChoice, fn($a, $b) => count($b) <=> count($a));
        $popular = array_key_first($byChoice);
        $callOn = $quietest($byChoice[$popular]);
        $split = $right === 0 ? "Tricky one."
            : ($right === 1 && $total > 1 ? "1 of {$total} got it." : "{$right} of {$total} got it.");
        $text = "{$split} The answer is **{$rightText}**."
            . ($callOn ? " " . gsFirstName($callOn['display_name']) . ", you went with \"{$options[$popular]}\". Talk us through how you got there?" : '');
    } else {
        $callOn = $total > 1 ? $quietest(array_map(fn($a) => (int) $a['user_id'], $answers)) : null;
        $text = ($total > 1 ? "Everyone got it: **{$rightText}**." : "Got it: **{$rightText}**.")
            . ($callOn ? " " . gsFirstName($callOn['display_name']) . ", in one line, why is that the answer?" : '');
    }

    $stmt = $pdo->prepare("SELECT id FROM group_session_turns WHERE session_id = ? AND status != 'completed' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$sessionId]);
    $pdo->prepare("INSERT INTO group_session_messages (session_id, turn_id, sender_type, content, addressed_user_id) VALUES (?, ?, 'ai', ?, ?)")
        ->execute([$sessionId, $stmt->fetchColumn() ?: null, $text, $callOn['user_id'] ?? null]);

    $bridge = gsBridge($pdo, $sessionId);
    if ($bridge['stones'] === 0 && $bridge['built'] > 0) {
        gsSystem($pdo, $sessionId, "The room finished a bridge: " . GS_BRIDGE_STONES . " rounds settled together.");
    }
    if ($round['source'] === 'timer') {
        gsSystem($pdo, $sessionId, "Focus session done. Take a 5-minute break if you need one. The room will be here.");
    }
    gsTouch($pdo, $sessionId);
}

/** The room's bridge: one stone per revealed round, GS_BRIDGE_STONES to a bridge. */
function gsBridge(PDO $pdo, int $sessionId): array {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM group_rounds WHERE session_id = ? AND status = 'revealed'");
    $stmt->execute([$sessionId]);
    $n = (int) $stmt->fetchColumn();
    return ['stones' => $n % GS_BRIDGE_STONES, 'built' => intdiv($n, GS_BRIDGE_STONES), 'per_bridge' => GS_BRIDGE_STONES];
}

/** Every round in the room, as this user may see it: while a round is open you
 *  see WHO has locked in and your own answer, never anyone else's or the right
 *  one. After the reveal, everything. */
function gsRoundsFor(PDO $pdo, int $sessionId, int $userId): array {
    $stmt = $pdo->prepare("SELECT id, source, question, options, correct_index, explanation, status,
        TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age FROM group_rounds WHERE session_id = ? ORDER BY id");
    $stmt->execute([$sessionId]);
    $rounds = $stmt->fetchAll();
    if (!$rounds) return [];
    $ids = array_map(fn($r) => (int) $r['id'], $rounds);
    $stmt = $pdo->prepare("SELECT round_id, user_id, choice FROM group_round_answers WHERE round_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ") ORDER BY locked_at");
    $stmt->execute($ids);
    $answers = [];
    foreach ($stmt->fetchAll() as $a) $answers[(int) $a['round_id']][] = ['user_id' => (int) $a['user_id'], 'choice' => (int) $a['choice']];

    return array_map(function ($r) use ($answers, $userId) {
        $a = $answers[(int) $r['id']] ?? [];
        $mine = array_values(array_filter($a, fn($x) => $x['user_id'] === $userId));
        $out = [
            'id'        => (int) $r['id'],
            'source'    => $r['source'],
            'question'  => $r['question'],
            'options'   => json_decode($r['options'], true) ?: [],
            'status'    => $r['status'],
            'age'       => max(0, (int) $r['age']),
            'timeout'   => GS_ROUND_TIMEOUT_SECONDS,
            'locked'    => array_map(fn($x) => $x['user_id'], $a),
            'my_choice' => $mine ? $mine[0]['choice'] : null,
        ];
        if ($r['status'] === 'revealed') {
            $out['answers'] = $a;
            $out['correct'] = (int) $r['correct_index'];
            $out['explanation'] = $r['explanation'];
        }
        return $out;
    }, $rounds);
}

// --------------------------------------------------------------------------
// Reactions
// --------------------------------------------------------------------------

/** { message_id: { kind: [count, reacted_by_me] } } for the whole room. */
function gsReactionsFor(PDO $pdo, int $sessionId, int $userId): object {
    $stmt = $pdo->prepare("SELECT r.message_id, r.kind, COUNT(*) AS n, MAX(r.user_id = ?) AS mine
        FROM group_message_reactions r JOIN group_session_messages m ON m.id = r.message_id
        WHERE m.session_id = ? GROUP BY r.message_id, r.kind");
    $stmt->execute([$userId, $sessionId]);
    $out = [];
    foreach ($stmt->fetchAll() as $r) $out[(int) $r['message_id']][$r['kind']] = [(int) $r['n'], (bool) $r['mine']];
    return (object) $out;   // JSON object even when empty
}

function gsReact(PDO $pdo, int $userId, array $data): void {
    $sessionId = (int) ($data['session_id'] ?? 0);
    $messageId = (int) ($data['message_id'] ?? 0);
    $kind = (string) ($data['kind'] ?? '');
    if (!in_array($kind, GS_REACTIONS, true)) { gsFail(400, 'Unknown reaction.'); return; }
    if (!($m = gsRequireMember($pdo, $userId, $sessionId))) return;
    if ($m[0]['status'] === 'completed') { gsFail(409, 'This session has ended.'); return; }

    $stmt = $pdo->prepare("SELECT sender_type, student_user_id FROM group_session_messages WHERE id = ? AND session_id = ?");
    $stmt->execute([$messageId, $sessionId]);
    $msg = $stmt->fetch();
    if (!$msg || $msg['sender_type'] === 'system') { gsFail(404, 'Nothing to react to.'); return; }
    if ($msg['sender_type'] === 'student' && (int) $msg['student_user_id'] === $userId) { gsFail(400, "You can't react to your own message."); return; }

    // Toggle
    $del = $pdo->prepare("DELETE FROM group_message_reactions WHERE message_id = ? AND user_id = ? AND kind = ?");
    $del->execute([$messageId, $userId, $kind]);
    if ($del->rowCount() === 0) {
        $pdo->prepare("INSERT IGNORE INTO group_message_reactions (message_id, user_id, kind) VALUES (?, ?, ?)")
            ->execute([$messageId, $userId, $kind]);
    }
    echo json_encode(['success' => true, 'on' => $del->rowCount() === 0]);
}

// --------------------------------------------------------------------------
// Focus sessions
// --------------------------------------------------------------------------

function gsFocusStart(PDO $pdo, int $userId, array $data): void {
    $sessionId = (int) ($data['session_id'] ?? 0);
    $minutes = (int) ($data['minutes'] ?? 0);
    if (!in_array($minutes, GS_FOCUS_MINUTES, true)) { gsFail(400, 'Pick a focus length.'); return; }
    if (!($m = gsRequireMember($pdo, $userId, $sessionId, true))) return;
    [$session, $participants] = $m;
    if ($userId !== (int) $session['host_user_id']) { gsFail(403, 'Only the host can start a focus session.'); return; }
    if ($session['focus_left'] !== null) { gsFail(409, 'A focus session is already running.'); return; }

    $pdo->prepare("UPDATE group_sessions SET focus_ends_at = NOW() + INTERVAL ? MINUTE, focus_minutes = ? WHERE id = ?")
        ->execute([$minutes, $minutes, $sessionId]);
    gsSystem($pdo, $sessionId, gsFirstName($participants[$userId]['display_name'])
        . " started a {$minutes}-minute focus session. When it ends, everyone gets a recall round.");
    echo json_encode(['success' => true]);
}

function gsFocusStop(PDO $pdo, int $userId, array $data): void {
    $sessionId = (int) ($data['session_id'] ?? 0);
    if (!($m = gsRequireMember($pdo, $userId, $sessionId, true))) return;
    [$session, $participants] = $m;
    if ($userId !== (int) $session['host_user_id']) { gsFail(403, 'Only the host can stop the focus session.'); return; }
    $stop = $pdo->prepare("UPDATE group_sessions SET focus_ends_at = NULL WHERE id = ? AND focus_ends_at IS NOT NULL");
    $stop->execute([$sessionId]);
    if ($stop->rowCount() > 0) gsSystem($pdo, $sessionId, gsFirstName($participants[$userId]['display_name']) . " stopped the focus session.");
    echo json_encode(['success' => true]);
}
