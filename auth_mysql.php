<?php
require_once __DIR__ . '/includes/session_boot.php'; // hardened cookie params, before session_start()
session_start();
header('Content-Type: application/json');
require_once 'includes/db_mysql.php';
require_once 'includes/csrf.php';
require_once 'includes/rate_limiter.php';
require_once 'includes/session_validity.php';
require_once 'includes/signup_throttle.php';

$request_method = $_SERVER['REQUEST_METHOD'];

switch ($request_method) {
    case 'GET':
        $action = $_GET['action'] ?? '';
        if ($action === 'logout') {
            // Clear Remember Me
            if (isset($_COOKIE['remember_me'])) {
                [$selector, $validator] = array_pad(explode(':', (string)$_COOKIE['remember_me'], 2), 2, '');
                try {
                    $pdo = getDbConnection();
                    $stmt = $pdo->prepare("DELETE FROM user_tokens WHERE selector = ?");
                    $stmt->execute([$selector]);
                } catch (Exception $e) {
                    // Ignore error on logout
                }
                setcookie('remember_me', '', [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }

            session_unset();
            session_destroy();
            header('Location: login');
            exit;
        }
        break;

    case 'POST':
        $action = $_POST['action'] ?? '';
        $is_redirect_flow = false;
        
        // Handle Google Redirect Mode (which sends credential but no action)
        if (empty($action) && isset($_POST['credential']) && isset($_POST['g_csrf_token'])) {
            $action = 'google_login';
            $is_redirect_flow = true;

            // Google's redirect mode sets a g_csrf_token cookie and repeats it in the form:
            // they must match, or anyone could make a browser post a credential here.
            $csrfCookie = (string)($_COOKIE['g_csrf_token'] ?? '');
            if ($csrfCookie === '' || !hash_equals($csrfCookie, (string)$_POST['g_csrf_token'])) {
                header("Location: login?error=" . urlencode('Could not verify the Google sign-in request. Please try again.'));
                exit;
            }
        }
        
        if ($action === 'register') {
            // --- Registration Logic ---
            requireCSRFToken(); // Validate CSRF token
            
            $firstName = trim($_POST['firstName'] ?? '');
            $lastName = trim($_POST['lastName'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';

            if (empty($firstName) || empty($lastName) || empty($username) || empty($email) || empty($password)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'All fields are required.']);
                exit;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid email format.']);
                exit;
            }
            if (strlen($password) < 8) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters long.']);
                exit;
            }
            if (strlen($password) > 200) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'That password is too long.']);
                exit;
            }

            try {
                $pdo = getDbConnection();
                // Sign-ups per network are capped. Every request that got this far counts, taken
                // names included, so the duplicate check can't be used as a cheap lookup.
                if (tm_signup_throttled($pdo, getClientIP())) {
                    http_response_code(429);
                    echo json_encode(['success' => false, 'error' => 'Too many sign-ups from this network. Please try again later.']);
                    exit;
                }
                // One answer for "username taken" and "email taken", so it doesn't say which exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                if ($stmt->fetch()) {
                    http_response_code(409);
                    echo json_encode(['success' => false, 'error' => 'That username or email is already taken.']);
                    exit;
                }

                $password_hash = password_hash($password, PASSWORD_ARGON2ID);
                $local_theme = $_POST['local_theme'] ?? 'light';
                $dark_mode = ($local_theme === 'dark') ? 1 : 0;
                
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, dark_mode) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$username, $email, $password_hash, $firstName, $lastName, $dark_mode])) {
                    // Automatically log the user in
                    $user_id = $pdo->lastInsertId();
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['first_name'] = $firstName;
                    $_SESSION['last_name'] = $lastName;
                    tm_session_mark_authenticated($pdo);

                    // The address starts unverified (email_verified_at NULL). Nothing is blocked
                    // meanwhile, but until it's confirmed a Google sign-in on that address takes
                    // the account over rather than joining it (see google_login below).
                    require_once 'includes/email_verification.php';
                    sendVerificationEmail($pdo, (int)$user_id, $email, $firstName ?: $username);

                    echo json_encode(['success' => true, 'redirect' => 'onboarding']);
                } else {
                    throw new Exception("Failed to create user account.");
                }
            } catch (Exception $e) {
                error_log("Registration Error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'A server error occurred during registration.']);
            }

        } elseif ($action === 'login') {
            // --- Login Logic ---
            requireCSRFToken(); // Validate CSRF token
            
            $identifier = $_POST['email'] ?? ''; // Can be email or username
            $password = $_POST['password'] ?? '';
            $clientIP = getClientIP();

            if (empty($identifier) || empty($password)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Email and password are required.']);
                exit;
            }

            // Check rate limiting before processing login
            $rateLimit = isRateLimited($clientIP, $identifier);
            if ($rateLimit['limited']) {
                $minutes = ceil($rateLimit['retry_after'] / 60);
                http_response_code(429);
                echo json_encode([
                    'success' => false, 
                    'error' => "Too many login attempts. Please try again in $minutes minute(s).",
                    'retry_after' => $rateLimit['retry_after']
                ]);
                exit;
            }

            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("SELECT id, username, password_hash, first_name, last_name, onboarding_completed, dark_mode FROM users WHERE email = ? OR username = ?");
                $stmt->execute([$identifier, $identifier]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                // No such account: still do a password check against a throwaway hash, so a
                // missing account doesn't answer faster than a wrong password. (A fixed Argon2id
                // hash of a random string nobody knows, same parameters as real ones.)
                $hashToCheck = $user ? $user['password_hash'] : '$argon2id$v=19$m=65536,t=4,p=1$N1RBR21BV2JtZlB2cld6ZA$LEw2KQnkm2LUNvNhRN9XvM+J2uBePc+BA8Hqf0UpGLI';
                $passwordOk = password_verify($password, $hashToCheck);

                if ($user && $passwordOk) {
                    // Clear failed attempts on successful login
                    clearLoginAttempts($clientIP, $identifier);
                    
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    tm_session_mark_authenticated($pdo);

                    // --- Remember Me Logic ---
                    if (isset($_POST['remember'])) {
                        $selector = bin2hex(random_bytes(16));
                        $validator = bin2hex(random_bytes(32));
                        $hashed_validator = password_hash($validator, PASSWORD_DEFAULT);
                        $expires_at = date('Y-m-d H:i:s', time() + 86400 * 30); // 30 days

                        $stmt = $pdo->prepare("INSERT INTO user_tokens (user_id, selector, hashed_validator, expires_at) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$user['id'], $selector, $hashed_validator, $expires_at]);

                        // Set cookie: selector:validator with SameSite
                        setcookie('remember_me', "$selector:$validator", [
                            'expires' => time() + 86400 * 30,
                            'path' => '/',
                            'secure' => true,
                            'httponly' => true,
                            'samesite' => 'Lax'
                        ]);
                    }

                    // Redirect based on onboarding completion status
                    $redirect = ($user['onboarding_completed']) ? 'chat' : 'onboarding';
                    $db_theme = ($user['dark_mode'] ?? false) ? 'dark' : 'light';
                    echo json_encode(['success' => true, 'redirect' => $redirect, 'db_theme' => $db_theme]);
                } else {
                    // Record failed attempt for rate limiting
                    recordFailedAttempt($clientIP, $identifier);
                    
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Invalid email or password.']);
                }
            } catch (Exception $e) {
                error_log("Login Error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'A server error occurred during login.']);
            }

        } elseif ($action === 'change_password') {
            // --- Password Change Logic ---
            requireCSRFToken(); // Validate CSRF token
            
            if (!isset($_SESSION['user_id'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'You must be logged in to change your password.']);
                exit;
            }

            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';

            if (empty($current_password) || empty($new_password)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'All password fields are required.']);
                exit;
            }
            if (strlen($new_password) < 8) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'New password must be at least 8 characters long.']);
                exit;
            }
            if (strlen($new_password) > 200) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'That password is too long.']);
                exit;
            }

            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user || !password_verify($current_password, $user['password_hash'])) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Your current password is not correct.']);
                    exit;
                }

                $new_password_hash = password_hash($new_password, PASSWORD_ARGON2ID);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                if ($stmt->execute([$new_password_hash, $_SESSION['user_id']])) {
                    // Whoever knew the old password shouldn't stay signed in on other devices
                    $pdo->prepare("DELETE FROM user_tokens WHERE user_id = ?")->execute([$_SESSION['user_id']]);
                    // ...and neither should their other open sessions; this one is re-stamped to survive
                    tm_invalidate_sessions($pdo, (int)$_SESSION['user_id'], true, false);
                    session_regenerate_id(true);
                    echo json_encode(['success' => true, 'message' => 'Password updated successfully!']);
                } else {
                    throw new Exception("Failed to update password.");
                }
            } catch (Exception $e) {
                error_log("Password Change Error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'An unexpected server error occurred.']);
            }
        } elseif ($action === 'google_login') {
            // --- Google Login Logic ---
            error_log("AUTH_MYSQL: Google login action started");
            $credential = $_POST['credential'] ?? '';

            if (empty($credential)) {
                error_log("AUTH_MYSQL: No credential provided");
                if ($is_redirect_flow) {
                    header("Location: login?error=" . urlencode('No credential provided.'));
                    exit;
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'No credential provided.']);
                }
                exit;
            }

            // Verify with Google: TLS, audience = TutorMind, issuer, expiry (includes/google_auth.php)
            require_once 'includes/google_auth.php';
            try {
                $verified = verifyGoogleIdToken($credential);
            } catch (RuntimeException $e) {
                error_log("Google Login Error: " . $e->getMessage());
                $msg = 'Could not reach Google to confirm your sign-in. Please try again.';
                if ($is_redirect_flow) {
                    header("Location: login?error=" . urlencode($msg));
                } else {
                    http_response_code(502);
                    echo json_encode(['success' => false, 'error' => $msg]);
                }
                exit;
            }
            $payload = $verified['payload'] ?? null;

            if (!$verified) {
                if ($is_redirect_flow) {
                    header("Location: login?error=" . urlencode('Invalid Google token.'));
                    exit;
                } else {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'error' => 'Invalid Google token.']);
                }
                exit;
            }

            // Token is valid, get user info
            $google_id = $payload['sub'];
            $email = $payload['email'];
            $first_name = $payload['given_name'] ?? '';
            $last_name = $payload['family_name'] ?? '';
            $avatar_url = $payload['picture'] ?? '';
            $username = explode('@', $email)[0]; // Default username from email
            
            error_log("AUTH_MYSQL: Extracted user info - email: $email, google_id: $google_id");

            try {
                error_log("AUTH_MYSQL: Getting DB connection...");
                $pdo = getDbConnection();
                error_log("AUTH_MYSQL: DB connection obtained");
                
                // Find the account: by Google ID first; by email only if Google has
                // verified that address — otherwise anyone could create a Google
                // account with someone else's email and walk into their account.
                $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ?");
                $stmt->execute([$google_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $matchedByEmail = false;
                if (!$user) {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    $byEmail = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($byEmail && !$verified['email_verified']) {
                        $msg = 'This email already has a TutorMind account. Verify the email on your Google account, or log in with your password.';
                        if ($is_redirect_flow) {
                            header("Location: login?error=" . urlencode($msg));
                        } else {
                            http_response_code(409);
                            echo json_encode(['success' => false, 'error' => $msg]);
                        }
                        exit;
                    }
                    $user = $byEmail;
                    $matchedByEmail = (bool)$byEmail;
                }

                if ($user) {
                    // User exists - Update google_id, avatar, and names if missing
                    $update_fields = [];
                    $params = [];

                    // A password account whose address nobody ever confirmed (email_verified_at is
                    // NULL; the key is absent before migration 023, which keeps the old behaviour)
                    // may have been registered by someone who doesn't own the address. Google has
                    // just vouched for the real owner, so the account is theirs now: kill the
                    // password it was registered with and every remember-me login before joining it.
                    if ($matchedByEmail && empty($user['google_id'])
                        && array_key_exists('email_verified_at', $user) && $user['email_verified_at'] === null) {
                        $pdo->beginTransaction();
                        try {
                            $pdo->prepare("UPDATE users SET password_hash = ?, email_verified_at = NOW(), updated_at = NOW() WHERE id = ?")
                                ->execute([password_hash(bin2hex(random_bytes(32)), PASSWORD_ARGON2ID), $user['id']]);
                            $pdo->prepare("DELETE FROM user_tokens WHERE user_id = ?")->execute([$user['id']]);
                            $pdo->commit();
                        } catch (Throwable $e) {
                            if ($pdo->inTransaction()) $pdo->rollBack();
                            throw $e;
                        }
                        // Whoever registered it may still have a session open: sign those out too
                        // (this request's session is the real owner's and is stamped below)
                        tm_invalidate_sessions($pdo, (int)$user['id'], false, false);
                        error_log("AUTH_MYSQL: unverified password account {$user['id']} taken over by its Google-verified owner");
                    }

                    // Update Google ID if missing
                    if (empty($user['google_id'])) {
                        $update_fields[] = "google_id = ?";
                        $params[] = $google_id;
                    }

                    // Update Avatar if missing or changed
                    if (empty($user['avatar_url']) || $user['avatar_url'] !== $avatar_url) {
                        $update_fields[] = "avatar_url = ?";
                        $params[] = $avatar_url;
                    }

                    // Update First Name if missing in DB
                    $final_first_name = $user['first_name'];
                    if (empty($user['first_name']) && !empty($first_name)) {
                        $update_fields[] = "first_name = ?";
                        $params[] = $first_name;
                        $final_first_name = $first_name;
                    }

                    // Update Last Name if missing in DB
                    $final_last_name = $user['last_name'];
                    if (empty($user['last_name']) && !empty($last_name)) {
                        $update_fields[] = "last_name = ?";
                        $params[] = $last_name;
                        $final_last_name = $last_name;
                    }

                    if (!empty($update_fields)) {
                        $params[] = $user['id'];
                        $sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                    }

                    // Log the user in
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['first_name'] = $final_first_name;
                    $_SESSION['last_name'] = $final_last_name;
                    $_SESSION['avatar_url'] = $avatar_url; // Store avatar in session
                    tm_session_mark_authenticated($pdo);

                    $redirect = ($user['onboarding_completed']) ? 'chat' : 'onboarding';
                    $db_theme = ($user['dark_mode'] ?? false) ? 'dark' : 'light';
                    if ($is_redirect_flow) {
                        header("Location: " . $redirect);
                        exit;
                    } else {
                        echo json_encode(['success' => true, 'redirect' => $redirect, 'db_theme' => $db_theme]);
                    }

                } else {
                    // New user - Create account
                    // Ensure username is unique
                    $base_username = $username;
                    $counter = 1;
                    while (true) {
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                        $stmt->execute([$username]);
                        if (!$stmt->fetch()) break;
                        $username = $base_username . $counter++;
                    }

                    // Use a random password for Google users (they can reset it later if they want)
                    $random_password = bin2hex(random_bytes(16));
                    $password_hash = password_hash($random_password, PASSWORD_ARGON2ID);
                    
                    $local_theme = $_POST['local_theme'] ?? 'light';
                    $dark_mode = ($local_theme === 'dark') ? 1 : 0;

                    $stmt = $pdo->prepare("INSERT INTO users (username, email, first_name, last_name, google_id, avatar_url, password_hash, dark_mode) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

                    if ($stmt->execute([$username, $email, $first_name, $last_name, $google_id, $avatar_url, $password_hash, $dark_mode])) {
                        $user_id = $pdo->lastInsertId();
                        // Google vouched for the address (skipped before migration 023 / if Google didn't)
                        require_once 'includes/email_verification.php';
                        if ($verified['email_verified'] && emailVerificationReady($pdo)) {
                            $pdo->prepare("UPDATE users SET email_verified_at = NOW() WHERE id = ?")->execute([$user_id]);
                        }
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['username'] = $username;
                        $_SESSION['first_name'] = $first_name;
                        $_SESSION['last_name'] = $last_name;
                        $_SESSION['avatar_url'] = $avatar_url;
                        tm_session_mark_authenticated($pdo);

                        if ($is_redirect_flow) {
                            header("Location: onboarding");
                            exit;
                        } else {
                            echo json_encode(['success' => true, 'redirect' => 'onboarding']);
                        }
                    } else {
                        throw new Exception("Failed to create user account.");
                    }
                }

            } catch (Exception $e) {
                error_log("Google Login Error: " . $e->getMessage());
                if ($is_redirect_flow) {
                    header("Location: login?error=" . urlencode('Server error during Google login. Please try again.'));
                    exit;
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Server error during Google login. Please try again.']);
                }
            }

        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid POST action specified.']);
        }
        break;

    default:
        http_response_code(405); // Method Not Allowed
        echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
        break;
}

function send_json_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

