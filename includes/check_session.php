<?php
require_once __DIR__ . '/session_boot.php';
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/session_validity.php';

// A session older than the account's last password/email change is signed out
if (isset($_SESSION['user_id']) && tm_session_revoked()) {
    tm_session_drop();
}

// 1. Check if session exists
if (isset($_SESSION['user_id'])) {
    echo json_encode(['loggedIn' => true]);
    exit;
}

// 2. Check if remember_me cookie exists and is valid
if (isset($_COOKIE['remember_me'])) {
    require_once 'db_mysql.php';
    [$selector, $validator] = array_pad(explode(':', (string)$_COOKIE['remember_me'], 2), 2, '');
    
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM user_tokens WHERE selector = ? AND expires_at > NOW()");
        $stmt->execute([$selector]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($token && password_verify($validator, $token['hashed_validator'])) {
            // Token is valid, restore session
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$token['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                tm_session_mark_authenticated($pdo);
                
                echo json_encode(['loggedIn' => true]);
                exit;
            }
        }
    } catch (Exception $e) {
        // Ignore errors
    }
}

// 3. Not logged in
echo json_encode(['loggedIn' => false]);
?>
