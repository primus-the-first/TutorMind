<?php
/**
 * Migration: Add is_edited column to messages table
 * Run this script once to add support for the message edit feature.
 */

require_once __DIR__ . '/../config.ini.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Check if column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM messages LIKE 'is_edited'");
    if ($stmt->rowCount() > 0) {
        echo "Column 'is_edited' already exists. No changes needed.\n";
        exit;
    }

    $pdo->exec("ALTER TABLE messages ADD COLUMN is_edited TINYINT(1) NOT NULL DEFAULT 0 AFTER content_html");
    echo "Successfully added 'is_edited' column to messages table.\n";

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
