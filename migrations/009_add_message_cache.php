<?php
/**
 * Migration: Add Message HTML Cache Column
 * 
 * Adds a content_html column to the messages table for caching
 * formatted/rendered message content. This avoids re-parsing 
 * markdown and processing LaTeX on every page load.
 * 
 * Performance Impact: Reduces CPU time on conversation loading
 * by ~50-100ms per message with code/math content.
 */

require_once __DIR__ . '/../db_mysql.php';

function migrate_add_message_cache() {
    try {
        $pdo = getDbConnection();
        
        // Check if column already exists
        $stmt = $pdo->query("SHOW COLUMNS FROM messages LIKE 'content_html'");
        if ($stmt->fetch()) {
            return [
                'success' => true,
                'message' => 'Column content_html already exists in messages table'
            ];
        }
        
        // Add the content_html column
        $pdo->exec("ALTER TABLE messages ADD COLUMN content_html TEXT NULL AFTER content");
        
        // Add an index for faster lookups of uncached messages
        $pdo->exec("CREATE INDEX idx_msg_cache ON messages (content_html(1))");
        
        return [
            'success' => true,
            'message' => 'Added content_html column to messages table for caching formatted content'
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Run migration if called directly
if (php_sapi_name() === 'cli' || (isset($_GET['run']) && $_GET['run'] === 'true')) {
    $result = migrate_add_message_cache();
    
    if (php_sapi_name() === 'cli') {
        echo "\n=== Message Cache Migration ===\n\n";
        if ($result['success']) {
            echo "✅ " . $result['message'] . "\n";
        } else {
            echo "❌ Migration failed: " . $result['error'] . "\n";
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode($result, JSON_PRETTY_PRINT);
    }
}
