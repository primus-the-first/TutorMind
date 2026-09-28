<?php
/**
 * Migration 017: Group study as a chat room.
 *
 * - Presence: participants ping last_seen_at on every poll, so the room can
 *   show who's here and the facilitator only calls on people who are.
 * - Leaving: left_at marks "left the room" without deleting the row, so the
 *   transcript keeps its speaker and rejoining picks the session back up.
 * - Calling on people: an AI message can be addressed to one participant
 *   (addressed_user_id) — the facilitator checks in with whoever has been
 *   quiet, and their screen shows "Q asked you".
 * - System lines ("Ada passed the turn to Ben", "Ben joined") are messages
 *   with sender_type 'system', so they sit in the transcript in order.
 * - One AI reply at a time per room: ai_busy_until is a short lease taken
 *   before calling the AI (and shown to the room as "Q is thinking").
 * - ended_at records when the host ended the session (status 'completed'
 *   existed before but nothing ever set it).
 */

require_once __DIR__ . '/../includes/db_mysql.php';

try {
    $pdo = getDbConnection();

    $pdo->exec("
        ALTER TABLE group_session_participants
        ADD COLUMN IF NOT EXISTS last_seen_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Last poll from this participant; recent = here',
        ADD COLUMN IF NOT EXISTS left_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Set when they leave; cleared on rejoin'
    ");

    $pdo->exec("
        ALTER TABLE group_sessions
        ADD COLUMN IF NOT EXISTS ai_busy_until DATETIME NULL DEFAULT NULL COMMENT 'Lease while an AI reply is being generated',
        ADD COLUMN IF NOT EXISTS ended_at TIMESTAMP NULL DEFAULT NULL
    ");

    $pdo->exec("
        ALTER TABLE group_session_messages
        MODIFY COLUMN sender_type ENUM('student', 'ai', 'system') NOT NULL,
        ADD COLUMN IF NOT EXISTS addressed_user_id INT NULL DEFAULT NULL COMMENT 'Participant the facilitator called on'
    ");

    // FK added separately so re-running doesn't fail on a duplicate constraint
    $fk = $pdo->query("
        SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'group_session_messages'
          AND COLUMN_NAME = 'addressed_user_id' AND REFERENCED_TABLE_NAME = 'users'
    ")->fetchColumn();
    if ((int) $fk === 0) {
        $pdo->exec("
            ALTER TABLE group_session_messages
            ADD CONSTRAINT fk_gsm_addressed_user FOREIGN KEY (addressed_user_id) REFERENCES users(id) ON DELETE SET NULL
        ");
    }

    $missing = [];
    foreach ([
        ['group_session_participants', 'last_seen_at'],
        ['group_session_participants', 'left_at'],
        ['group_sessions', 'ai_busy_until'],
        ['group_sessions', 'ended_at'],
        ['group_session_messages', 'addressed_user_id'],
    ] as [$table, $col]) {
        if (!$pdo->query("SHOW COLUMNS FROM {$table} LIKE '{$col}'")->fetch()) $missing[] = "{$table}.{$col}";
    }
    if ($missing) {
        echo "❌ Migration 017 failed: missing " . implode(', ', $missing) . "\n";
        exit(1);
    }
    echo "✅ Migration 017 complete: presence, leaving, addressed messages, system lines, AI lease, ended_at.\n";
} catch (PDOException $e) {
    echo "❌ Migration 017 failed: " . $e->getMessage() . "\n";
    exit(1);
}
