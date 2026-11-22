<?php
/**
 * Migration 010: Add knowledge_level column to users table.
 *
 * Stores the result of the onboarding knowledge assessment.
 * Values: 'beginner' | 'intermediate' | 'advanced' | NULL (skipped / not yet assessed)
 */

// CLI only: these files run schema/maintenance work and must never answer a web request.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }


require_once __DIR__ . '/../includes/db_mysql.php';

try {
    $pdo = getDbConnection();

    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN knowledge_level ENUM('beginner', 'intermediate', 'advanced') NULL DEFAULT NULL
    ");

    echo "✅ Migration 010 complete: knowledge_level column added to users.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "ℹ️  Column knowledge_level already exists — skipping.\n";
    } else {
        echo "❌ Migration 010 failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}
