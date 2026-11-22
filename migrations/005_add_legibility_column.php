<?php
/**
 * Migration: Add legibility column to users table
 * 
 * Adds the legibility accessibility setting (90-150%) for font/line scaling.
 */

// CLI only: these files run schema/maintenance work and must never answer a web request.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }


require_once __DIR__ . '/../includes/db_mysql.php';

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
