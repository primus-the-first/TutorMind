<?php
/**
 * Migration 023: email verification.
 *
 * - users.email_verified_at: when the owner proved they can read the address
 *   (clicked the emailed link, signed in with a Google account that vouches for
 *   it, or reset their password by email). NULL = not proven. Password
 *   sign-ups start NULL; this is what stops someone registering a victim's
 *   address and keeping the password after the victim later signs in with
 *   Google (auth_mysql.php neutralises an unverified, Google-less account
 *   instead of linking it).
 * - email_verifications: one row per emailed link, the same shape as
 *   password_resets (migration 018). Only a SHA-256 hash of the token is
 *   stored; links work once, for 24 hours. Rows go when the user is deleted.
 *
 * Every account that exists when this runs is grandfathered as verified, and so
 * is every Google account. The backfill of existing users only happens in the
 * run that adds the column, so re-running this never verifies newer sign-ups.
 *
 * The app copes with this migration not having run yet (it just skips
 * verification), so deploy the code first, then run this straight away.
 *
 * Run once per environment: php migrations/023_email_verification.php
 */

require_once __DIR__ . '/../includes/db_mysql.php';

try {
    $pdo = getDbConnection();

    $hadColumn = (bool)$pdo->query("SHOW COLUMNS FROM users LIKE 'email_verified_at'")->fetch();
    if (!$hadColumn) {
        $pdo->exec("
            ALTER TABLE users
            ADD COLUMN email_verified_at DATETIME NULL DEFAULT NULL COMMENT 'When the owner proved the address is theirs (migration 023)'
        ");
        // Grandfather everyone who signed up before verification existed
        $pdo->exec("UPDATE users SET email_verified_at = NOW() WHERE email_verified_at IS NULL");
    }
    // Google vouches for the address: safe to re-run
    $pdo->exec("UPDATE users SET email_verified_at = NOW() WHERE email_verified_at IS NULL AND google_id IS NOT NULL AND google_id <> ''");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_verifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL COMMENT 'sha256 of the emailed token',
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_token_hash (token_hash),
            KEY idx_user_created (user_id, created_at),
            CONSTRAINT fk_email_verifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'email_verified_at'")->fetch()) {
        fwrite(STDERR, "Migration 023 failed: users.email_verified_at is missing.\n");
        exit(1);
    }
    echo "Migration 023 complete: users.email_verified_at and email_verifications ready"
        . ($hadColumn ? " (column already existed, existing users left as they were).\n" : " (existing users marked verified).\n");
} catch (PDOException $e) {
    fwrite(STDERR, "Migration 023 failed: " . $e->getMessage() . "\n");
    exit(1);
}
