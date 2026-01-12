<?php
session_start();

// If the user is not logged in, check for remember me cookie
if (!isset($_SESSION['user_id'])) {
    if (isset($_COOKIE['remember_me'])) {
        require_once 'db_mysql.php';
        list($selector, $validator) = explode(':', $_COOKIE['remember_me']);
        
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SELECT * FROM user_tokens WHERE selector = ? AND expires_at > NOW()");
            $stmt->execute([$selector]);
            $token = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($token && password_verify($validator, $token['hashed_validator'])) {
                // Token is valid, fetch user details
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$token['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    // Success! User is logged in.
                    return; 
                }
            }
        } catch (Exception $e) {
            // Database error, ignore and redirect
        }
    }

    // If we are here, authentication failed
    // Check if this is an API request (expects JSON)
    $isApiRequest = (
        isset($_GET['action']) || 
        isset($_POST['action']) ||
        (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
        (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) ||
        (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false) ||
        (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    );
    
    if ($isApiRequest) {
        // Return JSON error for API requests
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentication required. Please log in.']);
        exit;
    }
    
    // Regular page request - redirect to login
    header('Location: login');
    exit;
}