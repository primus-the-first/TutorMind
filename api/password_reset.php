<?php
/**
 * Password reset by email (also how Google sign-ups add a password).
 *
 * POST action=request  email, csrf_token   → always the same answer, so it
 *                                              never reveals who has an account
 * GET  action=check    token               → { valid }  (lets the page say "expired" up front)
 * POST action=reset    token, password, csrf_token
 *
 * Tokens: 32 random bytes, emailed once; only sha256 is stored
 * (migrations/018_password_resets.php). 30 minutes, single use; a reset
 * invalidates every other outstanding link and "Remember me" token.
 */
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store');
require_once __DIR__ . '/../includes/db_mysql.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/rate_limiter.php';   // getClientIP()
require_once __DIR__ . '/../includes/mailer.php';

const RESET_TTL_MINUTES = 30;
const RESET_MAX_PER_ACCOUNT = 3;   // per 30 minutes — extra requests are silently not sent
const RESET_MAX_PER_IP = 10;       // per hour — then 429 (says nothing about any account)
const PASSWORD_MIN = 8;

function respond(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body);
    exit;
}

/**
 * Where the emailed link points. Never trust the Host header blindly: a forged
 * Host would put a working reset link to someone else's site in a real user's
 * inbox. Uses [app] base_url from config, else an allowlisted host.
 */
function appBaseUrl(): string
{
    foreach (['config-sql.ini', 'config.ini'] as $file) {
        $path = __DIR__ . '/../includes/' . $file;
        if (file_exists($path)) {
            $cfg = parse_ini_file($path, true);
            if (!empty($cfg['app']['base_url'])) return rtrim($cfg['app']['base_url'], '/');
            break;
        }
    }
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    $bare = preg_replace('/:\d+$/', '', $host);
    if (in_array($bare, ['tutormind.app', 'www.tutormind.app', 'localhost', '127.0.0.1'], true)) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $root = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/'); // api/ → app root
        return ($https ? 'https' : 'http') . "://$host$root";
    }
    return 'https://tutormind.app';
}

function findResetRow(PDO $pdo, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    $stmt = $pdo->prepare("
        SELECT pr.id, pr.user_id, u.email, u.username
        FROM password_resets pr JOIN users u ON u.id = pr.user_id
        WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()
    ");
    $stmt->execute([hash('sha256', $token)]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $pdo = getDbConnection();

    // ---------------------------------------------------------------- check
    if ($action === 'check' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        respond(200, ['success' => true, 'valid' => findResetRow($pdo, (string)($_GET['token'] ?? '')) !== null]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, ['success' => false, 'error' => 'Method not allowed.']);
    requireCSRFToken();

    // -------------------------------------------------------------- request
    if ($action === 'request') {
        $email = trim((string)($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            respond(400, ['success' => false, 'error' => 'Enter the email address you signed up with.']);
        }
        $ip = getClientIP();
        $generic = ['success' => true, 'message' => 'If an account uses that email, we have sent it a link to set a new password. The link works for ' . RESET_TTL_MINUTES . ' minutes.'];

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM password_resets WHERE requested_ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stmt->execute([$ip]);
        if ((int)$stmt->fetchColumn() >= RESET_MAX_PER_IP) {
            respond(429, ['success' => false, 'error' => 'Too many reset requests from this network. Please try again in an hour.']);
        }

        $stmt = $pdo->prepare("SELECT id, email, username, first_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) respond(200, $generic);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
        $stmt->execute([$user['id']]);
        if ((int)$stmt->fetchColumn() >= RESET_MAX_PER_ACCOUNT) respond(200, $generic);

        $token = bin2hex(random_bytes(32));
        $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")->execute([$user['id']]); // one live link at a time
        $pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at, requested_ip) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL " . RESET_TTL_MINUTES . " MINUTE), ?)")
            ->execute([$user['id'], hash('sha256', $token), $ip]);

        $link = appBaseUrl() . '/reset-password?token=' . $token;
        $name = $user['first_name'] ?: $user['username'];
        $safeName = htmlspecialchars($name, ENT_QUOTES);
        $safeLink = htmlspecialchars($link, ENT_QUOTES);
        $minutes = RESET_TTL_MINUTES;
        $html = <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;max-width:520px;margin:0 auto;color:#1F2937;line-height:1.55">
  <p style="font-size:20px;font-weight:700;margin:0 0 16px">Set a new password</p>
  <p>Hi {$safeName},</p>
  <p>Someone (hopefully you) asked to set a new password for your TutorMind account. If you usually sign in with Google, this lets you add a password too.</p>
  <p style="margin:24px 0"><a href="{$safeLink}" style="background:#7C3AED;color:#ffffff;padding:12px 20px;border-radius:10px;text-decoration:none;font-weight:700;display:inline-block">Set a new password</a></p>
  <p style="font-size:14px;color:#6b7280">The link works once, for {$minutes} minutes. If you did not ask for this, you can ignore this email; your password stays the same.</p>
  <p style="font-size:13px;color:#6b7280;word-break:break-all">If the button does not work, open this link: {$safeLink}</p>
</div>
HTML;
        $text = "Hi {$name},\n\nSomeone (hopefully you) asked to set a new password for your TutorMind account. "
              . "Open this link to choose one (it works once, for {$minutes} minutes):\n\n{$link}\n\n"
              . "If you did not ask for this, ignore this email; your password stays the same.\n";

        if (!sendEmail($user['email'], $name, 'Set a new TutorMind password', $html, $text)) {
            error_log("Password reset email failed for user {$user['id']}");
        }
        respond(200, $generic);
    }

    // ---------------------------------------------------------------- reset
    if ($action === 'reset') {
        $password = (string)($_POST['password'] ?? '');
        if (strlen($password) < PASSWORD_MIN) respond(400, ['success' => false, 'error' => 'Use at least ' . PASSWORD_MIN . ' characters.']);
        if (strlen($password) > 200) respond(400, ['success' => false, 'error' => 'That password is too long.']);

        $row = findResetRow($pdo, (string)($_POST['token'] ?? ''));
        if (!$row) respond(410, ['success' => false, 'error' => 'This link has expired or was already used. Ask for a new one.']);

        $pdo->beginTransaction();
        $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?")
            ->execute([password_hash($password, PASSWORD_ARGON2ID), $row['user_id']]);
        $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")->execute([$row['user_id']]);
        $pdo->prepare("DELETE FROM user_tokens WHERE user_id = ?")->execute([$row['user_id']]);                 // sign out "Remember me" devices
        $pdo->prepare("DELETE FROM login_attempts WHERE username IN (?, ?)")->execute([$row['email'], $row['username']]); // lift a lockout
        $pdo->commit();

        respond(200, ['success' => true, 'redirect' => 'login?reset=done']);
    }

    respond(400, ['success' => false, 'error' => 'Unknown action.']);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Password reset error: ' . $e->getMessage());
    respond(500, ['success' => false, 'error' => 'Something went wrong on our side. Please try again.']);
}
