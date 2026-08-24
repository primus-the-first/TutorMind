<?php
/**
 * Migration 013: Create push_subscriptions table.
 *
 * Stores Web Push subscriptions (one row per browser/device a user has
 * enabled push notifications on) used by scripts/send_study_reminders.php
 * to deliver real push notifications alongside the stubbed email reminder.
 */

require_once __DIR__ . '/../includes/db_mysql.php';

try {
    $pdo = getDbConnection();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS push_subscriptions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            endpoint TEXT NOT NULL,
            endpoint_hash CHAR(64) NOT NULL,
            p256dh VARCHAR(255) NOT NULL,
            auth VARCHAR(255) NOT NULL,
            user_agent VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_endpoint_hash (endpoint_hash),
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✅ Migration 013 complete: push_subscriptions table created.\n";
} catch (PDOException $e) {
    echo "❌ Migration 013 failed: " . $e->getMessage() . "\n";
    exit(1);
}
