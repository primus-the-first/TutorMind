<?php
/**
 * Migration: Add Performance Indexes
 * 
 * This migration adds database indexes to optimize common queries:
 * - Conversation listing by user
 * - Message retrieval by conversation
 * - Login attempt rate limiting
 * - Knowledge base lookups
 * 
 * Run this migration to significantly improve query performance.
 */

require_once __DIR__ . '/../db_mysql.php';

function migrate_add_performance_indexes() {
    try {
        $pdo = getDbConnection();
        
        $indexes = [
            // Conversations - for user history listing (ORDER BY updated_at DESC)
            [
                'table' => 'conversations',
                'name' => 'idx_conv_user_updated',
                'columns' => 'user_id, updated_at DESC',
                'description' => 'Optimizes conversation history loading'
            ],
            
            // Messages - for loading conversation messages
            [
                'table' => 'messages',
                'name' => 'idx_msg_conv_created',
                'columns' => 'conversation_id, created_at',
                'description' => 'Optimizes message retrieval for conversations'
            ],
            
            // Login attempts - for rate limiting by IP
            [
                'table' => 'login_attempts',
                'name' => 'idx_attempts_ip_time',
                'columns' => 'ip_address, attempt_time',
                'description' => 'Optimizes rate limiting IP lookups'
            ],
            
            // Login attempts - for rate limiting by username
            [
                'table' => 'login_attempts',
                'name' => 'idx_attempts_user_time',
                'columns' => 'username, attempt_time',
                'description' => 'Optimizes rate limiting username lookups'
            ],
            
            // User tokens - for remember me cookie validation
            [
                'table' => 'user_tokens',
                'name' => 'idx_tokens_selector',
                'columns' => 'selector',
                'description' => 'Optimizes remember-me token lookups'
            ],
            
            // User tokens - for cleanup of expired tokens
            [
                'table' => 'user_tokens',
                'name' => 'idx_tokens_expires',
                'columns' => 'expires_at',
                'description' => 'Optimizes expired token cleanup'
            ],
            
            // Feedback - for admin dashboard queries
            [
                'table' => 'feedback',
                'name' => 'idx_feedback_user_created',
                'columns' => 'user_id, created_at DESC',
                'description' => 'Optimizes feedback retrieval'
            ],
        ];
        
        $results = [];
        
        foreach ($indexes as $index) {
            $table = $index['table'];
            $name = $index['name'];
            $columns = $index['columns'];
            
            // Check if table exists first
            $tableCheck = $pdo->query("SHOW TABLES LIKE '{$table}'")->fetch();
            if (!$tableCheck) {
                $results[] = "⚠️ Skipped {$name}: Table '{$table}' does not exist";
                continue;
            }
            
            // Check if index already exists
            $indexCheck = $pdo->query("SHOW INDEX FROM {$table} WHERE Key_name = '{$name}'")->fetch();
            if ($indexCheck) {
                $results[] = "✓ Index {$name} already exists on {$table}";
                continue;
            }
            
            // Create the index
            try {
                $sql = "CREATE INDEX {$name} ON {$table} ({$columns})";
                $pdo->exec($sql);
                $results[] = "✅ Created index {$name} on {$table} ({$index['description']})";
            } catch (PDOException $e) {
                // Handle DESC keyword not supported in older MySQL
                if (strpos($e->getMessage(), 'DESC') !== false) {
                    // Try without DESC
                    $columnsWithoutDesc = str_replace(' DESC', '', $columns);
                    $sql = "CREATE INDEX {$name} ON {$table} ({$columnsWithoutDesc})";
                    $pdo->exec($sql);
                    $results[] = "✅ Created index {$name} on {$table} (without DESC - older MySQL)";
                } else {
                    $results[] = "❌ Failed to create {$name}: " . $e->getMessage();
                }
            }
        }
        
        // Try to add knowledge_base indexes if table exists
        $kbCheck = $pdo->query("SHOW TABLES LIKE 'knowledge_base'")->fetch();
        if ($kbCheck) {
            // Check for source_url index
            $urlIndex = $pdo->query("SHOW INDEX FROM knowledge_base WHERE Key_name = 'idx_kb_source_url'")->fetch();
            if (!$urlIndex) {
                try {
                    $pdo->exec("CREATE INDEX idx_kb_source_url ON knowledge_base (source_url(255))");
                    $results[] = "✅ Created index idx_kb_source_url on knowledge_base";
                } catch (PDOException $e) {
                    $results[] = "❌ Failed to create idx_kb_source_url: " . $e->getMessage();
                }
            }
            
            // Check for fulltext index on content
            $ftIndex = $pdo->query("SHOW INDEX FROM knowledge_base WHERE Key_name = 'idx_kb_content_ft'")->fetch();
            if (!$ftIndex) {
                try {
                    $pdo->exec("CREATE FULLTEXT INDEX idx_kb_content_ft ON knowledge_base (content)");
                    $results[] = "✅ Created FULLTEXT index idx_kb_content_ft on knowledge_base";
                } catch (PDOException $e) {
                    $results[] = "⚠️ Skipped FULLTEXT index: " . $e->getMessage();
                }
            }
        }
        
        return [
            'success' => true,
            'message' => 'Performance indexes migration completed',
            'results' => $results
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
    $result = migrate_add_performance_indexes();
    
    if (php_sapi_name() === 'cli') {
        echo "\n=== Performance Indexes Migration ===\n\n";
        if ($result['success']) {
            foreach ($result['results'] as $line) {
                echo $line . "\n";
            }
            echo "\n✅ Migration completed successfully!\n";
        } else {
            echo "❌ Migration failed: " . $result['error'] . "\n";
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode($result, JSON_PRETTY_PRINT);
    }
}
