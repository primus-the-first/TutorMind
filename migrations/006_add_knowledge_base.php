<?php
/**
 * Migration: Add knowledge_base table for RAG system
 * 
 * Stores extracted content from web searches with embeddings for semantic retrieval.
 */

require_once __DIR__ . '/../db_mysql.php';

try {
    $pdo = getDbConnection();
    
    // Check if table already exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'knowledge_base'");
    if ($stmt->fetch()) {
        echo "✅ knowledge_base table already exists.<br>";
    } else {
        $pdo->exec("
            CREATE TABLE knowledge_base (
                id INT AUTO_INCREMENT PRIMARY KEY,
                source_url VARCHAR(512) NOT NULL,
                source_title VARCHAR(255),
                source_type ENUM('webpage', 'pdf', 'paper', 'book') DEFAULT 'webpage',
                content_chunk TEXT NOT NULL,
                chunk_index INT DEFAULT 0,
                embedding_json TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_source_url (source_url(191)),
                INDEX idx_source_type (source_type),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✅ Created knowledge_base table.<br>";
    }
    
    echo "<br><strong>Migration complete!</strong>";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage();
}
?>
