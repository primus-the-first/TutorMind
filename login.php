<?php
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TutorMind</title>
    <link rel="icon" type="image/png" href="assets/favicon.png">
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Main Creative Styles -->
    <link rel="stylesheet" href="landing.css?v=3">
    
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body>
    
    <div class="auth-wrapper">
        <!-- Left Side: Form -->
        <div class="auth-form-side">
            <div class="auth-header">
                <a href="index" class="auth-brand">
                    <img src="assets/logo-bridge.svg?v=2" alt="TutorMind" style="height: 32px; width: auto; margin-right: 12px;">
                    TutorMind
                </a>
                <h1 class="auth-title">Welcome back!</h1>
                <p class="auth-subtitle">Time to learn something new today.</p>
            </div>

            <form id="loginForm" class="auth-form" action="auth_mysql" method="POST">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" id="csrf_token" value="">

                <div class="form-group">
                    <label for="email" class="form-label">Email or Username</label>
                    <input type="text" id="email" name="email" class="form-input" placeholder="Enter your email" autocomplete="username" required>
                    <div class="error-message" id="email-error"></div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div style="position: relative;">
                        <!-- Adjusted padding-right to accommodate eye icon -->
                        <input type="password" id="password" name="password" class="form-input" style="padding-right: 40px;" placeholder="Enter password" autocomplete="current-password" required>
                        <button type="button" id="togglePassword" aria-label="Toggle password visibility" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--text-secondary);">
                            <i class="far fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="error-message" id="password-error"></div>
                </div>

                <div class="form-group" style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" id="remember" name="remember" style="accent-color: var(--primary-ink); width: 16px; height: 16px;">
                        <label for="remember" style="font-size: 0.9rem; cursor: pointer;">Remember me</label>
                    </div>
                    <a href="#" style="color: var(--accent-purple); font-weight: 600; text-decoration: none; font-size: 0.9rem;">Forgot password?</a>
                </div>

                <button type="submit" class="btn-auth">Log In</button>

                <div class="auth-divider">
                    <span>Or login with</span>
                </div>

                <!-- Google Sign In Button - Redirect Mode for HTTPS -->
                <div id="g_id_onload"
                    data-client_id="1083917773706-gc0f400l24eavps3ckcnj04581gj3plk.apps.googleusercontent.com"
                    data-context="signin"
                    data-ux_mode="redirect"
                    data-login_uri="https://localhost/TutorMind/auth_mysql.php"
                    data-auto_prompt="false">
                </div>
                
                <div class="g_id_signin" data-type="standard" data-size="large" data-theme="outline"
                    data-text="sign_in_with" data-shape="rectangular" data-logo_alignment="left" data-width="100%">
                </div>

                <p style="text-align: center; margin-top: 1.5rem; color: var(--text-secondary);">
                    Don't have an account? <a href="register" style="color: var(--primary-ink); font-weight: 700; text-decoration: none;">Register here</a>
                </p>
            </form>
        </div>

        <!-- Right Side: Creative Visual -->
        <div class="auth-visual-side">
            <div class="auth-polaroid">
                <div class="auth-tape"></div>
                <!-- Updated Image -->
                <img src="assets/login_illustration.png" alt="Late Night Study" class="auth-visual-img" width="518" height="345">
                <div class="chat-bubble" style="top: -20px; left: -20px; transform: rotate(-5deg);">
                    <span class="chat-bubble-name">TutorMind</span>
                    <br>Late night study session? 🌙
                </div>
            </div>
            
            <!-- Floating Elements -->
            <div class="equation-marker" style="top: 20%; right: 10%; opacity: 0.1;">
                \( P \implies Q \)
            </div>
            <div class="equation-marker" style="bottom: 15%; left: 10%; opacity: 0.1;">
                \( \forall x \in \mathbb{R} \)
            </div>
        </div>
    </div>

    <!-- MathJax -->
    <script>
        MathJax = {
            tex: { inlineMath: [['\\(', '\\)']] }
        };
    </script>
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>

    <!-- Theme Script (Reuse Logic) -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Apply Dark Mode if saved
            const savedTheme = localStorage.getItem('tutormind-theme');
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-mode');
            }
        });
    </script>

    <!-- Login Logic -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const loginForm = document.getElementById('loginForm');
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');

            // Password Toggle
            togglePassword.addEventListener('click', function () {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });

            // Check for URL errors (e.g. from Google Redirect)
            const urlParams = new URLSearchParams(window.location.search);
            const errorMsg = urlParams.get('error');
            if (errorMsg) {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-banner';
                // Basic styling for the error banner
                errorDiv.style.cssText = 'background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; border: 1px solid #fecaca; display: flex; align-items: center; gap: 0.5rem;';
                errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> <span>' + decodeURIComponent(errorMsg).replace(/</g, "&lt;").replace(/>/g, "&gt;") + '</span>';
                
                loginForm.insertBefore(errorDiv, loginForm.firstChild);
                
                // Clean URL
                window.history.replaceState({}, document.title, window.location.pathname);
            }

            // Form Submit Logic (Kept mostly same but updated selectors/visuals)
            loginForm.addEventListener('submit', async function (event) {
                event.preventDefault();
                
                const btn = loginForm.querySelector('.btn-auth');
                btn.disabled = true;
                btn.textContent = 'Logging in...';

                try {
                    // Fetch CSRF (Assume csrf.php exists as per original)
                    const tokenResponse = await fetch('csrf.php?action=get_token');
                    const tokenData = await tokenResponse.json();
                    document.getElementById('csrf_token').value = tokenData.token;

                    const formData = new FormData(loginForm);
                    const response = await fetch(loginForm.getAttribute('action'), {
                        method: 'POST',
                        body: formData
                    });

                    const responseText = await response.text();
                    // Parse result
                    try {
                         const result = JSON.parse(responseText);
                         if (result.success && result.redirect) {
                             window.location.href = result.redirect;
                         } else {
                             alert(result.error || 'Login failed');
                             btn.disabled = false;
                            btn.textContent = 'Log In';
                         }
                    } catch(e) {
                        console.error("Invalid JSON", responseText);
                        alert("Server Error");
                        btn.disabled = false;
                        btn.textContent = 'Log In';
                    }

                } catch (error) {
                    console.error('Login error:', error);
                    alert('Connection error');
                    btn.disabled = false;
                    btn.textContent = 'Log In';
                }
            });
        });

        function handleCredentialResponse(response) {
            // Google Login Logic
            const formData = new FormData();
            formData.append('action', 'google_login');
            formData.append('credential', response.credential);

            fetch('auth_mysql', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    alert(data.error || 'Google login failed.');
                }
            })
            .catch(err => {
                console.error(err);
                alert('Connection error');
            });
        }
    </script>
</body>
</html>
