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
    <title>Register - TutorMind</title>
    <link rel="icon" type="image/png" href="assets/favicon.png">
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="landing.css?v=2">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body>
    
    <div class="auth-wrapper">
        <!-- Left Side: Register Form -->
        <div class="auth-form-side">
             <div class="auth-header" style="text-align: left; margin-bottom: 2rem;">
                <a href="index" class="auth-brand" style="justify-content: flex-start;">
                    🧠 TutorMind
                </a>
                <h1 class="auth-title" style="font-size: 2rem;">Create Account</h1>
                <p class="auth-subtitle">Unlock your full potential with personal AI tutoring.</p>
            </div>

            <form id="registerForm" class="auth-form" style="max-width: 100%;" action="auth_mysql" method="POST">
                <input type="hidden" name="csrf_token" id="csrf_token" value="">

                <div class="form-group">
                    <label for="fullName" class="form-label">Full Name</label>
                    <div class="input-wrapper">
                        <input type="text" id="fullName" name="fullName" class="form-input" placeholder="e.g. John Doe" autocomplete="name">
                    </div>
                    <small class="error-message"></small>
                </div>

                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-wrapper">
                        <input type="text" id="username" name="username" class="form-input" placeholder="Choose a username" autocomplete="username" required>
                    </div>
                    <small class="error-message"></small>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <div class="input-wrapper">
                        <input type="email" id="email" name="email" class="form-input" placeholder="name@example.com" autocomplete="email" spellcheck="false" required>
                    </div>
                    <small class="error-message"></small>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" class="form-input" placeholder="Create a password" autocomplete="new-password" required>
                        <button type="button" class="password-toggle" aria-label="Toggle password visibility" style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--text-secondary); background: none; border: none; padding: 0;">
                            <i class="fas fa-eye-slash" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="password-strength">
                        <div class="password-strength-bar"></div>
                    </div>
                    <small class="strength-text"></small>
                    <small class="error-message"></small>
                </div>

                <div class="form-group">
                    <label for="confirmPassword" class="form-label">Confirm Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="confirmPassword" name="confirmPassword" class="form-input" placeholder="Confirm your password" autocomplete="new-password" required>
                    </div>
                    <small class="error-message"></small>
                </div>

                <div class="form-group" style="display: flex; gap: 10px; align-items: flex-start;">
                    <input type="checkbox" id="terms" name="terms" style="margin-top: 5px; accent-color: var(--primary-ink);" required>
                    <label for="terms" style="font-weight: 400; font-size: 0.9rem;">
                        I agree to the <a href="#" style="color: var(--accent-purple); font-weight: 600;">Terms & Conditions</a> and Privacy Policy.
                    </label>
                </div>

                <button type="submit" id="createAccountBtn" class="btn-auth" disabled style="opacity: 0.7; cursor: not-allowed;">Create Account</button>

                <div class="auth-divider">
                    <span>Or sign up with</span>
                </div>

                <!-- Google Sign In Button - Redirect Mode for HTTPS -->
                <div id="g_id_onload"
                     data-client_id="1083917773706-gc0f400l24eavps3ckcnj04581gj3plk.apps.googleusercontent.com"
                     data-context="signup" 
                     data-ux_mode="redirect"
                     data-login_uri="https://localhost/TutorMind/auth_mysql.php"
                     data-auto_prompt="false">
                </div>
                <div class="g_id_signin" data-type="standard" data-shape="rectangular" data-theme="outline"
                     data-text="signup_with" data-size="large" data-logo_alignment="left" data-width="100%">
                </div>

                <p style="text-align: center; margin-top: 1.5rem;">
                    Already have an account? <a href="login" style="color: var(--primary-ink); font-weight: 700;">Log in</a>
                </p>
            </form>
        </div>

        <!-- Right Side: Creative Visual -->
        <div class="auth-visual-side">
            <div class="auth-polaroid" style="transform: rotate(2deg);">
                <div class="auth-tape" style="transform: translateX(-50%) rotate(-3deg);"></div>
                <!-- Updated Image -->
                <img src="assets/register_illustration.png" alt="Join TutorMind" class="auth-visual-img" width="518" height="345">
                <div class="math-bubble" style="bottom: -20px; right: -20px; background: var(--sticky-yellow); color: var(--text-primary); transform: rotate(5deg);">
                     <span style="font-weight: 700;">Welcome!</span> 👋
                </div>
            </div>
             <div class="equation-marker" style="top: 15%; left: 10%; opacity: 0.1;">
                \( e^{i\pi} + 1 = 0 \)
            </div>
            <div class="equation-marker" style="bottom: 10%; right: 10%; opacity: 0.1;">
                \( \nabla \times \mathbf{B} = \mu_0 \mathbf{J} \)
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

     <!-- Theme Script -->
     <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (localStorage.getItem('tutormind-theme') === 'dark') {
                document.body.classList.add('dark-mode');
            }
        });
    </script>

    <!-- Validation & Logic -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const registerForm = document.getElementById('registerForm');
            const fullNameInput = document.getElementById('fullName');
            const usernameInput = document.getElementById('username');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirmPassword');
            const termsCheckbox = document.getElementById('terms');
            const createAccountBtn = document.getElementById('createAccountBtn');
            const passwordToggle = document.querySelector('.password-toggle');
            
            const strengthBar = document.querySelector('.password-strength-bar');
            const strengthText = document.querySelector('.strength-text');

            // Password Toggle
             if(passwordToggle) {
                passwordToggle.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    confirmPasswordInput.setAttribute('type', type); // Toggle both
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                });
             }

            // Check for URL errors (e.g. from Google Redirect)
            const urlParams = new URLSearchParams(window.location.search);
            const errorMsg = urlParams.get('error');
            if (errorMsg) {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-banner';
                errorDiv.style.cssText = 'background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; border: 1px solid #fecaca; display: flex; align-items: center; gap: 0.5rem;';
                errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> <span>' + decodeURIComponent(errorMsg).replace(/</g, "&lt;").replace(/>/g, "&gt;") + '</span>';
                
                registerForm.insertBefore(errorDiv, registerForm.firstChild);
                
                // Clean URL
                window.history.replaceState({}, document.title, window.location.pathname);
            }

            const validators = {
                username: {
                    validate: () => /^[a-zA-Z0-9_]{3,15}$/.test(usernameInput.value),
                    message: "Username must be 3-15 chars (letters, numbers, _)"
                },
                email: {
                    validate: () => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value),
                    message: "Invalid email address"
                },
                password: {
                    validate: () => passwordInput.value.length >= 8,
                    message: "Password must be 8+ chars"
                },
                confirmPassword: {
                    validate: () => confirmPasswordInput.value === passwordInput.value && confirmPasswordInput.value.length > 0,
                    message: "Passwords do not match"
                },
                terms: {
                    validate: () => termsCheckbox.checked,
                    message: "" 
                }
            };

            const checkFieldValidity = (field, validator) => {
                const inputWrapper = field.closest('.input-wrapper') || field.parentElement;
                const errorMsg = field.closest('.form-group').querySelector('.error-message');
                
                if (validator.validate()) {
                    if(inputWrapper.classList.contains('input-wrapper')) inputWrapper.classList.remove('error');
                    if(errorMsg) errorMsg.textContent = '';
                    return true;
                } else {
                     // Only show error if field is dirty (not empty) or forced
                    if(field.value.length > 0 || field === termsCheckbox) {
                         if(inputWrapper.classList.contains('input-wrapper')) inputWrapper.classList.add('error');
                         if(errorMsg) errorMsg.textContent = validator.message;
                    }
                    return false;
                }
            };

            const checkFormValidity = () => {
                let isFormValid = true;
                for (const key in validators) {
                     // We check all logic
                    if (!validators[key].validate()) {
                        isFormValid = false;
                        break;
                    }
                }
                createAccountBtn.disabled = !isFormValid;
                createAccountBtn.style.opacity = isFormValid ? '1' : '0.7';
                createAccountBtn.style.cursor = isFormValid ? 'pointer' : 'not-allowed';
            };

            const checkPasswordStrength = () => {
                const password = passwordInput.value;
                let score = 0;
                if (password.length >= 8) score++;
                if (/[A-Z]/.test(password)) score++;
                if (/[0-9]/.test(password)) score++;
                if (/[^A-Za-z0-9]/.test(password)) score++;

                strengthBar.className = 'password-strength-bar';
                if (password.length === 0) {
                    strengthBar.style.width = '0%';
                    strengthText.textContent = '';
                } else if (score < 2) {
                    strengthBar.style.width = '33%';
                    strengthBar.classList.add('weak');
                    strengthText.textContent = 'Weak';
                    strengthText.style.color = '#EF4444';
                } else if (score < 4) {
                    strengthBar.style.width = '66%';
                    strengthBar.classList.add('medium');
                    strengthText.textContent = 'Medium';
                    strengthText.style.color = 'var(--accent-gold)';
                } else {
                    strengthBar.style.width = '100%';
                    strengthBar.classList.add('strong');
                    strengthText.textContent = 'Strong';
                    strengthText.style.color = '#10B981';
                }
            };

            [usernameInput, emailInput, passwordInput, confirmPasswordInput].forEach(input => {
                input.addEventListener('input', () => {
                    // Find key based on id
                    const key = input.id; 
                    if(validators[key]) checkFieldValidity(input, validators[key]);
                    if(input === passwordInput) {
                        checkPasswordStrength();
                        if(confirmPasswordInput.value) checkFieldValidity(confirmPasswordInput, validators.confirmPassword);
                    }
                    checkFormValidity();
                });
            });

            termsCheckbox.addEventListener('change', () => {
                 checkFormValidity();
            });

            // ==================== FORM SUBMISSION ====================
            // Fetch CSRF Token on page load
            fetch('csrf.php?action=get_token')
                .then(res => res.json())
                .then(data => {
                    if (data.token) {
                        document.getElementById('csrf_token').value = data.token;
                    }
                })
                .catch(err => console.error('CSRF token fetch error:', err));

            // Handle form submission
            registerForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                // Final validation check
                let isValid = true;
                for (const key in validators) {
                    const field = key === 'terms' ? termsCheckbox : document.getElementById(key);
                    if (!checkFieldValidity(field, validators[key])) {
                        isValid = false;
                    }
                }

                if (!isValid) {
                    alert('Please fix the errors before submitting.');
                    return;
                }

                // Parse full name into first and last
                const fullName = fullNameInput.value.trim();
                const nameParts = fullName.split(' ');
                const firstName = nameParts[0] || '';
                const lastName = nameParts.slice(1).join(' ') || nameParts[0]; // Fallback to first name if no last

                // Prepare form data
                const formData = new FormData();
                formData.append('action', 'register'); // CRITICAL: This tells the backend what to do
                formData.append('firstName', firstName);
                formData.append('lastName', lastName);
                formData.append('username', usernameInput.value);
                formData.append('email', emailInput.value);
                formData.append('password', passwordInput.value);
                formData.append('csrf_token', document.getElementById('csrf_token').value);

                // Disable button and show loading
                createAccountBtn.disabled = true;
                createAccountBtn.textContent = 'Creating Account...';

                try {
                    const response = await fetch('auth_mysql', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success && data.redirect) {
                        createAccountBtn.textContent = '✓ Success! Redirecting...';
                        createAccountBtn.style.background = '#10B981';
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 500);
                    } else {
                        throw new Error(data.error || 'Registration failed');
                    }
                } catch (error) {
                    console.error('Registration error:', error);
                    alert(error.message || 'An error occurred. Please try again.');
                    createAccountBtn.disabled = false;
                    createAccountBtn.textContent = 'Create Account';
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
                    alert(data.error || 'Google signup failed.');
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
