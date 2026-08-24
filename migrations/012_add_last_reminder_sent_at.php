<?php
/**
 * Migration 012: Add last_reminder_sent_at column to users table.
 *
 * Tracks when a study reminder was last sent to a user, so the reminder
 * CLI script (scripts/send_study_reminders.php) can respect a per-frequency
 * cooldown and avoid re-sending within the same window.
 */

require_once __DIR__ . '/../includes/db_mysql.php';

try {
    $pdo = getDbConnection();

    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS last_reminder_sent_at DATETIME NULL DEFAULT NULL
        COMMENT 'Last time a study reminder was sent to this user'
    ");

    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'last_reminder_sent_at'");
    if ($stmt->fetch()) {
        echo "✅ Migration 012 complete: last_reminder_sent_at column present on users.\n";
    } else {
        echo "❌ Migration 012 failed: column missing after ALTER TABLE.\n";
        exit(1);
    }
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "ℹ️  Column last_reminder_sent_at already exists — skipping.\n";
    } else {
        echo "❌ Migration 012 failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}
