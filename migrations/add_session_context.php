<?php
/**
 * Migration: Add session context columns to conversations table
 * 
 * Run this migration on production after deployment.
 * 
 * Adds:
 * - session_goal: The user's learning goal (homework_help, test_prep, explore, practice)
 * - context_data: JSON storage for subject, topic, test date, etc.
 * - progress: Percentage completion (0-100)
 * - completed: Whether the session is marked complete
 */

require_once '../db_mysql.php';

try {
    $pdo = getDbConnection();
    
    // Check if columns already exist
    $stmt = $pdo->query("DESCRIBE conversations");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $migrations = [];
    
    // Add session_goal column
    if (!in_array('session_goal', $columns)) {
        $migrations[] = "ALTER TABLE conversations ADD COLUMN session_goal ENUM('homework_help', 'test_prep', 'explore', 'practice') NULL AFTER title";
        echo "Adding session_goal column...<br>";
    } else {
        echo "session_goal column already exists.<br>";
    }
    
    // Add context_data column (JSON for flexible storage)
    if (!in_array('context_data', $columns)) {
        $migrations[] = "ALTER TABLE conversations ADD COLUMN context_data JSON NULL AFTER session_goal";
        echo "Adding context_data column...<br>";
    } else {
        echo "context_data column already exists.<br>";
    }
    
    // Add progress column
    if (!in_array('progress', $columns)) {
        $migrations[] = "ALTER TABLE conversations ADD COLUMN progress TINYINT UNSIGNED DEFAULT 0 AFTER context_data";
        echo "Adding progress column...<br>";
    } else {
        echo "progress column already exists.<br>";
    }
    
    // Add completed column
    if (!in_array('completed', $columns)) {
        $migrations[] = "ALTER TABLE conversations ADD COLUMN completed BOOLEAN DEFAULT FALSE AFTER progress";
        echo "Adding completed column...<br>";
    } else {
        echo "completed column already exists.<br>";
    }
    
    // Execute migrations
    foreach ($migrations as $sql) {
        try {
            $pdo->exec($sql);
            echo "✅ Executed: " . substr($sql, 0, 60) . "...<br>";
        } catch (PDOException $e) {
            echo "❌ Error: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<br><strong>Migration complete!</strong>";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage();
}
?>
