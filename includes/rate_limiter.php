<?php
/**
 * Rate Limiter for Login Attempts
 * 
 * Protects against brute force attacks by limiting failed login attempts.
 * Three counters, so one person can't lock another out with a handful of tries:
 *   - this IP against this username:        5 in the window
 *   - this IP against anything:            20 in the window
 *   - this username from any IP at all:    15 in the window (higher, so a victim
 *     isn't locked out by five bad guesses, but a distributed guesser still stops)
 */

require_once 'db_mysql.php';

/**
 * Configuration
 */
define('RATE_LIMIT_MAX_ATTEMPTS', 5);
define('RATE_LIMIT_MAX_PER_IP', 20);
define('RATE_LIMIT_MAX_PER_USERNAME', 15);
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
        
        // Count recent failures three ways in one pass
        $stmt = $pdo->prepare("
            SELECT
                SUM(ip_address = ? AND username = ?) AS pair_attempts,
                MIN(CASE WHEN ip_address = ? AND username = ? THEN attempt_time END) AS pair_first,
                SUM(ip_address = ?) AS ip_attempts,
                MIN(CASE WHEN ip_address = ? THEN attempt_time END) AS ip_first,
                SUM(username = ?) AS user_attempts,
                MIN(CASE WHEN username = ? THEN attempt_time END) AS user_first
            FROM login_attempts
            WHERE (ip_address = ? OR username = ?)
            AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$ip, $username, $ip, $username, $ip, $ip, $username, $username, $ip, $username, $window]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $pair = (int)($result['pair_attempts'] ?? 0);
        $byIp = (int)($result['ip_attempts'] ?? 0);
        $byUser = (int)($result['user_attempts'] ?? 0);
        $remaining = max(0, RATE_LIMIT_MAX_ATTEMPTS - $pair);

        // Whichever limit is hit, the wait is until its oldest failure leaves the window
        $firsts = [];
        if ($pair >= RATE_LIMIT_MAX_ATTEMPTS && !empty($result['pair_first']))        $firsts[] = strtotime($result['pair_first']);
        if ($byIp >= RATE_LIMIT_MAX_PER_IP && !empty($result['ip_first']))             $firsts[] = strtotime($result['ip_first']);
        if ($byUser >= RATE_LIMIT_MAX_PER_USERNAME && !empty($result['user_first']))   $firsts[] = strtotime($result['user_first']);

        if ($firsts) {
            // Calculate when they can retry (the latest of the limits they're over)
            $retryAfter = max($firsts) + $window - time();

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
 * Only that account's counter: clearing by IP would let someone who owns an
 * account log into it between guesses at another and keep their count at zero.
 *
 * @param string $ip The IP address
 * @param string $username The username/email
 */
function clearLoginAttempts($ip, $username) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE username = ?");
        $stmt->execute([$username]);
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
 * Proxies allowed to tell us the client's address: [app] trusted_proxy in
 * config-sql.ini / config.ini, comma-separated IPs (empty or absent = nobody).
 */
function trustedProxies(): array {
    static $list = null;
    if ($list !== null) return $list;
    $list = [];
    foreach (['config-sql.ini', 'config.ini'] as $file) {
        $path = __DIR__ . '/' . $file;
        if (file_exists($path)) {
            $cfg = parse_ini_file($path, true);
            if (!empty($cfg['app']['trusted_proxy'])) {
                $list = array_values(array_filter(array_map('trim', explode(',', (string)$cfg['app']['trusted_proxy']))));
            }
            break;
        }
    }
    return $list;
}

/**
 * Get the client IP address.
 *
 * REMOTE_ADDR is the only address a client can't make up. X-Forwarded-For is
 * read only when the connection comes from a proxy listed in trusted_proxy, and
 * then walked from the right, past our own proxies.
 *
 * @return string The client IP address
 */
function getClientIP() {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $proxies = trustedProxies();

    if ($proxies && in_array($remote, $proxies, true) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $hops = array_reverse(array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])));
        foreach ($hops as $hop) {
            if (!filter_var($hop, FILTER_VALIDATE_IP)) break; // garbage in the chain: stop trusting it
            if (!in_array($hop, $proxies, true)) return $hop;
        }
    }

    return $remote;
}

