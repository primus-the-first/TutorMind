<?php
// Reset password — the page the emailed link opens (api/password_reset.php).
// The token is read from the URL, checked, then dropped from the address bar.
// Same layout and padlock scene as login.php (tm-ds): the key turns when the password is set.
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Referrer-Policy: no-referrer");
$v = fn($f) => filemtime($f);
?>
<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <meta name="referrer" content="no-referrer">
    <title>Set a new password — TutorMind</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon-new.svg">
    <link rel="icon" type="image/png" href="assets/icons/icon-512.png">
    <link rel="apple-touch-icon" href="assets/icons/icon-512.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Funnel+Display:wght@600;700&family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/tm-tokens.css?v=<?= $v('assets/css/tm-tokens.css') ?>">
    <link rel="stylesheet" href="assets/css/tm-ds.css?v=<?= $v('assets/css/tm-ds.css') ?>">
    <link rel="stylesheet" href="assets/css/tm-loader.css?v=<?= $v('assets/css/tm-loader.css') ?>">
    <script src="assets/js/tm-loader.js?v=<?= $v('assets/js/tm-loader.js') ?>"></script>
</head>
<body class="ds-page">
    <script>
        (function () {
            var theme = null;
            try { theme = localStorage.getItem('tutormind-theme') || (localStorage.getItem('darkMode') === 'enabled' ? 'dark' : null); } catch (e) {}
            if (theme === 'dark') document.body.classList.add('dark-mode');
        })();
    </script>

    <div class="ds-auth">
        <aside class="ds-auth__stage" aria-hidden="true">
            <a href="index" class="ds-logo" tabindex="-1"><img src="assets/logo-bridge.svg" alt="">TutorMind</a>
            <div class="ds-auth__intro">
                <p class="ds-auth__headline">A new key,<br>just for <span class="ds-accent">you.</span></p>
                <p class="ds-lede">Pick something you will remember. You can change it again in Settings.</p>
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
                    <h1 class="ds-h2">Set a new password</h1>
                    <p class="ds-muted" style="margin:0">Use at least 8 characters. After this you can log in with your email and this password.</p>
                </header>

                <div class="ds-alert" id="auth-error" role="alert" hidden>
                    <svg class="ds-i"><use href="#i-alert"/></svg><span></span>
                </div>

                <form id="resetForm" class="ds-form" novalidate hidden>
                    <div class="ds-field">
                        <label for="password" class="ds-label">New password</label>
                        <div class="ds-input-wrap">
                            <input type="password" id="password" class="ds-input" placeholder="At least 8 characters" autocomplete="new-password" minlength="8" required>
                            <button type="button" id="togglePassword" class="ds-ask__tool" aria-label="Show password" aria-pressed="false">
                                <svg class="ds-i"><use href="#i-eye"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="ds-field">
                        <label for="confirm" class="ds-label">Type it again</label>
                        <input type="password" id="confirm" class="ds-input" autocomplete="new-password" required>
                    </div>
                    <button type="submit" class="ds-btn ds-btn--primary ds-btn--block" id="saveBtn">Save password <svg class="ds-i ds-i-arrow"><use href="#i-arrow"/></svg></button>
                </form>

                <p class="ds-auth__alt" id="expiredAlt" hidden><a href="forgot-password">Send me a new link</a></p>
                <p class="ds-auth__alt">Remembered it? <a href="login">Log in</a></p>
            </div>
        </main>
    </div>

    <script>
        (function () {
            var params = new URLSearchParams(location.search);
            var token = params.get('token') || '';
            // Keep the token out of history, bookmarks and screenshots of the address bar
            history.replaceState({}, document.title, location.pathname);

            var form = document.getElementById('resetForm');
            var pw = document.getElementById('password');
            var confirmPw = document.getElementById('confirm');
            var btn = document.getElementById('saveBtn');
            var btnHTML = btn.innerHTML;
            var errorBox = document.getElementById('auth-error');
            var toggle = document.getElementById('togglePassword');

            function showError(msg) { errorBox.querySelector('span').textContent = msg; errorBox.hidden = false; }
            function lockScene(method, arg) {
                var host = document.querySelector('[data-ds-scene="lock"]');
                if (host && host.tmScene) host.tmScene[method](arg);
            }
            function expired(msg) {
                form.hidden = true;
                showError(msg || 'This link has expired or was already used. Ask for a new one.');
                document.getElementById('expiredAlt').hidden = false;
            }

            fetch('api/password_reset.php?action=check&token=' + encodeURIComponent(token))
                .then(function (r) { return r.json(); })
                .then(function (d) { if (d.valid) { form.hidden = false; pw.focus(); } else expired(); })
                .catch(function () { showError('Couldn’t reach TutorMind. Check your connection and reload.'); });

            pw.addEventListener('input', function () { lockScene('setProgress', pw.value.length / 8); });
            toggle.addEventListener('click', function () {
                var show = pw.type === 'password';
                pw.type = confirmPw.type = show ? 'text' : 'password';
                toggle.setAttribute('aria-pressed', String(show));
                toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                toggle.querySelector('use').setAttribute('href', show ? '#i-eye-off' : '#i-eye');
            });

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                errorBox.hidden = true;
                if (pw.value.length < 8) { pw.setAttribute('aria-invalid', 'true'); showError('Use at least 8 characters.'); return; }
                if (pw.value !== confirmPw.value) { confirmPw.setAttribute('aria-invalid', 'true'); showError('The two passwords don’t match.'); return; }
                pw.setAttribute('aria-invalid', 'false'); confirmPw.setAttribute('aria-invalid', 'false');

                btn.disabled = true;
                btn.innerHTML = (window.TmLoader ? TmLoader.inlineHTML() + ' ' : '') + 'Saving…';
                lockScene('attempt');
                try {
                    var t = await (await fetch('includes/csrf.php?action=get_token')).json();
                    var body = new FormData();
                    body.append('action', 'reset');
                    body.append('token', token);
                    body.append('password', pw.value);
                    body.append('csrf_token', t.token);
                    var res = await fetch('api/password_reset.php', { method: 'POST', body: body });
                    var data = await res.json();
                    if (data.success) {
                        lockScene('grant');
                        setTimeout(function () { location.href = data.redirect; }, 900); // let the key turn
                        return;
                    }
                    lockScene('deny');
                    if (res.status === 410) expired(data.error); else showError(data.error || 'Could not save the password. Please try again.');
                } catch (err) {
                    lockScene('deny');
                    showError('Couldn’t reach TutorMind. Check your connection and try again.');
                }
                btn.disabled = false;
                btn.innerHTML = btnHTML;
            });
        })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.158.0/build/three.min.js"></script>
    <script src="assets/js/tm-ds.js?v=<?= $v('assets/js/tm-ds.js') ?>"></script>
</body>
</html>
