<?php
// Forgot password — asks for the email and sends a reset link (api/password_reset.php).
// Same layout and padlock scene as login.php (tm-ds).
header("Cache-Control: no-cache, no-store, must-revalidate");
$v = fn($f) => filemtime($f);
?>
<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Forgot password — TutorMind</title>
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
                <p class="ds-auth__headline">Lost your key?<br>We'll cut a <span class="ds-accent">new one.</span></p>
                <p class="ds-lede">Your chats, notes and progress stay exactly where you left them.</p>
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
                    <h1 class="ds-h2">Forgot your password?</h1>
                    <p class="ds-muted" style="margin:0">Enter the email you signed up with and we will send you a link to set a new one. Signed up with Google? This adds a password too.</p>
                </header>

                <div class="ds-alert" id="auth-error" role="alert" hidden>
                    <svg class="ds-i"><use href="#i-alert"/></svg><span></span>
                </div>
                <div class="ds-alert ds-alert--info" id="auth-notice" role="status" hidden>
                    <svg class="ds-i"><use href="#i-check"/></svg><span></span>
                </div>

                <form id="forgotForm" class="ds-form" novalidate>
                    <div class="ds-field">
                        <label for="email" class="ds-label">Email</label>
                        <input type="email" id="email" name="email" class="ds-input" placeholder="you@example.com" autocomplete="email" required>
                    </div>
                    <button type="submit" class="ds-btn ds-btn--primary ds-btn--block" id="sendBtn">Send the link <svg class="ds-i ds-i-arrow"><use href="#i-arrow"/></svg></button>
                </form>

                <p class="ds-muted" id="afterSend" style="margin:0" hidden>Nothing in your inbox after a few minutes? Check spam, then <button type="button" class="ds-textlink" id="againBtn" style="border:0;background:none;padding:0;font:inherit;cursor:pointer">try again</button>.</p>

                <p class="ds-auth__alt">Remembered it? <a href="login">Log in</a></p>
            </div>
        </main>
    </div>

    <script>
        (function () {
            var form = document.getElementById('forgotForm');
            var email = document.getElementById('email');
            var btn = document.getElementById('sendBtn');
            var btnHTML = btn.innerHTML;
            var errorBox = document.getElementById('auth-error');
            var notice = document.getElementById('auth-notice');
            var afterSend = document.getElementById('afterSend');

            function show(box, msg) { box.querySelector('span').textContent = msg; box.hidden = false; }
            function lockScene(method, arg) {
                var host = document.querySelector('[data-ds-scene="lock"]');
                if (host && host.tmScene) host.tmScene[method](arg);
            }

            // Carried over from the login form when it looks like an email
            try {
                var pre = new URLSearchParams(location.search).get('email');
                if (pre && /@/.test(pre)) email.value = pre;
            } catch (e) {}
            email.addEventListener('input', function () { lockScene('setProgress', Math.min(1, email.value.length / 12)); });

            document.getElementById('againBtn').addEventListener('click', function () {
                notice.hidden = true; afterSend.hidden = true; form.hidden = false; email.focus();
            });

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                errorBox.hidden = true;
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
                    email.setAttribute('aria-invalid', 'true');
                    show(errorBox, 'Enter the email address you signed up with.');
                    return;
                }
                email.setAttribute('aria-invalid', 'false');
                btn.disabled = true;
                btn.innerHTML = (window.TmLoader ? TmLoader.inlineHTML() + ' ' : '') + 'Sending…';
                lockScene('attempt');
                try {
                    var t = await (await fetch('includes/csrf.php?action=get_token')).json();
                    var body = new FormData();
                    body.append('action', 'request');
                    body.append('email', email.value.trim());
                    body.append('csrf_token', t.token);
                    var res = await fetch('api/password_reset.php', { method: 'POST', body: body });
                    var data = await res.json();
                    if (data.success) {
                        form.hidden = true;
                        show(notice, data.message);
                        afterSend.hidden = false;
                        lockScene('setProgress', 1); // key waits at the lock; it turns on the reset page
                    } else {
                        show(errorBox, data.error || 'Could not send the link. Please try again.');
                        lockScene('deny');
                    }
                } catch (err) {
                    show(errorBox, 'Couldn’t reach TutorMind. Check your connection and try again.');
                    lockScene('deny');
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
