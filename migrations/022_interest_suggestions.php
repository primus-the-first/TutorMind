<?php
/**
 * Migration 022: interests noticed in chat, and a room privacy switch.
 *
 * - users.suggested_interests: what the tutor noticed the student likes while
 *   chatting, as JSON {"pending": [...], "declined": [...]}. Nothing in here
 *   reaches a prompt: a suggestion only becomes an interest once the student
 *   accepts it in Settings. "declined" stops the same guess coming back.
 * - users.interests_in_rooms: whether Q may use the student's own interests when
 *   it talks to them in a group study room. On by default; Settings → Privacy.
 *
 * Run once per environment: php migrations/022_interest_suggestions.php
 */

require_once __DIR__ . '/../includes/db_mysql.php';

try {
    $pdo = getDbConnection();

    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS suggested_interests TEXT NULL DEFAULT NULL COMMENT 'Interests noticed in chat, awaiting the student''s OK (migration 022)',
        ADD COLUMN IF NOT EXISTS interests_in_rooms TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'May Q use my interests in group rooms (migration 022)'
    ");

    $missing = [];
    foreach (['suggested_interests', 'interests_in_rooms'] as $col) {
        if (!$pdo->query("SHOW COLUMNS FROM users LIKE '{$col}'")->fetch()) $missing[] = "users.{$col}";
    }
    if ($missing) {
        echo "❌ Migration 022 failed: missing " . implode(', ', $missing) . "\n";
        exit(1);
    }
    echo "✅ Migration 022 complete: interest suggestions and room privacy switch.\n";
} catch (PDOException $e) {
    echo "❌ Migration 022 failed: " . $e->getMessage() . "\n";
    exit(1);
}
