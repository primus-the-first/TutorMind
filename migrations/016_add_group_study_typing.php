<?php
/**
 * Migration 016: Add typing_at to group_session_participants.
 *
 * Group Study has no WebSocket layer (see migration 015's header) — the
 * "X is typing..." indicator has to be modeled as state a participant
 * pings periodically and the 3s poll loop picks up a couple seconds late,
 * same eventual-consistency shape as the rest of this feature.
 */

require_once __DIR__ . '/../includes/db_mysql.php';

try {
    $pdo = getDbConnection();

    $pdo->exec("
        ALTER TABLE group_session_participants
        ADD COLUMN IF NOT EXISTS typing_at TIMESTAMP NULL DEFAULT NULL
        COMMENT 'Last time this participant sent a typing ping; NULL/stale = not typing'
    ");

    $stmt = $pdo->query("SHOW COLUMNS FROM group_session_participants LIKE 'typing_at'");
    if ($stmt->fetch()) {
        echo "✅ Migration 016 complete: typing_at column present on group_session_participants.\n";
    } else {
        echo "❌ Migration 016 failed: column missing after ALTER TABLE.\n";
        exit(1);
    }
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "ℹ️  Column typing_at already exists — skipping.\n";
    } else {
        echo "❌ Migration 016 failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}
