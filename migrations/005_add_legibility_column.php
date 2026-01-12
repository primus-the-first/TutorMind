<?php
/**
 * Migration: Add legibility column to users table
 * 
 * Adds the legibility accessibility setting (90-150%) for font/line scaling.
 */

require_once __DIR__ . '/../db_mysql.php';

try {
    $pdo = getDbConnection();
    
    // Check if column already exists
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('legibility', $columns)) {
        echo "✅ Legibility column already exists.<br>";
    } else {
        $pdo->exec("ALTER TABLE users ADD COLUMN legibility TINYINT UNSIGNED DEFAULT 100");
        echo "✅ Added legibility column to users table.<br>";
    }
    
    echo "<br><strong>Migration complete!</strong>";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage();
}
?>
