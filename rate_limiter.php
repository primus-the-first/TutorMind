<?php
/**
 * Rate Limiter for Login Attempts
 * 
 * Protects against brute force attacks by limiting failed login attempts
 * per IP address and username combination.
 */

require_once 'db_mysql.php';

/**
 * Configuration
 */
define('RATE_LIMIT_MAX_ATTEMPTS', 5);
define('RATE_LIMIT_WINDOW_SECONDS', 15 * 60); // 15 minutes

/**
 * Check if a login attempt is rate limited.
 * 
 * @param string $ip The IP address
 * @param string $username The username/email being attempted
 * @return array ['limited' => bool, 'remaining' => int, 'retry_after' => int|null]
 */
function isRateLimited($ip, $username) {
    try {
        $pdo = getDbConnection();
        $window = RATE_LIMIT_WINDOW_SECONDS;
        
        // Count recent attempts for this IP or username
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempts, MIN(attempt_time) as first_attempt
            FROM login_attempts 
            WHERE (ip_address = ? OR username = ?) 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$ip, $username, $window]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $attempts = (int)($result['attempts'] ?? 0);
        $remaining = max(0, RATE_LIMIT_MAX_ATTEMPTS - $attempts);
        
        if ($attempts >= RATE_LIMIT_MAX_ATTEMPTS) {
            // Calculate when they can retry
            $firstAttempt = strtotime($result['first_attempt']);
            $retryAfter = $firstAttempt + $window - time();
            
            return [
                'limited' => true,
                'remaining' => 0,
                'retry_after' => max(0, $retryAfter)
            ];
        }
        
        return [
            'limited' => false,
            'remaining' => $remaining,
            'retry_after' => null
        ];
        
    } catch (Exception $e) {
        error_log("Rate limiter error: " . $e->getMessage());
        // Fail open - don't block on DB errors
        return ['limited' => false, 'remaining' => RATE_LIMIT_MAX_ATTEMPTS, 'retry_after' => null];
    }
}

/**
 * Record a failed login attempt.
 * 
 * @param string $ip The IP address
 * @param string $username The username/email attempted
 */
function recordFailedAttempt($ip, $username) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, username) VALUES (?, ?)");
        $stmt->execute([$ip, $username]);
    } catch (Exception $e) {
        error_log("Failed to record login attempt: " . $e->getMessage());
    }
}

/**
 * Clear login attempts after successful login.
 * 
 * @param string $ip The IP address
 * @param string $username The username/email
 */
function clearLoginAttempts($ip, $username) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? OR username = ?");
        $stmt->execute([$ip, $username]);
    } catch (Exception $e) {
        error_log("Failed to clear login attempts: " . $e->getMessage());
    }
}

/**
 * Clean up old login attempts (call periodically or via cron).
 */
function cleanupOldAttempts() {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 1 DAY)");
        $stmt->execute();
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("Failed to cleanup login attempts: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get client IP address (handles proxies).
 * 
 * @return string The client IP address
 */
function getClientIP() {
    // Check for proxy headers (be careful with these in production)
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            // X-Forwarded-For can contain multiple IPs, take the first one
            $ip = explode(',', $_SERVER[$header])[0];
            $ip = trim($ip);
            
            // Validate it's a proper IP
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
?>
