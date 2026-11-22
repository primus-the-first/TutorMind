<?php
// CLI only: these files run schema/maintenance work and must never answer a web request.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once 'db_mysql.php';

try {
    $pdo = getDbConnection();
    $sql = "CREATE TABLE IF NOT EXISTS user_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        selector VARCHAR(255) NOT NULL,
        hashed_validator VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    echo "Table user_tokens created successfully.";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>
