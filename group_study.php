<?php
// Group study — a study chat room with an AI facilitator ("Q").
// Design system page (tm-tokens + tm-ds, like login/register) + group-study.css.
// Behaviour: assets/js/group-study.js · API: api/group_study.php
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_once 'includes/check_auth.php';

$firstName = !empty($_SESSION['first_name']) ? $_SESSION['first_name'] : ($_SESSION['username'] ?? 'there');
$v = fn($f) => filemtime($f);
?>
<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Group study — TutorMind</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon-new.svg">
    <link rel="icon" type="image/png" href="assets/icons/icon-512.png">
    <link rel="apple-touch-icon" href="assets/icons/icon-512.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Funnel+Display:wght@600;700&family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/tm-tokens.css?v=<?= $v('assets/css/tm-tokens.css') ?>">
    <link rel="stylesheet" href="assets/css/tm-ds.css?v=<?= $v('assets/css/tm-ds.css') ?>">
    <link rel="stylesheet" href="assets/css/group-study.css?v=<?= $v('assets/css/group-study.css') ?>">
</head>
<body class="ds-page gs-page">
    <script>
        // Same theme key and fallbacks as the rest of the app
        (function () {
            var theme = null;
            try { theme = localStorage.getItem('tutormind-theme') || (localStorage.getItem('darkMode') === 'enabled' ? 'dark' : null); } catch (e) {}
            if (theme === 'dark') document.body.classList.add('dark-mode');
        })();
    </script>

    <?php $appNavCurrent = 'group'; include __DIR__ . '/includes/app_nav.php'; ?>

    <!-- ================= Lobby ================= -->
    <main id="gsLobby" class="gs-lobby">
        <div class="gs-lobby__intro">
            <span class="ds-kicker">Hi, <?= htmlspecialchars($firstName) ?></span>
            <h1 class="ds-h2">Teach it to <span class="ds-accent">each other.</span></h1>
            <p class="ds-lede">One of you explains, the room talks it through, and Q points at gaps. It'll ask the quiet ones what they think, and it never just hands you the answer.</p>
        </div>

        <div class="gs-lobby__cards">
            <section class="gs-card" id="gsCreateCard" aria-labelledby="gsCreateTitle">
                <div class="gs-card__head">
                    <span class="gs-card__icon" aria-hidden="true"><svg class="ds-i"><use href="#i-plus"/></svg></span>
                    <div class="gs-card__text">
                        <h2 class="gs-card__title" id="gsCreateTitle">Start a room</h2>
                        <span class="gs-card__desc">Pick a topic. You teach first, everyone else joins with a code.</span>
                    </div>
                </div>
                <form class="gs-card__form" id="gsCreateForm" novalidate>
                    <div class="ds-field">
                        <label for="gsTopicInput" class="ds-label">Topic</label>
                        <input type="text" id="gsTopicInput" class="ds-input" placeholder="e.g. Photosynthesis, binary search" maxlength="255" autocomplete="off">
                    </div>
                    <button type="submit" id="gsCreateBtn" class="ds-btn ds-btn--primary ds-btn--block">Open the room <svg class="ds-i ds-i-arrow"><use href="#i-arrow"/></svg></button>
                </form>
            </section>

            <section class="gs-card" id="gsJoinCard" aria-labelledby="gsJoinTitle">
                <div class="gs-card__head">
                    <span class="gs-card__icon" aria-hidden="true"><svg class="ds-i"><use href="#i-users"/></svg></span>
                    <div class="gs-card__text">
                        <h2 class="gs-card__title" id="gsJoinTitle">Join a room</h2>
                        <span class="gs-card__desc">Enter the 6-character code someone shared with you.</span>
                    </div>
                </div>
                <form class="gs-card__form" id="gsJoinForm" novalidate>
                    <div class="ds-field">
                        <label for="gsJoinCodeInput" class="ds-label">Join code</label>
                        <input type="text" id="gsJoinCodeInput" class="ds-input gs-code-input" placeholder="AB2XQ9" maxlength="6" autocomplete="off" autocapitalize="characters" spellcheck="false">
                    </div>
                    <button type="submit" id="gsJoinBtn" class="ds-btn ds-btn--secondary ds-btn--block">Join</button>
                </form>
            </section>
        </div>
        <div class="ds-alert" id="gsLobbyError" role="alert" hidden><svg class="ds-i"><use href="#i-alert"/></svg><span></span></div>

        <section id="gsRejoin" class="gs-rejoin" hidden aria-labelledby="gsRejoinTitle">
            <h2 id="gsRejoinTitle" class="gs-section-title">Your study rooms</h2>
            <p class="gs-section-sub">Rooms you're in from the last few hours.</p>
            <ul id="gsRejoinList" class="gs-rooms"></ul>
        </section>

        <section id="gsHistory" class="gs-history" hidden aria-labelledby="gsHistoryTitle">
            <h2 id="gsHistoryTitle" class="gs-section-title">Past rooms</h2>
            <p class="gs-section-sub">Read back what your group worked through.</p>
            <ul id="gsHistoryList" class="gs-history__list"></ul>
        </section>
    </main>

    <!-- ================= Room ================= -->
    <div id="gsRoom" class="gs-room" hidden>
        <div class="gs-room__bar">
            <div class="gs-room__heading">
                <h1 id="gsTopic" class="gs-room__topic"></h1>
                <p id="gsTeaching" class="gs-room__teaching"></p>
            </div>
            <div class="gs-room__actions">
                <button type="button" class="ds-icon-btn gs-people-toggle" id="gsPeopleToggle" aria-controls="gsPeople" aria-expanded="false" aria-label="People in the room">
                    <svg class="ds-i"><use href="#i-users"/></svg><span id="gsPeopleCount"></span>
                </button>
                <div class="gs-menu-wrap">
                    <button type="button" class="ds-btn ds-btn--tertiary ds-btn--sm" id="gsPassBtn" aria-haspopup="true" aria-expanded="false" hidden>Pass the turn</button>
                    <div class="gs-menu" id="gsPassMenu" role="menu" hidden></div>
                </div>
                <button type="button" class="ds-btn ds-btn--tertiary ds-btn--sm" id="gsEndBtn" hidden>End session</button>
                <button type="button" class="ds-icon-btn" id="gsLeaveBtn" aria-label="Leave the room" title="Leave the room"><svg class="ds-i"><use href="#i-leave"/></svg></button>
            </div>
        </div>
        <div class="gs-confirm" id="gsEndConfirm" role="alertdialog" aria-labelledby="gsEndConfirmText" hidden>
            <span id="gsEndConfirmText">End the session for everyone?</span>
            <button type="button" class="ds-btn ds-btn--primary ds-btn--sm" id="gsEndYes">End it</button>
            <button type="button" class="ds-btn ds-btn--tertiary ds-btn--sm" id="gsEndNo">Keep going</button>
        </div>

        <div class="gs-room__body">
            <div class="gs-room__main">
                <!-- Waiting for a second person -->
                <section id="gsInvite" class="gs-invite" hidden>
                    <h2 class="gs-invite__title">Get your group in</h2>
                    <p class="gs-invite__text">Share this code. The room opens as soon as one more person joins.</p>
                    <div class="gs-invite__code" id="gsInviteCode" aria-label="Join code"></div>
                    <div class="gs-invite__actions">
                        <button type="button" class="ds-btn ds-btn--primary ds-btn--sm" id="gsCopyCode"><svg class="ds-i"><use href="#i-copy"/></svg><span>Copy code</span></button>
                        <button type="button" class="ds-btn ds-btn--tertiary ds-btn--sm" id="gsCopyLink"><svg class="ds-i"><use href="#i-link"/></svg><span>Copy invite link</span></button>
                    </div>
                </section>

                <div class="gs-transcript" id="gsTranscript" role="log" aria-live="polite" aria-label="Room messages"></div>

                <div class="gs-foot">
                    <p class="gs-status" id="gsStatus" aria-live="polite"></p>
                    <div class="gs-asked" id="gsAsked" hidden>
                        <strong>Q asked you.</strong> Say what you think, in your own words. Getting it wrong is fine, the room will help.
                    </div>
                    <form class="gs-composer" id="gsComposer" novalidate>
                        <label for="gsInput" class="ds-visually-hidden">Message</label>
                        <textarea id="gsInput" class="gs-composer__input" rows="1" maxlength="2000" placeholder="Message the room…"></textarea>
                        <button type="button" class="gs-composer__tool" id="gsMicBtn" aria-label="Voice typing" aria-pressed="false"><svg class="ds-i"><use href="#i-mic"/></svg></button>
                        <button type="submit" class="gs-composer__send" id="gsSendBtn" aria-label="Send"><svg class="ds-i"><use href="#i-send"/></svg></button>
                    </form>
                    <p class="gs-hint" id="gsHint">Q replies to whoever is teaching and to anyone it asks. Mention <strong>@Q</strong> to ask it directly.</p>
                    <div class="gs-ended" id="gsEnded" hidden>
                        <p>This session has ended. Nice work, everyone.</p>
                        <button type="button" class="ds-btn ds-btn--primary ds-btn--sm" id="gsBackToLobby">Back to group study</button>
                    </div>
                </div>
            </div>

            <aside class="gs-people" id="gsPeople" aria-label="People">
                <h2 class="gs-section-title">In the room</h2>
                <ul class="gs-people__list" id="gsPeopleList"></ul>
                <div class="gs-people__code">
                    <span>Code</span>
                    <button type="button" class="gs-people__code-btn" id="gsSideCode" title="Copy code"></button>
                </div>
            </aside>
        </div>
    </div>

    <script src="assets/js/tm-ds.js?v=<?= $v('assets/js/tm-ds.js') ?>"></script>
    <script src="assets/js/group-study.js?v=<?= $v('assets/js/group-study.js') ?>"></script>
</body>
</html>
