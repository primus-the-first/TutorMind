<?php
// Unsubscribe from study-reminder emails — no login needed.
//   GET  ?u=ID&t=TOKEN  → confirm page (mail scanners open links; a GET must not unsubscribe)
//   POST ?u=ID&t=TOKEN  → unsubscribes. Also what the inbox's own "Unsubscribe" button sends
//                         (RFC 8058 one-click: List-Unsubscribe-Post header on reminder emails).
// The token is users.email_token (migration 019), compared in constant time.
require_once 'includes/db_mysql.php';
header("Cache-Control: no-store");
header("Referrer-Policy: no-referrer");

$uid = (int)($_GET['u'] ?? $_POST['u'] ?? 0);
$token = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$state = 'invalid';   // invalid | confirm | done

try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT email, email_token, email_reminders FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $valid = $user && !empty($user['email_token']) && preg_match('/^[a-f0-9]{32}$/', $token) && hash_equals($user['email_token'], $token);

    if ($valid) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $pdo->prepare("UPDATE users SET email_reminders = 0 WHERE id = ?")->execute([$uid]);
            $state = 'done';
            // One-click from a mail client: it only needs a 2xx, not a page
            if (($_POST['List-Unsubscribe'] ?? '') === 'One-Click') { http_response_code(200); echo 'Unsubscribed'; exit; }
        } else {
            $state = $user['email_reminders'] ? 'confirm' : 'done';
        }
    }
} catch (Throwable $e) {
    error_log('Unsubscribe error: ' . $e->getMessage());
    $state = 'error';
}
if ($state === 'invalid') http_response_code(404);

$v = fn($f) => filemtime($f);
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
$email = $user['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Email reminders — TutorMind</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon-new.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Funnel+Display:wght@600;700&family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/tm-tokens.css?v=<?= $v('assets/css/tm-tokens.css') ?>">
    <link rel="stylesheet" href="assets/css/tm-ds.css?v=<?= $v('assets/css/tm-ds.css') ?>">
    <style>
        .un-wrap { min-height: 100vh; display: grid; place-items: center; padding: 24px 16px; }
        .un-card { width: 100%; max-width: 460px; display: grid; gap: 16px; padding: 32px; background: var(--ds-surface); border: 1px solid var(--ds-line); border-radius: var(--ds-r-xl); box-shadow: var(--ds-lift); }
        .un-card h1 { margin: 0; font: 700 1.6rem/1.2 var(--ds-font-display); letter-spacing: -0.02em; }
        .un-card p { margin: 0; color: var(--ds-ink-2); }
        .un-card form { margin: 4px 0 0; }
        .un-card a.ds-textlink { color: var(--ds-accent-ink); } /* out-rank .ds-page a { color: inherit } */
        .un-arch { width: 44px; height: 44px; }
        .un-arch path { fill: none; stroke: var(--ds-accent-ink); stroke-width: 4; stroke-linecap: round; }
        .un-arch circle { fill: var(--cta); }
    </style>
</head>
<body class="ds-page">
    <script>
        (function () { var t = null; try { t = localStorage.getItem('tutormind-theme'); } catch (e) {} if (t === 'dark') document.body.classList.add('dark-mode'); })();
    </script>
    <main class="un-wrap">
        <div class="un-card">
            <svg class="un-arch" viewBox="0 0 40 40" aria-hidden="true"><path d="M6 30 C 6 20, 14 12, 20 12 C 26 12, 34 20, 34 30"/><circle cx="20" cy="8" r="3"/></svg>
<?php if ($state === 'confirm'): ?>
            <h1>Stop study-reminder emails?</h1>
            <p>We'll stop emailing reminders to <strong><?= $e($email) ?></strong>. Push notifications on your devices aren't affected.</p>
            <form method="post" action="unsubscribe?u=<?= $uid ?>&amp;t=<?= $e($token) ?>">
                <button type="submit" class="ds-btn ds-btn--primary ds-btn--block">Unsubscribe</button>
            </form>
            <p><a class="ds-textlink" href="chat">Keep them coming</a></p>
<?php elseif ($state === 'done'): ?>
            <h1>You're unsubscribed</h1>
            <p>No more study-reminder emails to <strong><?= $e($email) ?></strong>. You can switch them back on any time in Settings → Notifications.</p>
            <p><a class="ds-textlink" href="chat">Back to TutorMind</a></p>
<?php elseif ($state === 'error'): ?>
            <h1>Something went wrong</h1>
            <p>We couldn't update your email settings just now. Please try again, or turn reminders off in Settings → Notifications.</p>
<?php else: ?>
            <h1>This link isn't valid</h1>
            <p>It may be incomplete. You can turn reminder emails off in Settings → Notifications after logging in.</p>
            <p><a class="ds-textlink" href="login">Log in</a></p>
<?php endif; ?>
        </div>
    </main>
</body>
</html>
