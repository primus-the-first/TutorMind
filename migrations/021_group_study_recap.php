<?php
/**
 * Migration 021: a recap when a group study room ends.
 *
 * - group_sessions.recap: what Q wrote once the room ended, as JSON
 *   { covered[], stuck, next, people{user_id: line} } — or {"skipped": true}
 *   when the room was too quiet to sum up. Each person's line is only ever
 *   sent to that person (api/group_study.php gsRecapFor).
 * - group_sessions.recap_claimed_at: whoever first sees the ended room claims
 *   the write atomically, so two tabs never pay for the same AI call. A claim
 *   older than the AI lease can be taken over (the writer crashed or timed out).
 *
 * Run once per environment: php migrations/021_group_study_recap.php
 */

require_once __DIR__ . '/../includes/db_mysql.php';

try {
    $pdo = getDbConnection();

    $pdo->exec("
        ALTER TABLE group_sessions
        ADD COLUMN IF NOT EXISTS recap JSON NULL DEFAULT NULL COMMENT 'Q''s end-of-room recap, or {\"skipped\":true}',
        ADD COLUMN IF NOT EXISTS recap_claimed_at DATETIME NULL DEFAULT NULL COMMENT 'Who-writes-it claim, see migration 021'
    ");

    $missing = [];
    foreach (['recap', 'recap_claimed_at'] as $col) {
        if (!$pdo->query("SHOW COLUMNS FROM group_sessions LIKE '{$col}'")->fetch()) $missing[] = "group_sessions.{$col}";
    }
    if ($missing) {
        echo "❌ Migration 021 failed: missing " . implode(', ', $missing) . "\n";
        exit(1);
    }
    echo "✅ Migration 021 complete: group study recaps.\n";
} catch (PDOException $e) {
    echo "❌ Migration 021 failed: " . $e->getMessage() . "\n";
    exit(1);
}
