<?php
/**
 * CSRF Token Management
 * 
 * Provides functions for generating and validating CSRF tokens
 * to protect against Cross-Site Request Forgery attacks.
 */

/**
 * Generates a CSRF token and stores it in the session.
 * Returns existing token if already generated for this session.
 * 
 * @return string The CSRF token
 */
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Validates a submitted CSRF token against the session token.
 * Uses hash_equals for timing-safe comparison.
 * 
 * @param string $token The token to validate
 * @return bool True if valid, false otherwise
 */
function validateCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Returns a hidden input field with the CSRF token for use in forms.
 * 
 * @return string HTML hidden input element
 */
function getCSRFInput() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRFToken()) . '">';
}

/**
 * Validates CSRF token from request (checks both POST and header).
 * Returns JSON error and exits if invalid.
 */
function requireCSRFToken() {
    // Check POST parameter first
    $token = $_POST['csrf_token'] ?? null;
    
    // Fall back to header (for AJAX requests)
    if (!$token) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    }
    
    if (!validateCSRFToken($token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid or missing security token. Please refresh and try again.']);
        exit;
    }
}

/**
 * API endpoint to get CSRF token for JavaScript.
 * Only responds if accessed directly with action=get_token.
 */
if (isset($_GET['action']) && $_GET['action'] === 'get_token') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    header('Content-Type: application/json');
    echo json_encode(['token' => generateCSRFToken()]);
    exit;
}
?>
