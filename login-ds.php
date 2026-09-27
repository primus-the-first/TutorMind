<?php
// Design-system preview (2026-09) of login.php — same auth wiring, new UI.
// Set Security Headers - Using unsafe-none for localhost popup compatibility
header("Cross-Origin-Opener-Policy: unsafe-none");
// CSP: Allow Google Sign-In resources
$csp = "default-src 'self'; ";
$csp .= "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://accounts.google.com https://apis.google.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; ";
$csp .= "style-src 'self' 'unsafe-inline' https://accounts.google.com https://fonts.googleapis.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; ";
$csp .= "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; ";
$csp .= "img-src 'self' data: https: blob:; ";
$csp .= "connect-src 'self' https://accounts.google.com https://generativelanguage.googleapis.com https://oauth2.googleapis.com https://api.elevenlabs.io https://serpapi.com; ";
$csp .= "frame-src 'self' https://accounts.google.com; ";
$csp .= "frame-ancestors 'self';";
header("Content-Security-Policy: " . $csp);

header("Cache-Control: no-cache, no-store, must-revalidate");
?>
<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Log in — TutorMind</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon-new.svg">
    <link rel="icon" type="image/png" href="assets/icons/icon-512.png">
    <link rel="apple-touch-icon" href="assets/icons/icon-512.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Funnel+Display:wght@600;700&family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/tm-ds.css?v=<?= filemtime('assets/css/tm-ds.css') ?>">
    <link rel="stylesheet" href="assets/css/tm-loader.css?v=<?= filemtime('assets/css/tm-loader.css') ?>">

    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script src="assets/js/tm-loader.js?v=<?= filemtime('assets/js/tm-loader.js') ?>"></script>
