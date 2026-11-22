<?php
// Verify email — the page the emailed link opens (includes/email_verification.php).
// Opening the link is the confirmation: the token is checked, used up and dropped
// from the address bar. Same layout as reset-password.php (tm-ds).
require_once __DIR__ . '/includes/session_boot.php';
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Referrer-Policy: no-referrer");
require_once __DIR__ . '/includes/db_mysql.php';
$v = fn($f) => filemtime($f);

// ok | used | invalid | error
$state = 'invalid';
$token = (string)($_GET['token'] ?? '');
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT id, user_id, used_at, expires_at > NOW() AS live FROM email_verifications WHERE token_hash = ?");
        $stmt->execute([hash('sha256', $token)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['used_at'] !== null) {
            $state = 'used';
        } elseif ($row && $row['live']) {
            $pdo->beginTransaction();
            // Claim the link first: of two clicks at once, only one gets the row
            $claim = $pdo->prepare("UPDATE email_verifications SET used_at = NOW() WHERE id = ? AND used_at IS NULL AND expires_at > NOW()");
            $claim->execute([$row['id']]);
            if ($claim->rowCount() === 1) {
                $pdo->prepare("UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?")->execute([$row['user_id']]);
                $pdo->commit();
                $state = 'ok';
            } else {
                $pdo->rollBack();
                $state = 'used';
            }
        }
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        error_log('Email verification error: ' . $e->getMessage());
        $state = 'error';
    }
}

$loggedIn = isset($_SESSION['user_id']);
$copy = [
    'ok'      => ['Email confirmed', 'Thanks, that address is now confirmed on your TutorMind account.'],
    'used'    => ['Link already used', 'This link was already used. If you confirmed your email, you are all set.'],
    'invalid' => ['Link expired', 'This link has expired or isn’t valid. Open Settings in TutorMind and change your email again to get a new one.'],
    'error'   => ['Something went wrong', 'We couldn’t check that link just now. Please try it again in a minute.'],
][$state];
?>
<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <meta name="referrer" content="no-referrer">
    <title>Confirm your email — TutorMind</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon-new.svg">
    <link rel="icon" type="image/png" href="assets/icons/icon-512.png">
    <link rel="apple-touch-icon" href="assets/icons/icon-512.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Funnel+Display:wght@600;700&family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/tm-tokens.css?v=<?= $v('assets/css/tm-tokens.css') ?>">
    <link rel="stylesheet" href="assets/css/tm-ds.css?v=<?= $v('assets/css/tm-ds.css') ?>">
    <link rel="stylesheet" href="assets/css/tm-loader.css?v=<?= $v('assets/css/tm-loader.css') ?>">
</head>
<body class="ds-page">
    <script>
        (function () {
            var theme = null;
            try { theme = localStorage.getItem('tutormind-theme') || (localStorage.getItem('darkMode') === 'enabled' ? 'dark' : null); } catch (e) {}
            if (theme === 'dark') document.body.classList.add('dark-mode');
            // Keep the token out of history, bookmarks and screenshots of the address bar
            history.replaceState({}, document.title, location.pathname);
        })();
    </script>

    <div class="ds-auth">
        <aside class="ds-auth__stage" aria-hidden="true">
            <a href="index" class="ds-logo" tabindex="-1"><img src="assets/logo-bridge.svg" alt="">TutorMind</a>
            <div class="ds-auth__intro">
                <p class="ds-auth__headline">One less thing<br>to <span class="ds-accent">worry about.</span></p>
                <p class="ds-lede">A confirmed email keeps your account safe and lets us reach you if you ever lose your password.</p>
            </div>
            <div class="ds-auth__visual" data-ds-scene="lock">
                <div class="ds-hero__fallback"><img src="assets/logo-bridge.svg" alt=""></div>
            </div>
        </aside>

        <main class="ds-auth__main">
            <div class="ds-auth__top">
                <a href="index" class="ds-logo ds-auth__mobile-logo"><img src="assets/logo-bridge.svg" alt="">TutorMind</a>
                <button class="ds-icon-btn" type="button" data-ds-theme aria-label="Dark mode"><svg class="ds-i"><use href="#i-moon"/></svg></button>
            </div>

            <div class="ds-auth__card">
                <header>
                    <h1 class="ds-h2"><?= htmlspecialchars($copy[0]) ?></h1>
                    <p class="ds-muted" style="margin:0"><?= htmlspecialchars($copy[1]) ?></p>
                </header>

                <a href="<?= $loggedIn ? 'chat' : 'login' ?>" class="ds-btn ds-btn--primary ds-btn--block"><?= $loggedIn ? 'Back to TutorMind' : 'Log in' ?> <svg class="ds-i ds-i-arrow"><use href="#i-arrow"/></svg></a>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/three@0.158.0/build/three.min.js"></script>
    <script src="assets/js/tm-ds.js?v=<?= $v('assets/js/tm-ds.js') ?>"></script>
    <?php if ($state === 'ok'): ?>
    <script>
        // The key turns once the address is confirmed
        window.addEventListener('load', function () {
            setTimeout(function () {
                var host = document.querySelector('[data-ds-scene="lock"]');
                if (host && host.tmScene) host.tmScene.grant();
            }, 400);
        });
    </script>
    <?php endif; ?>
</body>
</html>
