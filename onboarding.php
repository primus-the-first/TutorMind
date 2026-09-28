<?php
// Onboarding — 2026-09 design system (tm-ds.css/js), same layout as register:
// the keystone arch on the stage keeps building from where register left off.
// Five steps, and every question asked here reaches the tutor's prompt.

// Force HTTPS redirect (skip on localhost for development)
$isLocalhost = in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1', '::1']);
if (!$isLocalhost && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')) {
    if (!headers_sent()) {
//         header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
        exit();
    }
}

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_once 'includes/check_auth.php'; // Secure this page
require_once 'includes/db_mysql.php';

$displayName = isset($_SESSION['first_name']) && !empty($_SESSION['first_name']) ? $_SESSION['first_name'] : (isset($_SESSION['username']) ? $_SESSION['username'] : 'there');
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Check if onboarding is already completed.
// Completed users are only bounced to chat when nothing is missing; if fields
// added after their original onboarding (e.g. interests) are still empty, they
// re-enter in "update mode" to fill in just those gaps.
$update_mode = false;
$existing_profile = null;
$user_dark_mode = false;
// Seeds for answers Settings may already hold, so onboarding never overwrites them with defaults
$saved_prefs = ['country' => null, 'responseStyle' => null];
if ($user_id) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT onboarding_completed, dark_mode, interests, profile_data, country, response_style FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $user_dark_mode = (bool)($user['dark_mode'] ?? false);
            $saved_prefs = ['country' => $user['country'] ?: null, 'responseStyle' => $user['response_style'] ?: null];
            if ($user['onboarding_completed']) {
                // NULL interests means the user never submitted an interests payload;
                // any valid JSON array (including []) means they did submit one.
                $saved_interests = json_decode($user['interests'] ?? 'null', true);
                if (is_array($saved_interests)) {
                    header('Location: chat');
                    exit;
                }
                $update_mode = true;
                $existing_profile = $user['profile_data'];
            }
        }
    } catch (Exception $e) {
        error_log("Onboarding check error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set up your tutor — TutorMind</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon-new.svg">
    <link rel="icon" type="image/png" href="assets/icons/icon-512.png">
    <link rel="apple-touch-icon" href="assets/icons/icon-512.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Funnel+Display:wght@600;700&family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/tm-tokens.css?v=<?= filemtime('assets/css/tm-tokens.css') ?>">
    <link rel="stylesheet" href="assets/css/tm-ds.css?v=<?= filemtime('assets/css/tm-ds.css') ?>">
    <link rel="stylesheet" href="assets/css/onboarding.css?v=<?= filemtime('assets/css/onboarding.css') ?>">

    <link rel="stylesheet" href="assets/css/tm-loader.css?v=<?= filemtime('assets/css/tm-loader.css') ?>">
    <script src="assets/js/tm-loader.js?v=<?= filemtime('assets/js/tm-loader.js') ?>"></script>
</head>
<body class="ds-page tm-onb<?= $user_dark_mode ? ' dark-mode' : '' ?>">
    <!-- Unified Theme Script (same fallbacks as register.php) -->
    <script>
        (function () {
            var theme = null, isDark = null;
            try {
                theme = localStorage.getItem('tutormind-theme');
                if (theme) isDark = theme === 'dark';
                else if (localStorage.getItem('darkMode') === 'enabled' || localStorage.getItem('theme') === 'dark') isDark = true;
            } catch (e) {}
            if (isDark !== null) document.body.classList.toggle('dark-mode', isDark);
        })();
    </script>

    <!-- Boot loader: shown for at least 2 full animation cycles, extended to
         cover real page-load time if that takes longer (same as chat/dashboard). -->
    <script>
        (function () {
            var bootStart = Date.now();
            if (typeof TmLoader === 'undefined') return;
            TmLoader.showFullscreen('Loading TutorMind…');
            window.addEventListener('load', function () {
                var minDuration = 2 * TmLoader.FULL_CYCLE_MS;
                var remaining = Math.max(0, minDuration - (Date.now() - bootStart));
                setTimeout(function () { TmLoader.hide(); }, remaining);
            });
        })();
    </script>

    <div class="ds-auth">
        <!-- Scene: register laid the first stones; each step here lays the next
             pair, and the amber keystone drops when the learner starts. -->
        <aside class="ds-auth__stage" aria-hidden="true">
            <a href="index" class="ds-logo" tabindex="-1"><img src="assets/logo-bridge.svg" alt="">TutorMind</a>
            <div class="ds-auth__intro">
                <p class="ds-auth__headline">Finish your<br><span class="ds-accent">bridge.</span></p>
                <p class="ds-lede">Five quick steps. Each one lays a stone, and your tutor learns a little more about how to teach you.</p>
            </div>
            <div class="ds-auth__visual" data-ds-scene="keystone">
                <div class="ds-hero__fallback"><img src="assets/logo-bridge.svg" alt=""></div>
            </div>
        </aside>

        <main class="ds-auth__main">
            <div class="ds-auth__top">
                <a href="index" class="ds-logo ds-auth__mobile-logo"><img src="assets/logo-bridge.svg" alt="">TutorMind</a>
                <button class="ds-icon-btn" type="button" data-ds-theme aria-label="Dark mode"><svg class="ds-i"><use href="#i-moon"/></svg></button>
            </div>

            <div class="ds-auth__card onb-card" id="onb-card">
                <div class="onb-progress" id="onb-progress">
                    <p class="onb-progress__label" id="onb-progress-label">Step 1 of 5</p>
                    <ol class="onb-progress__bar" aria-hidden="true">
                        <li></li><li></li><li></li><li></li><li></li>
                    </ol>
                </div>

                <!-- ============ 1. About you ============ -->
                <section class="onb-step" data-step="1" aria-labelledby="s1-title">
                    <header class="onb-head">
                        <h1 class="onb-title" id="s1-title">Hi <?= htmlspecialchars($displayName) ?>, let’s set up your tutor</h1>
                        <p class="ds-muted">You can change any of this later in Settings.</p>
                    </header>

                    <fieldset class="onb-group">
                        <legend class="ds-label">Where are you in your studies?</legend>
                        <div class="ds-chip-row" data-single="educationLevel">
                            <button type="button" class="ds-chip" data-value="high" aria-pressed="false">High school (SHS)</button>
                            <button type="button" class="ds-chip" data-value="college" aria-pressed="false">University</button>
                            <button type="button" class="ds-chip" data-value="adult" aria-pressed="false">Working or adult learner</button>
                            <button type="button" class="ds-chip" data-value="other" aria-pressed="false">Something else</button>
                        </div>
                    </fieldset>

                    <fieldset class="onb-group" id="enrollment-group" hidden>
                        <legend class="ds-label">At university, are you…</legend>
                        <div class="ds-chip-row" data-single="enrollmentStatus">
                            <button type="button" class="ds-chip" data-value="enrolled" aria-pressed="false">Studying now</button>
                            <button type="button" class="ds-chip" data-value="graduated" aria-pressed="false">Graduated</button>
                        </div>
                    </fieldset>

                    <div class="ds-field" id="school-field" hidden>
                        <label for="school-input" class="ds-label" id="school-label">Your school</label>
                        <input type="text" id="school-input" class="ds-input" autocomplete="organization" placeholder="Optional">
                        <datalist id="university-list"></datalist>
                    </div>

                    <div class="ds-field">
                        <label for="country-input" class="ds-label">Which country are you studying in?</label>
                        <input type="text" id="country-input" class="ds-input" list="country-list" autocomplete="country-name" placeholder="e.g. Ghana">
                        <datalist id="country-list"></datalist>
                        <p class="ds-field__hint">Your tutor uses it for local examples, currency and spelling.</p>
                    </div>

                    <p class="ds-field__error" id="err-1" role="alert"></p>
                    <div class="onb-nav">
                        <button type="button" class="ds-btn ds-btn--primary ds-btn--block" data-next>Continue <svg class="ds-i ds-i-arrow"><use href="#i-arrow"/></svg></button>
                    </div>
                </section>

                <!-- ============ 2. Subjects ============ -->
                <section class="onb-step" data-step="2" aria-labelledby="s2-title" hidden>
                    <header class="onb-head">
                        <h1 class="onb-title" id="s2-title">What are you studying?</h1>
                        <p class="ds-muted" id="s2-sub">Pick what you’d like help with. You can ask about anything else too.</p>
                    </header>

                    <!-- SHS: programme, then its electives -->
                    <div class="onb-branch" data-branch="high" hidden>
                        <fieldset class="onb-group">
                            <legend class="ds-label">Your programme</legend>
                            <div class="ds-chip-row" id="shs-programs"></div>
                        </fieldset>
                        <fieldset class="onb-group" id="shs-electives-group" hidden>
                            <legend class="ds-label">Your electives</legend>
                            <div class="ds-chip-row" id="shs-electives"></div>
                            <p class="ds-field__hint">Everyone also takes English, Core Maths, Integrated Science and Social Studies. Ask about those anytime.</p>
                        </fieldset>
                    </div>

                    <!-- University: programme + free-text subjects -->
                    <div class="onb-branch" data-branch="college" hidden>
                        <div class="ds-field">
                            <label for="uni-program" class="ds-label">Your programme</label>
                            <input type="text" id="uni-program" class="ds-input" placeholder="e.g. BSc Computer Science (optional)">
                        </div>
                        <div class="ds-field">
                            <label for="uni-subject" class="ds-label" id="uni-subject-label">Courses you want help with</label>
                            <div class="onb-add">
                                <input type="text" id="uni-subject" class="ds-input" placeholder="e.g. Calculus II">
                                <button type="button" class="ds-btn ds-btn--secondary ds-btn--sm" data-add="uni-subject">Add</button>
                            </div>
                            <ul class="onb-tags" data-tags="customSubjects" aria-label="Courses added"></ul>
                        </div>
                    </div>

                    <!-- Everyone else: broad subjects + anything specific -->
                    <div class="onb-branch" data-branch="general" hidden>
                        <fieldset class="onb-group">
                            <legend class="ds-label">Subjects</legend>
                            <div class="ds-chip-row" data-multi="subjects">
                                <button type="button" class="ds-chip" data-value="mathematics" aria-pressed="false">Mathematics</button>
                                <button type="button" class="ds-chip" data-value="science" aria-pressed="false">Science</button>
                                <button type="button" class="ds-chip" data-value="languages" aria-pressed="false">Languages and writing</button>
                                <button type="button" class="ds-chip" data-value="computer-science" aria-pressed="false">Computer science</button>
                                <button type="button" class="ds-chip" data-value="social-studies" aria-pressed="false">Social studies</button>
                                <button type="button" class="ds-chip" data-value="business" aria-pressed="false">Business and finance</button>
                            </div>
                        </fieldset>
                        <div class="ds-field">
                            <label for="gen-subject" class="ds-label">Anything more specific?</label>
                            <div class="onb-add">
                                <input type="text" id="gen-subject" class="ds-input" placeholder="e.g. Excel, IELTS, Statistics">
                                <button type="button" class="ds-btn ds-btn--secondary ds-btn--sm" data-add="gen-subject">Add</button>
                            </div>
                            <ul class="onb-tags" data-tags="customSubjects" aria-label="Topics added"></ul>
                        </div>
                    </div>

                    <p class="ds-field__error" id="err-2" role="alert"></p>
                    <div class="onb-nav">
                        <button type="button" class="ds-btn ds-btn--tertiary" data-back>Back</button>
                        <button type="button" class="ds-btn ds-btn--primary" data-next>Continue <svg class="ds-i ds-i-arrow"><use href="#i-arrow"/></svg></button>
                    </div>
                </section>

                <!-- ============ 3. Goal and you ============ -->
                <section class="onb-step" data-step="3" aria-labelledby="s3-title" hidden>
                    <header class="onb-head">
                        <h1 class="onb-title" id="s3-title">What do you want from your tutor?</h1>
                        <p class="ds-muted" id="s3-sub">This shapes how your tutor starts each conversation.</p>
                    </header>

                    <fieldset class="onb-group">
                        <legend class="ds-label">Mostly, I want help with…</legend>
                        <div class="ds-chip-row" data-single="learningGoal">
                            <button type="button" class="ds-chip" data-value="homework_help" aria-pressed="false">Homework</button>
                            <button type="button" class="ds-chip" data-value="exam_prep" aria-pressed="false">Exam prep</button>
                            <button type="button" class="ds-chip" data-value="concept_mastery" aria-pressed="false">Understanding topics deeply</button>
                            <button type="button" class="ds-chip" data-value="catch_up" aria-pressed="false">Catching up</button>
                            <button type="button" class="ds-chip" data-value="get_ahead" aria-pressed="false">Getting ahead</button>
                            <button type="button" class="ds-chip" data-value="general_learning" aria-pressed="false">Learning for fun</button>
                        </div>
                    </fieldset>

                    <div class="ds-field">
                        <label for="interest-input" class="ds-label">What are you into?</label>
                        <div class="onb-add">
                            <input type="text" id="interest-input" class="ds-input" placeholder="e.g. football, cooking, Minecraft">
                            <button type="button" class="ds-btn ds-btn--secondary ds-btn--sm" data-add="interest-input">Add</button>
                        </div>
                        <p class="ds-field__hint">Hobbies, sports, games, your job. Your tutor builds examples around them. Optional.</p>
                        <ul class="onb-tags" data-tags="interests" aria-label="Interests added"></ul>
                    </div>

                    <fieldset class="onb-group">
                        <legend class="ds-label">How much explanation do you like?</legend>
                        <div class="ds-chip-row" data-single="responseStyle">
                            <button type="button" class="ds-chip" data-value="concise" aria-pressed="false">Short and to the point</button>
                            <button type="button" class="ds-chip" data-value="detailed" aria-pressed="false">Fuller, with worked examples</button>
                        </div>
                    </fieldset>

                    <p class="ds-field__error" id="err-3" role="alert"></p>
                    <div class="onb-nav">
                        <button type="button" class="ds-btn ds-btn--tertiary" data-back>Back</button>
                        <button type="button" class="ds-btn ds-btn--primary" data-next id="s3-next">Continue <svg class="ds-i ds-i-arrow"><use href="#i-arrow"/></svg></button>
                    </div>
                </section>

                <!-- ============ 4. Quick check ============ -->
                <section class="onb-step" data-step="4" aria-labelledby="s4-title" hidden>
                    <header class="onb-head">
                        <h1 class="onb-title" id="s4-title">A quick check</h1>
                        <p class="ds-muted" id="s4-sub">Three questions so your tutor knows where to start. It isn’t graded.</p>
                    </header>

                    <div class="onb-quiz" id="quiz">
                        <p class="onb-quiz__count" id="quiz-count">Question 1 of 3</p>
                        <p class="onb-quiz__q" id="quiz-q"></p>
                        <div class="onb-quiz__opts" id="quiz-opts" role="group" aria-labelledby="quiz-q"></div>
                        <p class="onb-quiz__feedback" id="quiz-feedback" aria-live="polite"></p>
                    </div>

                    <div class="onb-result" id="quiz-result" hidden>
                        <p class="ds-label">Your tutor will start at</p>
                        <p class="onb-result__level" id="result-level"></p>
                        <p class="ds-muted" id="result-msg"></p>
                    </div>

                    <div class="onb-nav">
                        <button type="button" class="ds-btn ds-btn--tertiary" data-back>Back</button>
                        <button type="button" class="ds-btn ds-btn--primary" id="quiz-next" disabled>Next question <svg class="ds-i ds-i-arrow"><use href="#i-arrow"/></svg></button>
                    </div>
                    <button type="button" class="onb-skip" id="quiz-skip">Skip the check</button>
                </section>

                <!-- ============ 5. Ready ============ -->
                <section class="onb-step" data-step="5" aria-labelledby="s5-title" hidden>
                    <header class="onb-head">
                        <h1 class="onb-title" id="s5-title">Your tutor is ready</h1>
                        <p class="ds-muted" id="recap"></p>
                    </header>

                    <fieldset class="onb-group">
                        <legend class="ds-label">Pick a first question, or write your own</legend>
                        <div class="onb-starters" id="starters"></div>
                    </fieldset>
                    <div class="ds-field">
                        <label for="first-prompt" class="ds-visually-hidden">Your first question</label>
                        <textarea id="first-prompt" class="ds-input onb-prompt" rows="2" placeholder="Ask anything about what you’re studying"></textarea>
                    </div>

                    <div class="ds-alert" id="save-error" role="alert" hidden>
                        <svg class="ds-i"><use href="#i-alert"/></svg><span></span>
                    </div>
                    <div class="onb-nav">
                        <button type="button" class="ds-btn ds-btn--tertiary" data-back>Back</button>
                        <button type="button" class="ds-btn ds-btn--cta" id="finish-btn">Start learning <svg class="ds-i ds-i-arrow"><use href="#i-arrow"/></svg></button>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <!-- Update mode: completed users returning to fill in newly added fields.
         Their saved profile is preloaded so re-saving doesn't wipe earlier answers. -->
    <script>
        window.TUTORMIND_UPDATE_MODE = <?= json_encode($update_mode) ?>;
        // Scopes the saved wizard progress so a shared browser never restores another account's answers
        window.TUTORMIND_USER_ID = <?= json_encode($user_id) ?>;
        window.TUTORMIND_SAVED_PREFS = <?= json_encode($saved_prefs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        <?php
            // Re-encode profile_data with HTML-safe flags so sequences like
            // </script> in user-supplied values cannot terminate this element.
            $safe_profile = null;
            if ($existing_profile !== null && json_decode($existing_profile) !== null) {
                $safe_profile = json_encode(
                    json_decode($existing_profile),
                    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
                );
            }
        ?>
        window.TUTORMIND_EXISTING_PROFILE = <?= $safe_profile !== null ? $safe_profile : 'null' ?>;
    </script>

    <script src="https://cdn.jsdelivr.net/npm/three@0.158.0/build/three.min.js"></script>
    <script src="assets/js/tm-ds.js?v=<?= filemtime('assets/js/tm-ds.js') ?>"></script>
    <!-- After tm-ds.js: its DOMContentLoaded boot attaches host.tmScene first -->
    <script src="assets/js/onboarding-data.js?v=<?= filemtime('assets/js/onboarding-data.js') ?>"></script>
    <script src="assets/js/onboarding-flow.js?v=<?= filemtime('assets/js/onboarding-flow.js') ?>"></script>
</body>
</html>
