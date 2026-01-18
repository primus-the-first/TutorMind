<?php
/**
 * Migration: Create login_attempts table for rate limiting
 * 
 * Run this migration to create the table needed for login rate limiting.
 * Command: php migrations/007_add_login_attempts_table.php
 */

require_once __DIR__ . '/../db_mysql.php';

try {
    $pdo = getDbConnection();
    
    $sql = "CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        username VARCHAR(255) NOT NULL,
        attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_time (ip_address, attempt_time),
        INDEX idx_username_time (username, attempt_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    
    echo "✓ Table 'login_attempts' created successfully.\n";
    
} catch (Exception $e) {
    echo "✗ Error creating table: " . $e->getMessage() . "\n";
    exit(1);
}
?>