</head>
<body class="ds-page">
    <!-- Unified Theme Script (same fallbacks as login.php) -->
    <script>
        (function () {
            var isDark = false, theme = null;
            try {
                theme = new URLSearchParams(location.search).get('theme') || localStorage.getItem('tutormind-theme');
                isDark = theme ? theme === 'dark'
                    : (localStorage.getItem('darkMode') === 'enabled' || localStorage.getItem('theme') === 'dark');
            } catch (e) {}
            if (isDark) document.body.classList.add('dark-mode');
        })();
    </script>

    <div class="ds-auth">
        <!-- Left: brand stage (desktop) -->
        <aside class="ds-auth__stage" aria-hidden="true">
            <a href="index" class="ds-logo" tabindex="-1"><img src="assets/logo-bridge.svg" alt="">TutorMind</a>
            <div style="display:grid;gap:14px">
                <p class="ds-auth__headline">Learning starts<br>with one <span class="ds-accent" style="color:var(--primary-light)">question.</span></p>
                <p class="ds-lede">Pick up right where you left off.</p>
            </div>
            <div class="ds-auth__visual" data-ds-bridge>
                <div class="ds-hero__fallback"><img src="assets/logo-bridge.svg" alt=""></div>
                <div class="ds-float ds-float--a"><span class="ds-float__tag">Up next</span><b>Review osmosis</b></div>
                <div class="ds-float ds-float--b"><span class="ds-float__tag">Streak</span><b>4 days</b></div>
            </div>
        </aside>

        <!-- Right: form -->
        <main class="ds-auth__main">
            <div class="ds-auth__top">
                <a href="index" class="ds-logo ds-auth__mobile-logo"><img src="assets/logo-bridge.svg" alt="">TutorMind</a>
                <button class="ds-icon-btn" type="button" data-ds-theme aria-label="Dark mode"><svg class="ds-i"><use href="#i-moon"/></svg></button>
            </div>

            <div class="ds-auth__card">
                <header>
                    <h1 class="ds-h2">Welcome back!</h1>
                    <p class="ds-muted" style="margin:0">Sign in to continue learning.</p>
                </header>

                <div class="ds-alert" id="auth-error" role="alert" hidden>
                    <svg class="ds-i"><use href="#i-alert"/></svg><span></span>
                </div>

                <form id="loginForm" action="auth_mysql" method="POST" style="display:grid;gap:18px" novalidate>
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="csrf_token" id="csrf_token" value="">
                    <input type="hidden" name="local_theme" id="local_theme" value="light">

                    <div class="ds-field">
                        <label for="email" class="ds-label">Email or username</label>
                        <input type="text" id="email" name="email" class="ds-input" placeholder="you@example.com" autocomplete="username" required>
                    </div>

                    <div class="ds-field">
                        <label for="password" class="ds-label">Password</label>
                        <div class="ds-input-wrap">
                            <input type="password" id="password" name="password" class="ds-input" placeholder="Your password" autocomplete="current-password" required>
                            <button type="button" id="togglePassword" class="ds-ask__tool" aria-label="Show password" aria-pressed="false">
                                <svg class="ds-i"><use href="#i-eye"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="ds-row">
                        <label class="ds-check"><input type="checkbox" id="remember" name="remember"> Remember me</label>
                        <a href="#" class="ds-textlink">Forgot password?</a>
                    </div>

                    <button type="submit" class="ds-btn ds-btn--primary ds-btn--block" id="loginBtn">Log in <svg class="ds-i ds-i-arrow"><use href="#i-arrow"/></svg></button>
                </form>

                <div class="ds-divider">or</div>

                <div class="ds-auth__google">
                    <div id="g_id_onload"
                        data-client_id="1083917773706-gc0f400l24eavps3ckcnj04581gj3plk.apps.googleusercontent.com"
                        data-context="signin"
                        data-ux_mode="popup"
                        data-callback="handleCredentialResponse"
                        data-auto_prompt="false">
                    </div>
                    <div class="g_id_signin" id="g_id_button" data-type="standard" data-size="large" data-theme="outline"
                        data-text="signin_with" data-shape="rectangular" data-logo_alignment="left" data-width="320">
                    </div>
                    <script>
                        if (document.body.classList.contains('dark-mode')) document.getElementById('g_id_button').setAttribute('data-theme', 'filled_black');
                    </script>
                </div>

                <p class="ds-auth__alt">New to TutorMind? <a href="register">Create an account</a></p>
            </div>
        </main>
    </div>

    <script>
        var authError = document.getElementById('auth-error');
        function showError(msg) {
            authError.querySelector('span').textContent = msg;
            authError.hidden = false;
        }

        document.addEventListener('DOMContentLoaded', function () {
            var loginForm = document.getElementById('loginForm');
            var btn = document.getElementById('loginBtn');
            var btnHTML = btn.innerHTML;
            var togglePassword = document.getElementById('togglePassword');
            var passwordInput = document.getElementById('password');

            // Password toggle
            togglePassword.addEventListener('click', function () {
                var show = passwordInput.type === 'password';
                passwordInput.type = show ? 'text' : 'password';
                togglePassword.setAttribute('aria-pressed', String(show));
                togglePassword.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                togglePassword.querySelector('use').setAttribute('href', show ? '#i-eye-off' : '#i-eye');
            });

            // URL errors (e.g. from Google redirect)
            var urlParams = new URLSearchParams(window.location.search);
            var errorMsg = urlParams.get('error');
            if (errorMsg) {
                showError(decodeURIComponent(errorMsg));
                window.history.replaceState({}, document.title, window.location.pathname);
            }

            // Sync local theme to form
            var isDarkLocal = false;
            var themeLocal = localStorage.getItem('tutormind-theme');
            if (themeLocal) {
                isDarkLocal = themeLocal === 'dark';
            } else {
                isDarkLocal = localStorage.getItem('darkMode') === 'enabled' || localStorage.getItem('theme') === 'dark';
            }
            document.getElementById('local_theme').value = isDarkLocal ? 'dark' : 'light';

            function resetBtn() { btn.disabled = false; btn.innerHTML = btnHTML; }

            loginForm.addEventListener('submit', async function (event) {
                event.preventDefault();
                authError.hidden = true;

                // Inline validation instead of the browser's bubbles
                var missing = false;
                ['email', 'password'].forEach(function (id) {
                    var el = document.getElementById(id);
                    var empty = !el.value.trim();
                    el.setAttribute('aria-invalid', String(empty));
                    if (empty) missing = true;
                });
                if (missing) { showError('Enter your email or username and password.'); return; }

                btn.disabled = true;
                btn.innerHTML = (window.TmLoader && TmLoader.inlineHTML ? TmLoader.inlineHTML() + ' ' : '') + 'Logging in…';

                try {
                    var tokenResponse = await fetch('includes/csrf.php?action=get_token');
                    var tokenData = await tokenResponse.json();
                    document.getElementById('csrf_token').value = tokenData.token;

                    var response = await fetch(loginForm.getAttribute('action'), {
                        method: 'POST',
                        body: new FormData(loginForm)
                    });
                    var responseText = await response.text();

                    try {
                        var result = JSON.parse(responseText);
                        if (result.success && result.redirect) {
                            TmLoader.showFullscreen('Signing you in…');
                            if (result.db_theme) {
                                localStorage.setItem('tutormind-theme', result.db_theme);
                                if (result.db_theme === 'dark') {
                                    localStorage.setItem('darkMode', 'enabled');
                                    localStorage.setItem('theme', 'dark');
                                } else {
                                    localStorage.removeItem('darkMode');
                                    localStorage.setItem('theme', 'light');
                                }
                            }
                            // Let the loader play one full cycle before handing off
                            setTimeout(function () { window.location.href = result.redirect; }, TmLoader.FULL_CYCLE_MS || 2200);
                        } else {
                            showError(result.error || 'Login failed. Check your details and try again.');
                            resetBtn();
                        }
                    } catch (e) {
                        console.error('Invalid JSON', responseText);
                        showError('Something went wrong on our side. Please try again.');
                        resetBtn();
                    }
                } catch (error) {
                    console.error('Login error:', error);
                    showError('Couldn’t reach TutorMind. Check your connection and try again.');
                    resetBtn();
                }
            });
        });

        function handleCredentialResponse(response) {
            var formData = new FormData();
            formData.append('action', 'google_login');
            formData.append('credential', response.credential);

            fetch('auth_mysql', { method: 'POST', body: formData })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.success && data.redirect) {
                        TmLoader.showFullscreen('Signing you in…');
                        setTimeout(function () { window.location.href = data.redirect; }, TmLoader.FULL_CYCLE_MS || 2200);
                    } else {
                        showError(data.error || 'Google sign-in failed.');
                    }
                })
                .catch(function (err) {
                    console.error(err);
                    showError('Couldn’t reach TutorMind. Check your connection and try again.');
                });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.158.0/build/three.min.js"></script>
    <script src="assets/js/tm-ds.js?v=<?= filemtime('assets/js/tm-ds.js') ?>"></script>
</body>
</html>
