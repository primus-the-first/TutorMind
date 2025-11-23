<?php
session_start();
header('Content-Type: application/json');
require_once 'db_mysql.php';

$request_method = $_SERVER['REQUEST_METHOD'];

switch ($request_method) {
    case 'GET':
        $action = $_GET['action'] ?? '';
        if ($action === 'logout') {
            session_unset();
            session_destroy();
            header('Location: login.html');
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

                    echo json_encode(['success' => true, 'redirect' => 'onboarding.php']);
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
                    // Redirect based on onboarding completion status
                    $redirect = ($user['onboarding_completed']) ? 'tutor_mysql.php' : 'onboarding.php';
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
