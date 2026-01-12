<?php
/**
 * Migration: Add feedback table for user ratings and comments
 * 
 * Run this migration on production after deployment.
 * 
 * Creates the `feedback` table to store:
 * - Per-message ratings (thumbs up/down on AI responses)
 * - General app feedback (suggestions, issues, etc.)
 */

require_once __DIR__ . '/../db_mysql.php';

try {
    $pdo = getDbConnection();
    
    // Check if table already exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'feedback'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Feedback table already exists.<br>";
    } else {
        // Create the feedback table
        $sql = "
            CREATE TABLE feedback (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                type ENUM('message_rating', 'general') NOT NULL,
                rating ENUM('positive', 'negative', 'neutral') NOT NULL DEFAULT 'neutral',
                comment TEXT NULL,
                conversation_id INT UNSIGNED NULL,
                message_index INT UNSIGNED NULL,
                page_url VARCHAR(500) NULL,
                category VARCHAR(100) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                INDEX idx_user_id (user_id),
                INDEX idx_type (type),
                INDEX idx_rating (rating),
                INDEX idx_created_at (created_at),
                INDEX idx_conversation (conversation_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $pdo->exec($sql);
        echo "✅ Created feedback table successfully!<br>";
    }
    
    echo "<br><strong>Migration complete!</strong>";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage();
}
?>
