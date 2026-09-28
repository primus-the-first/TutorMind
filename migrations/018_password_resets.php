<?php
/**
 * Migration 018: Password reset links.
 *
 * One row per emailed link. Only a SHA-256 hash of the token is stored, so a
 * database leak doesn't hand out working links. Links expire after 30 minutes
 * and work once (used_at). requested_ip lets the API rate-limit requests per
 * IP as well as per account. Rows go when the user is deleted.
 *
 * Run once per environment: php migrations/018_password_resets.php
 */

require_once __DIR__ . '/../includes/db_mysql.php';

try {
    $pdo = getDbConnection();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL COMMENT 'sha256 of the emailed token',
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL DEFAULT NULL,
            requested_ip VARCHAR(45) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_token_hash (token_hash),
            KEY idx_user_created (user_id, created_at),
            KEY idx_ip_created (requested_ip, created_at),
            CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "Migration 018 complete: password_resets table ready.\n";
} catch (PDOException $e) {
    fwrite(STDERR, "Migration 018 failed: " . $e->getMessage() . "\n");
    exit(1);
}
