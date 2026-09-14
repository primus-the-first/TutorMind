<?php
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_once 'includes/check_auth.php';

$displayName = isset($_SESSION['first_name']) && !empty($_SESSION['first_name'])
    ? $_SESSION['first_name']
    : (isset($_SESSION['username']) ? $_SESSION['username'] : 'User');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Study - TutorMind</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon-new.svg">
    <link rel="icon" type="image/png" href="assets/icons/icon-512.png">
    <link rel="apple-touch-icon" href="assets/icons/icon-512.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Source+Sans+Pro:wght@400;600;700&family=Funnel+Display:wght@400;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/ui-overhaul.css?v=<?= filemtime('assets/css/ui-overhaul.css') ?>">
    <link rel="stylesheet" href="assets/css/tm-widgets.css?v=<?= filemtime('assets/css/tm-widgets.css') ?>">
    <link rel="stylesheet" href="assets/css/group-study.css?v=<?= filemtime('assets/css/group-study.css') ?>">
    <link rel="stylesheet" href="assets/css/tm-loader.css?v=<?= filemtime('assets/css/tm-loader.css') ?>">
    <script src="assets/js/tm-loader.js?v=<?= filemtime('assets/js/tm-loader.js') ?>"></script>
</head>
<body data-display-name="<?= htmlspecialchars($displayName) ?>">
    <div class="gs-page">
        <header class="gs-header">
            <a href="tutor_mysql.php" class="gs-back" aria-label="Back to chat">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
            <div class="gs-header-title">
                <span class="gs-header-icon" aria-hidden="true"></span>
                <h1>Group Study</h1>
            </div>
            <button type="button" id="darkModeToggle" class="gs-theme-toggle" aria-label="Toggle dark mode">
                <i class="fas fa-moon"></i>
            </button>
        </header>

        <main class="gs-main">
            <!-- Landing: create or join -->
            <section id="gsLanding" class="gs-panel">
                <p class="gs-tagline">One of you teaches, the group works through it together — the AI only steps in to point at gaps, never to hand you the answer.</p>

                <div class="gs-landing-grid">
                    <div class="gs-card">
                        <h2>Start a session</h2>
                        <p>Pick a topic. You'll teach it first — everyone else joins with a code.</p>
                        <label for="gsTopicInput" class="gs-label">Topic</label>
                        <input type="text" id="gsTopicInput" class="gs-input" placeholder="e.g. Photosynthesis, Binary search, The French Revolution" maxlength="255">
                        <button type="button" id="gsCreateBtn" class="gs-btn gs-btn-primary">Create session</button>
                    </div>
                    <div class="gs-card">
                        <h2>Join a session</h2>
                        <p>Enter the 6-character code someone shared with you.</p>
                        <label for="gsJoinCodeInput" class="gs-label">Join code</label>
                        <input type="text" id="gsJoinCodeInput" class="gs-input gs-input-code" placeholder="AB2XQ9" maxlength="6" autocapitalize="characters">
                        <button type="button" id="gsJoinBtn" class="gs-btn">Join session</button>
                    </div>
                </div>
                <p id="gsLandingError" class="gs-error" hidden></p>
            </section>

            <!-- Waiting room: created but not enough participants yet -->
            <section id="gsWaiting" class="gs-panel" hidden>
                <div class="gs-waiting-card">
                    <p class="gs-waiting-label">Share this code with your study group</p>
                    <div class="gs-join-code" id="gsWaitingCode">------</div>
                    <p class="gs-waiting-hint">Waiting for at least one more person to join before you can start teaching "<span id="gsWaitingTopic"></span>"...</p>
                    <button type="button" class="gs-exit-btn" data-gs-exit>Exit room</button>
                </div>
            </section>

            <!-- Active session -->
            <section id="gsSession" class="gs-panel gs-session" hidden>
                <div class="gs-session-meta">
                    <div>
                        <h2 id="gsSessionTopic"></h2>
                        <p class="gs-session-teacher">Teaching now: <strong id="gsCurrentTeacher"></strong></p>
                    </div>
                    <div class="gs-session-meta-right">
                        <div class="gs-participants" id="gsParticipants"></div>
                        <button type="button" class="gs-exit-btn" data-gs-exit>Exit room</button>
                    </div>
                </div>

                <div class="gs-transcript" id="gsTranscript"></div>

                <div class="gs-composer" id="gsComposer">
                    <textarea id="gsMessageInput" class="gs-message-input" placeholder="Explain it in your own words, add to what's been said, or challenge it..." rows="2"></textarea>
                    <button type="button" id="gsSendBtn" class="gs-btn gs-btn-primary">Send</button>
                </div>
                <p class="gs-composer-hint" id="gsComposerHint" hidden>This session has ended.</p>
            </section>
        </main>
    </div>

    <script src="assets/js/tm-widgets.js?v=<?= filemtime('assets/js/tm-widgets.js') ?>"></script>
    <script src="assets/js/group-study.js?v=<?= filemtime('assets/js/group-study.js') ?>"></script>
    <script>
        document.getElementById('darkModeToggle').addEventListener('click', function () {
            document.body.classList.toggle('dark-mode');
            try { localStorage.setItem('darkMode', document.body.classList.contains('dark-mode') ? '1' : '0'); } catch (e) {}
        });
        try { if (localStorage.getItem('darkMode') === '1') document.body.classList.add('dark-mode'); } catch (e) {}
    </script>
</body>
</html>
