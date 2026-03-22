<?php
session_start();

// If the user is not logged in, check for remember me cookie
if (!isset($_SESSION['user_id'])) {
    if (isset($_COOKIE['remember_me'])) {
        $cookie_val = $_COOKIE['remember_me'];
        
        // Validation: Format is selector:validator
        // Selector is 16 bytes (32 hex chars), Validator is 32 bytes (64 hex chars)
        if (strlen($cookie_val) < 40 || strpos($cookie_val, ':') === false) {
            // Invalid cookie format, ignore
            return;
        }

        list($selector, $validator) = explode(':', $cookie_val);
        
        // Strict pattern validation to prevent SQLi and bypass attempts
        if (!preg_match('/^[a-f0-9]{32}$/i', $selector) || !preg_match('/^[a-f0-9]{64}$/i', $validator)) {
            // Malformed token components
            return;
        }

        require_once 'db_mysql.php';
        
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
    
    // Regular page request - redirect to login using absolute path
    // Use the directory of the entry script (not the included file) to build the correct base path
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $loginUrl = $scriptDir . '/login';
    
    // Prevent infinite redirect loop: if we're already trying to load the login page, stop
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (basename($currentPath) === 'login' || basename($currentPath) === 'login.php') {
        // We're already on the login page but somehow check_auth.php was included - show error instead of looping
        http_response_code(403);
        echo 'Authentication required. <a href="' . htmlspecialchars($loginUrl) . '">Click here to log in</a>.';
        exit;
    }
    
    header('Location: ' . $loginUrl);
    exit;
}