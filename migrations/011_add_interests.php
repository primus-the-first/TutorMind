<?php
/**
 * Migration 011: Add interests column to users table.
 *
 * Stores a JSON array of personal interests/hobbies captured during onboarding,
 * used as a fallback source for teaching analogies when a learner can't produce their own.
 */

require_once __DIR__ . '/../includes/db_mysql.php';

try {
    $pdo = getDbConnection();

    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN interests TEXT NULL DEFAULT NULL
        COMMENT 'JSON array of personal interests/hobbies, used to seed teaching analogies'
    ");

    echo "✅ Migration 011 complete: interests column added to users.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "ℹ️  Column interests already exists — skipping.\n";
    } else {
        echo "❌ Migration 011 failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}
