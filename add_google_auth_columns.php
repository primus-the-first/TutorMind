<?php
require_once 'db_mysql.php';

try {
    $pdo = getDbConnection();
    
    // Add google_id column
    $sql = "ALTER TABLE users ADD COLUMN google_id VARCHAR(255) NULL UNIQUE AFTER email";
    try {
        $pdo->exec($sql);
        echo "Added google_id column.<br>";
    } catch (PDOException $e) {
        echo "google_id column might already exist: " . $e->getMessage() . "<br>";
    }

    // Add avatar_url column
    $sql = "ALTER TABLE users ADD COLUMN avatar_url VARCHAR(2048) NULL AFTER last_name";
    try {
        $pdo->exec($sql);
        echo "Added avatar_url column.<br>";
    } catch (PDOException $e) {
        echo "avatar_url column might already exist: " . $e->getMessage() . "<br>";
    }

    echo "Database migration completed.";
} catch (PDOException $e) {
    echo "Error during migration: " . $e->getMessage();
}
?>
