<?php
// "Your learning" — the learner dashboard.
// Design system page (tm-tokens + tm-ds, like group_study.php) + dashboard.css.
// Behaviour: assets/js/dashboard.js · Data: api/analytics.php
require_once 'includes/check_auth.php';
require_once 'includes/db_mysql.php';

$user_dark_mode = false;
if (!empty($_SESSION['user_id'])) {
    try {
        $stmt = getDbConnection()->prepare("SELECT dark_mode FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_dark_mode = (bool)$stmt->fetchColumn();
    } catch (Exception $e) {}
}

$firstName = !empty($_SESSION['first_name']) ? $_SESSION['first_name'] : ($_SESSION['username'] ?? '');
$v = fn($f) => filemtime($f);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Your learning — TutorMind</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon-new.svg">
    <link rel="icon" type="image/png" href="assets/icons/icon-512.png">
    <link rel="apple-touch-icon" href="assets/icons/icon-512.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Funnel+Display:wght@600;700&family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/tm-tokens.css?v=<?= $v('assets/css/tm-tokens.css') ?>">
    <link rel="stylesheet" href="assets/css/tm-ds.css?v=<?= $v('assets/css/tm-ds.css') ?>">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= $v('assets/css/dashboard.css') ?>">
</head>
<body class="ds-page db-page<?= $user_dark_mode ? ' dark-mode' : '' ?>">
    <script>
        // Same theme key and fallbacks as the rest of the app (the server already applied the saved setting)
        (function () {
            var theme = null;
            try { theme = localStorage.getItem('tutormind-theme') || (localStorage.getItem('darkMode') === 'enabled' ? 'dark' : null); } catch (e) {}
            if (theme === 'dark') document.body.classList.add('dark-mode');
        })();
    </script>

    <?php $appNavCurrent = 'dashboard'; include __DIR__ . '/includes/app_nav.php'; ?>

    <main class="ds-wrap db-main">
        <div class="db-hello">
            <h1 class="db-hello__title" id="dbGreeting" data-name="<?= htmlspecialchars($firstName) ?>">Hi<?= $firstName !== '' ? ', ' . htmlspecialchars($firstName) : '' ?></h1>
            <p class="db-hello__sub" id="dbHelloSub"></p>
        </div>

        <div id="dbContent" class="db-content" aria-busy="true">
            <div class="db-skeleton" aria-hidden="true">
                <div class="db-skel db-skel--band"></div>
                <div class="db-skel db-skel--card"></div>
                <div class="db-skel db-skel--card"></div>
            </div>
            <p class="ds-visually-hidden">Loading your learning…</p>
        </div>
    </main>

    <script src="assets/js/tm-ds.js?v=<?= $v('assets/js/tm-ds.js') ?>"></script>
    <script src="assets/js/dashboard.js?v=<?= $v('assets/js/dashboard.js') ?>"></script>
</body>
</html>
