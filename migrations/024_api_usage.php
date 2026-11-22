<?php
/**
 * Migration 024: daily usage counters for the paid APIs.
 *
 * One row per (user, feature, day): how many text-to-speech, image, quiz, group
 * study AI... calls that student has spent today. includes/usage_quota.php
 * reads and bumps it; the limits live there (and in an optional [quotas] section
 * of the config file). Old days are never read again, so the table only grows by
 * one small row per user per feature per active day; rows go with the user.
 *
 * The code works before this runs (it lets requests through and logs once), so
 * deploy the code first, then run this.
 *
 * Run once per environment: php migrations/024_api_usage.php
 */

// CLI only: these files run schema/maintenance work and must never answer a web request.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }


require_once __DIR__ . '/../includes/db_mysql.php';

try {
    $pdo = getDbConnection();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS api_usage (
            user_id INT NOT NULL,
            kind VARCHAR(32) NOT NULL COMMENT 'tts, image, group_ai, quiz_generate, ...',
            day DATE NOT NULL COMMENT 'MySQL CURDATE() when the call was made',
            count INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (user_id, kind, day),
            KEY idx_day (day),
            CONSTRAINT fk_api_usage_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $missing = [];
    if (!$pdo->query("SHOW TABLES LIKE 'api_usage'")->fetch()) {
        $missing[] = 'table api_usage';
    } else {
        foreach (['user_id', 'kind', 'day', 'count'] as $col) {
            if (!$pdo->query("SHOW COLUMNS FROM api_usage LIKE '{$col}'")->fetch()) $missing[] = "api_usage.{$col}";
        }
    }
    if ($missing) {
        echo "❌ Migration 024 failed: missing " . implode(', ', $missing) . "\n";
        exit(1);
    }
    echo "✅ Migration 024 complete: api_usage table ready (daily quotas for paid APIs).\n";
} catch (PDOException $e) {
    echo "❌ Migration 024 failed: " . $e->getMessage() . "\n";
    exit(1);
}
