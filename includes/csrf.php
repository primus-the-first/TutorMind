<?php
/**
 * CSRF Token Management
 * 
 * Provides functions for generating and validating CSRF tokens
 * to protect against Cross-Site Request Forgery attacks.
 */

// Cookie params (httponly/samesite/strict mode) must be set before any session_start() below.
require_once __DIR__ . '/session_boot.php';

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
    $sessionToken = csrfSessionToken();

    if (empty($sessionToken) || empty($token) || !is_string($token)) {
        return false;
    }

    return hash_equals($sessionToken, $token);
}

/**
 * Reads the session's CSRF token without ever creating or changing it.
 * $_SESSION stays readable after session_write_close(), so the common path just
 * reads it. If the session was never opened in this request, open it only long
 * enough to read the token and release the lock again.
 *
 * @return string|null
 */
function csrfSessionToken() {
    if (!empty($_SESSION['csrf_token'])) {
        return $_SESSION['csrf_token'];
    }
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        session_start();
        $token = $_SESSION['csrf_token'] ?? null;
        session_write_close();
        return $token;
    }
    return null;
}

/**
 * Defence in depth: a request that names its origin must name ours. Compares the
 * host only (scheme and port are ignored, so http://localhost vs https behind a
 * proxy still match). Requests with neither Origin nor Referer are allowed; the
 * token is the real check. An opaque "null" origin has no host and is left to the
 * token check too.
 *
 * @return bool True when the origin is acceptable
 */
function csrfOriginAllowed() {
    $source = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($source === '') {
        $source = $_SERVER['HTTP_REFERER'] ?? '';
    }
    if ($source === '') {
        return true;
    }
    $sourceHost = parse_url($source, PHP_URL_HOST);
    if (!is_string($sourceHost) || $sourceHost === '') {
        return true;
    }
    $ownHost = (string)($_SERVER['HTTP_HOST'] ?? '');
    // Strip a port, leaving bracketed IPv6 literals intact
    $ownHost = preg_replace('/:\d+$/', '', $ownHost);
    $ownHost = trim($ownHost, '[]');
    $sourceHost = trim($sourceHost, '[]');
    if ($ownHost === '') {
        return true;
    }
    return strcasecmp($sourceHost, $ownHost) === 0;
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
    if (!csrfOriginAllowed()) {
        error_log("[csrf] rejected {$_SERVER['REQUEST_URI']} reason=origin_mismatch origin="
            . substr($_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '-'), 0, 200));
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid or missing security token. Please refresh and try again.']);
        exit;
    }

    // Check POST parameter first
    $token = $_POST['csrf_token'] ?? null;
    
    // Fall back to header (for AJAX requests)
    if (!$token) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    }
    
    if (!validateCSRFToken($token)) {
        // Say why, so a failure we can't reproduce can be told apart in the log:
        // no cookie = the browser isn't keeping our session cookie at all (in-app
        // browsers, data-saver proxies); no session token = session expired or
        // never got one; mismatch = a token from an older session (stale tab).
        $hasCookie = isset($_COOKIE[session_name()]);
        $reason = !$hasCookie ? 'no_session_cookie'
            : (empty($_SESSION['csrf_token']) ? 'no_session_token'
            : (empty($token) ? 'no_submitted_token' : 'mismatch'));
        error_log("[csrf] rejected {$_SERVER['REQUEST_URI']} reason=$reason action=" . ($_POST['action'] ?? '-')
            . ' ua=' . substr($_SERVER['HTTP_USER_AGENT'] ?? '-', 0, 200));
        if (function_exists('pulse_note')) {
            pulse_note("CSRF $reason · " . substr($_SERVER['HTTP_USER_AGENT'] ?? '-', 0, 120));
        }

        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => $hasCookie
            ? 'Invalid or missing security token. Please refresh and try again.'
            : 'Your browser isn’t keeping cookies for TutorMind, so we can’t keep you signed in. If you opened this link inside another app, open it in Chrome or Safari instead, or allow cookies for this site.']);
        exit;
    }
}

/**
 * Enforces the CSRF token on every state-changing request (POST/PUT/PATCH/DELETE);
 * a no-op for GET/HEAD/OPTIONS. Call it right after the session/auth setup and
 * before anything is written. Clients send the token in an X-CSRF-Token header
 * (or a csrf_token form field).
 */
function requireCSRFForWrites() {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        requireCSRFToken();
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

