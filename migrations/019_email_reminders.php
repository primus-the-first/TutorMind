<?php
/**
 * Migration 019: Email study reminders (opt-in).
 *
 * - email_reminders: the student switched on "Email me" in Settings. Off by
 *   default — the old onboarding only ever said "study reminders", never email,
 *   so nobody is emailed until they choose it. Push reminders are unaffected.
 * - email_token: per-user secret in unsubscribe links, so "Unsubscribe" works
 *   from the email without logging in (and can't be forged for someone else).
 *   Filled in the first time a reminder email is sent.
 *
 * Run once per environment: php migrations/019_email_reminders.php
 */

require_once __DIR__ . '/../includes/db_mysql.php';

try {
    $pdo = getDbConnection();
    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS email_reminders TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Opted in to study-reminder emails',
        ADD COLUMN IF NOT EXISTS email_token CHAR(32) NULL DEFAULT NULL COMMENT 'Secret for one-click unsubscribe links'
    ");
    echo "Migration 019 complete: email_reminders + email_token ready.\n";
} catch (PDOException $e) {
    fwrite(STDERR, "Migration 019 failed: " . $e->getMessage() . "\n");
    exit(1);
}
