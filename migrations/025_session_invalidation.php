<?php
/**
 * Migration 025: sign-out of old sessions after a credential change, and a sign-up throttle.
 *
 * - users.sessions_valid_after: a session that signed in before this moment is no
 *   longer trusted (includes/session_validity.php). Stamped on password change,
 *   password reset, email change and when a Google sign-in takes over an unverified
 *   account, so a session opened by whoever held the old credential dies with it.
 *   NULL = nothing has ever been revoked. Existing sessions stay valid until the
 *   first stamp, so this migration signs nobody out.
 * - registration_attempts: one row per sign-up request per IP, for the per-IP
 *   sign-up throttle (includes/signup_throttle.php). Rows older than 2 days are
 *   tidied up by the sign-up code itself.
 *
 * The app copes with this migration not having run yet (no revocation, no
 * throttle), so deploy the code first, then run this straight away.
 *
 * Run once per environment: php migrations/025_session_invalidation.php
 */

// CLI only: these files run schema/maintenance work and must never answer a web request.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../includes/db_mysql.php';

try {
    $pdo = getDbConnection();

    $hadColumn = (bool)$pdo->query("SHOW COLUMNS FROM users LIKE 'sessions_valid_after'")->fetch();
    if (!$hadColumn) {
        $pdo->exec("
            ALTER TABLE users
            ADD COLUMN sessions_valid_after DATETIME NULL DEFAULT NULL COMMENT 'Sessions that signed in before this are signed out (migration 025)'
        ");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS registration_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ip_created (ip_address, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $missing = [];
    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'sessions_valid_after'")->fetch()) $missing[] = 'users.sessions_valid_after';
    if (!$pdo->query("SHOW TABLES LIKE 'registration_attempts'")->fetch()) $missing[] = 'registration_attempts';
    if ($missing) {
        fwrite(STDERR, "Migration 025 failed: missing " . implode(', ', $missing) . "\n");
        exit(1);
    }
    echo "Migration 025 complete: users.sessions_valid_after"
        . ($hadColumn ? " (already existed)" : " added")
        . " and registration_attempts ready.\n";
} catch (PDOException $e) {
    fwrite(STDERR, "Migration 025 failed: " . $e->getMessage() . "\n");
    exit(1);
}
