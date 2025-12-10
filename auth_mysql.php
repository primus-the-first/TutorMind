<?php
session_start();
header('Content-Type: application/json');
require_once 'db_mysql.php';

$request_method = $_SERVER['REQUEST_METHOD'];

switch ($request_method) {
    case 'GET':
        $action = $_GET['action'] ?? '';
        if ($action === 'logout') {
            // Clear Remember Me
            if (isset($_COOKIE['remember_me'])) {
                list($selector, $validator) = explode(':', $_COOKIE['remember_me']);
                try {
                    $pdo = getDbConnection();
                    $stmt = $pdo->prepare("DELETE FROM user_tokens WHERE selector = ?");
                    $stmt->execute([$selector]);
                } catch (Exception $e) {
                    // Ignore error on logout
                }
                setcookie('remember_me', '', time() - 3600, '/', '', true, true);
            }

            session_unset();
            session_destroy();
            header('Location: login');
            exit;
        }
        break;

    case 'POST':
        $action = $_POST['action'] ?? '';
        if ($action === 'register') {
            // --- Registration Logic ---
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

            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                if ($stmt->fetch()) {
                    http_response_code(409);
                    echo json_encode(['success' => false, 'error' => 'That username or email is already taken.']);
                    exit;
                }

                $password_hash = password_hash($password, PASSWORD_ARGON2ID);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$username, $email, $password_hash, $firstName, $lastName])) {
                    // Automatically log the user in
                    $user_id = $pdo->lastInsertId();
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['first_name'] = $firstName;
                    $_SESSION['last_name'] = $lastName;

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
            $identifier = $_POST['email'] ?? ''; // Can be email or username
            $password = $_POST['password'] ?? '';

            if (empty($identifier) || empty($password)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Email and password are required.']);
                exit;
            }

            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("SELECT id, username, password_hash, first_name, last_name, onboarding_completed FROM users WHERE email = ? OR username = ?");
                $stmt->execute([$identifier, $identifier]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password_hash'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];

                    // --- Remember Me Logic ---
                    if (isset($_POST['remember'])) {
                        $selector = bin2hex(random_bytes(16));
                        $validator = bin2hex(random_bytes(32));
                        $hashed_validator = password_hash($validator, PASSWORD_DEFAULT);
                        $expires_at = date('Y-m-d H:i:s', time() + 86400 * 30); // 30 days

                        $stmt = $pdo->prepare("INSERT INTO user_tokens (user_id, selector, hashed_validator, expires_at) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$user['id'], $selector, $hashed_validator, $expires_at]);

                        // Set cookie: selector:validator
                        setcookie('remember_me', "$selector:$validator", time() + 86400 * 30, '/', '', true, true);
                    }

                    // Redirect based on onboarding completion status
                    $redirect = ($user['onboarding_completed']) ? 'chat' : 'onboarding';
                    echo json_encode(['success' => true, 'redirect' => $redirect]);
                } else {
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
            $credential = $_POST['credential'] ?? '';

            if (empty($credential)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'No credential provided.']);
                exit;
            }

            // Verify the token with Google
            $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $credential;
            $response = file_get_contents($url);
            $payload = json_decode($response, true);

            if (!$payload || isset($payload['error_description'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Invalid Google token.']);
                exit;
            }

            // Token is valid, get user info
            $google_id = $payload['sub'];
            $email = $payload['email'];
            $first_name = $payload['given_name'] ?? '';
            $last_name = $payload['family_name'] ?? '';
            $avatar_url = $payload['picture'] ?? '';
            $username = explode('@', $email)[0]; // Default username from email

            try {
                $pdo = getDbConnection();
                
                // Check if user exists by google_id or email
                $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ? OR email = ?");
                $stmt->execute([$google_id, $email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    // User exists - Update google_id, avatar, and names if missing
                    $update_fields = [];
                    $params = [];

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

                    $redirect = ($user['onboarding_completed']) ? 'chat' : 'onboarding';
                    echo json_encode(['success' => true, 'redirect' => $redirect]);

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

                    $stmt = $pdo->prepare("INSERT INTO users (username, email, first_name, last_name, google_id, avatar_url, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    // Use a random password for Google users (they can reset it later if they want)
                    $random_password = bin2hex(random_bytes(16));
                    $password_hash = password_hash($random_password, PASSWORD_ARGON2ID);
                    
                    if ($stmt->execute([$username, $email, $first_name, $last_name, $google_id, $avatar_url, $password_hash])) {
                        $user_id = $pdo->lastInsertId();
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['username'] = $username;
                        $_SESSION['first_name'] = $first_name;
                        $_SESSION['last_name'] = $last_name;
                        $_SESSION['avatar_url'] = $avatar_url;

                        echo json_encode(['success' => true, 'redirect' => 'onboarding']);
                    } else {
                        throw new Exception("Failed to create user account.");
                    }
                }

            } catch (Exception $e) {
                error_log("Google Login Error: " . $e->getMessage());
                http_response_code(500);
                // Return the actual error for debugging (remove in production later if sensitive)
                echo json_encode(['success' => false, 'error' => 'Server error during Google login: ' . $e->getMessage()]);
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
?>
