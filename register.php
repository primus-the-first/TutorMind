<?php
// Register page — 2026-09 design system (tm-ds.css/js): keystone scene + form.
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

// Robust Google Login URI Generation
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$scriptDir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$scriptDir = str_replace('\\', '/', $scriptDir); // Ensure forward slashes for Windows
$google_login_uri = "$protocol://$host$scriptDir/auth_mysql.php";
?>
<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create your account — TutorMind</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon-new.svg">
    <link rel="icon" type="image/png" href="assets/icons/icon-512.png">
    <link rel="apple-touch-icon" href="assets/icons/icon-512.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Funnel+Display:wght@600;700&family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/tm-tokens.css?v=<?= filemtime('assets/css/tm-tokens.css') ?>">
    <link rel="stylesheet" href="assets/css/tm-ds.css?v=<?= filemtime('assets/css/tm-ds.css') ?>">

    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body class="ds-page">
    <!-- Unified Theme Script (same fallbacks as register.php) -->
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
        <!-- Scene: the arch gets built as you go. Each passing check lays a pair
             of stones from both ends inward; the amber keystone goes in last. -->
        <aside class="ds-auth__stage" aria-hidden="true">
            <a href="index" class="ds-logo" tabindex="-1"><img src="assets/logo-bridge.svg" alt="">TutorMind</a>
            <div class="ds-auth__intro">
                <p class="ds-auth__headline">Start building<br>your <span class="ds-accent">bridge.</span></p>
                <p class="ds-lede">Every step lays a stone. The keystone goes in last, and then you’re ready to cross.</p>
            </div>
            <div class="ds-auth__visual" data-ds-scene="keystone">
                <div class="ds-hero__fallback"><img src="assets/logo-bridge.svg" alt=""></div>
                <span class="ds-auth__status" id="scene-status">Every bridge starts with one stone</span>
            </div>
        </aside>

        <main class="ds-auth__main">
            <div class="ds-auth__top">
                <a href="index" class="ds-logo ds-auth__mobile-logo"><img src="assets/logo-bridge.svg" alt="">TutorMind</a>
                <button class="ds-icon-btn" type="button" data-ds-theme aria-label="Dark mode"><svg class="ds-i"><use href="#i-moon"/></svg></button>
            </div>

            <div class="ds-auth__card">
                <header>
                    <h1 class="ds-h2">Create your account</h1>
                    <p class="ds-muted" style="margin:0">Your own AI tutor, ready when you are.</p>
                </header>

                <div class="ds-alert" id="auth-error" role="alert" hidden>
                    <svg class="ds-i"><use href="#i-alert"/></svg><span></span>
                </div>

                <form id="registerForm" action="auth_mysql" method="POST" style="display:grid;gap:16px" novalidate>
                    <input type="hidden" name="csrf_token" id="csrf_token" value="">

                    <div class="ds-field">
                        <label for="fullName" class="ds-label">Full name</label>
                        <input type="text" id="fullName" name="fullName" class="ds-input" placeholder="e.g. Ama Mensah" autocomplete="name">
                    </div>

                    <div class="ds-field">
                        <label for="username" class="ds-label">Username</label>
                        <input type="text" id="username" name="username" class="ds-input" placeholder="Choose a username" autocomplete="username" required aria-describedby="username-error">
                        <p class="ds-field__error" id="username-error"></p>
                    </div>

                    <div class="ds-field">
                        <label for="email" class="ds-label">Email</label>
                        <input type="email" id="email" name="email" class="ds-input" placeholder="name@example.com" autocomplete="email" spellcheck="false" required aria-describedby="email-error">
                        <p class="ds-field__error" id="email-error"></p>
                    </div>

                    <div class="ds-field">
                        <label for="password" class="ds-label">Password</label>
                        <div class="ds-input-wrap">
                            <input type="password" id="password" name="password" class="ds-input" placeholder="At least 8 characters" autocomplete="new-password" required aria-describedby="password-error password-strength">
                            <button type="button" id="togglePassword" class="ds-ask__tool" aria-label="Show passwords" aria-pressed="false">
                                <svg class="ds-i"><use href="#i-eye"/></svg>
                            </button>
                        </div>
                        <div class="ds-strength" id="password-strength" data-level="0" aria-live="polite">
                            <span class="ds-strength__track"><span class="ds-strength__fill"></span></span>
                            <span class="ds-strength__label"></span>
                        </div>
                        <p class="ds-field__error" id="password-error"></p>
                    </div>

                    <div class="ds-field">
                        <label for="confirmPassword" class="ds-label">Confirm password</label>
                        <input type="password" id="confirmPassword" name="confirmPassword" class="ds-input" placeholder="Type it again" autocomplete="new-password" required aria-describedby="confirmPassword-error">
                        <p class="ds-field__error" id="confirmPassword-error"></p>
                    </div>

                    <label class="ds-check" style="align-items:flex-start">
                        <input type="checkbox" id="terms" name="terms" required style="margin-top:3px">
                        <span>I agree to the <a href="#" class="ds-textlink">Terms &amp; Conditions</a> and Privacy Policy.</span>
                    </label>

                    <button type="submit" id="createAccountBtn" class="ds-btn ds-btn--primary ds-btn--block" disabled>Create account <svg class="ds-i ds-i-arrow"><use href="#i-arrow"/></svg></button>
                </form>

                <div class="ds-divider">or</div>

                <!-- Google Sign In Button - Redirect Mode for HTTPS -->
                <div class="ds-auth__google">
                    <div id="g_id_onload"
                         data-client_id="1083917773706-gc0f400l24eavps3ckcnj04581gj3plk.apps.googleusercontent.com"
                         data-context="signup"
                         data-ux_mode="redirect"
                         data-login_uri="<?php echo htmlspecialchars($google_login_uri); ?>"
                         data-auto_prompt="false">
                    </div>
                    <div class="g_id_signin" id="g_id_button" data-type="standard" data-shape="rectangular" data-theme="outline"
                         data-text="signup_with" data-size="large" data-logo_alignment="left" data-width="320">
                    </div>
                    <script>
                        if (document.body.classList.contains('dark-mode')) document.getElementById('g_id_button').setAttribute('data-theme', 'filled_black');
                    </script>
                </div>

                <p class="ds-auth__alt">Already have an account? <a href="login">Log in</a></p>
            </div>
        </main>
    </div>

    <script>
        var authError = document.getElementById('auth-error');
        function showError(msg) {
            authError.querySelector('span').textContent = msg;
            authError.hidden = false;
        }

        // Drive the keystone scene (no-op without WebGL)
        var sceneStatus = document.getElementById('scene-status');
        function archScene(method, arg, status, state) {
            var host = document.querySelector('[data-ds-scene="keystone"]');
            if (host && host.tmScene) host.tmScene[method](arg);
            if (status) { sceneStatus.textContent = status; sceneStatus.setAttribute('data-state', state || ''); }
        }
        // Time for the learner to roll across the finished bridge before redirecting
        var CROSS_MS = 1900;

        document.addEventListener('DOMContentLoaded', function () {
            var registerForm = document.getElementById('registerForm');
            var fullNameInput = document.getElementById('fullName');
            var usernameInput = document.getElementById('username');
            var emailInput = document.getElementById('email');
            var passwordInput = document.getElementById('password');
            var confirmPasswordInput = document.getElementById('confirmPassword');
            var termsCheckbox = document.getElementById('terms');
            var createAccountBtn = document.getElementById('createAccountBtn');
            var btnHTML = createAccountBtn.innerHTML;
            var togglePassword = document.getElementById('togglePassword');
            var strength = document.getElementById('password-strength');
            var strengthLabel = strength.querySelector('.ds-strength__label');

            // Password toggle (shows/hides both fields, as before)
            togglePassword.addEventListener('click', function () {
                var show = passwordInput.type === 'password';
                passwordInput.type = confirmPasswordInput.type = show ? 'text' : 'password';
                togglePassword.setAttribute('aria-pressed', String(show));
                togglePassword.setAttribute('aria-label', show ? 'Hide passwords' : 'Show passwords');
                togglePassword.querySelector('use').setAttribute('href', show ? '#i-eye-off' : '#i-eye');
            });

            // URL errors (e.g. from Google redirect)
            var urlParams = new URLSearchParams(window.location.search);
            var errorMsg = urlParams.get('error');
            if (errorMsg) {
                showError(decodeURIComponent(errorMsg));
                window.history.replaceState({}, document.title, window.location.pathname);
            }

            // Same rules as register.php
            var validators = {
                username: {
                    validate: function () { return /^[a-zA-Z0-9_]{3,15}$/.test(usernameInput.value); },
                    message: 'Username must be 3–15 characters: letters, numbers or _'
                },
                email: {
                    validate: function () { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value); },
                    message: 'Enter a valid email address'
                },
                password: {
                    validate: function () { return passwordInput.value.length >= 8; },
                    message: 'Password needs at least 8 characters'
                },
                confirmPassword: {
                    validate: function () { return confirmPasswordInput.value === passwordInput.value && confirmPasswordInput.value.length > 0; },
                    message: 'Passwords don’t match'
                },
                terms: {
                    validate: function () { return termsCheckbox.checked; },
                    message: ''
                }
            };

            function fieldFor(key) { return key === 'terms' ? termsCheckbox : document.getElementById(key); }

            function checkFieldValidity(key, force) {
                var field = fieldFor(key), ok = validators[key].validate();
                var err = document.getElementById(key + '-error');
                // Only flag a field once it's dirty (or on submit)
                var show = !ok && (force || field.value.length > 0) && key !== 'terms';
                if (key !== 'terms') field.setAttribute('aria-invalid', String(show));
                if (err) err.textContent = show ? validators[key].message : '';
                return ok;
            }

            var STATUS = ['Every bridge starts with one stone', 'Laying stones · 1 of 5', 'Laying stones · 2 of 5', 'Laying stones · 3 of 5', 'Laying stones · 4 of 5', 'Keystone in. Ready to cross!'];
            function checkFormValidity() {
                var valid = 0;
                for (var key in validators) if (validators[key].validate()) valid++;
                createAccountBtn.disabled = valid < 5;
                archScene('setProgress', valid / 5, STATUS[valid], valid === 5 ? 'ok' : '');
            }

            function checkPasswordStrength() {
                var password = passwordInput.value, score = 0;
                if (password.length >= 8) score++;
                if (/[A-Z]/.test(password)) score++;
                if (/[0-9]/.test(password)) score++;
                if (/[^A-Za-z0-9]/.test(password)) score++;
                var level = password.length === 0 ? 0 : score < 2 ? 1 : score < 4 ? 2 : 3;
                strength.setAttribute('data-level', level);
                strengthLabel.textContent = ['', 'Weak', 'Medium', 'Strong'][level];
            }

            [usernameInput, emailInput, passwordInput, confirmPasswordInput].forEach(function (input) {
                input.addEventListener('input', function () {
                    checkFieldValidity(input.id);
                    if (input === passwordInput) {
                        checkPasswordStrength();
                        if (confirmPasswordInput.value) checkFieldValidity('confirmPassword');
                    }
                    checkFormValidity();
                });
            });
            termsCheckbox.addEventListener('change', checkFormValidity);

            // Fetch CSRF token on page load (as before)
            fetch('includes/csrf.php?action=get_token')
                .then(function (res) { return res.json(); })
                .then(function (data) { if (data.token) document.getElementById('csrf_token').value = data.token; })
                .catch(function (err) { console.error('CSRF token fetch error:', err); });

            registerForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                authError.hidden = true;

                var isValid = true;
                for (var key in validators) if (!checkFieldValidity(key, true)) isValid = false;
                if (!isValid) {
                    showError('Please fix the highlighted fields first.');
                    archScene('deny', null, 'A few stones are missing', 'bad');
                    return;
                }

                // Parse full name into first and last (same fallback as register.php)
                var nameParts = fullNameInput.value.trim().split(' ');
                var firstName = nameParts[0] || '';
                var lastName = nameParts.slice(1).join(' ') || nameParts[0];

                var formData = new FormData();
                formData.append('action', 'register');
                formData.append('firstName', firstName);
                formData.append('lastName', lastName);
                formData.append('username', usernameInput.value);
                formData.append('email', emailInput.value);
                formData.append('password', passwordInput.value);
                formData.append('csrf_token', document.getElementById('csrf_token').value);

                var isDarkLocal = false;
                var themeLocal = localStorage.getItem('tutormind-theme');
                if (themeLocal) {
                    isDarkLocal = themeLocal === 'dark';
                } else {
                    isDarkLocal = localStorage.getItem('darkMode') === 'enabled' || localStorage.getItem('theme') === 'dark';
                }
                formData.append('local_theme', isDarkLocal ? 'dark' : 'light');

                createAccountBtn.disabled = true;
                createAccountBtn.textContent = 'Creating your account…';
                archScene('attempt', null, 'Setting the keystone…', '');

                try {
                    var response = await fetch('auth_mysql', { method: 'POST', body: formData });
                    var data = await response.json();
                    if (data.success && data.redirect) {
                        createAccountBtn.textContent = 'Account created';
                        archScene('grant', null, 'Bridge built. Welcome to TutorMind!', 'ok');
                        setTimeout(function () { window.location.href = data.redirect; }, CROSS_MS);
                    } else {
                        throw new Error(data.error || 'Registration failed');
                    }
                } catch (error) {
                    console.error('Registration error:', error);
                    showError(error.message || 'Something went wrong. Please try again.');
                    archScene('deny', null, 'The bridge wobbled. Check the form.', 'bad');
                    createAccountBtn.disabled = false;
                    createAccountBtn.innerHTML = btnHTML;
                }
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.158.0/build/three.min.js"></script>
    <script src="assets/js/tm-ds.js?v=<?= filemtime('assets/js/tm-ds.js') ?>"></script>
</body>
</html>
