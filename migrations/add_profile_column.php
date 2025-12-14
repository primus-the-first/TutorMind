<?php
require_once __DIR__ . '/../db_mysql.php';

try {
    $pdo = getDbConnection();
    
    // Check if column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'profile_data'");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if (!$result) {
        // Add the column
        $sql = "ALTER TABLE users ADD COLUMN profile_data TEXT";
        $pdo->exec($sql);
        echo "Column 'profile_data' added successfully.\n";
    } else {
        echo "Column 'profile_data' already exists.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
