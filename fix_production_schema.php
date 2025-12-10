<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_mysql.php';

echo "<h1>Production Database Schema Fixer</h1>";
echo "<p>Checking database schema...</p>";

try {
    $pdo = getDbConnection();
    
    // 1. Get current columns
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $columns = array_map('strtolower', $columns);
    
    echo "Current columns: " . implode(', ', $columns) . "<br><br>";
    
    // 2. Check and Add google_id
    if (!in_array('google_id', $columns)) {
        echo "Adding 'google_id' column... ";
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN google_id VARCHAR(255) NULL UNIQUE AFTER email");
            echo "<span style='color:green'>Success</span><br>";
        } catch (Exception $e) {
            echo "<span style='color:red'>Failed: " . $e->getMessage() . "</span><br>";
        }
    } else {
        echo "'google_id' column exists.<br>";
    }

    // 3. Check and Add avatar_url
    if (!in_array('avatar_url', $columns)) {
        echo "Adding 'avatar_url' column... ";
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN avatar_url VARCHAR(2048) NULL AFTER last_name"); 
            // Note: IF last_name doesn't exist yet, this might fail or position elsewhere. 
            // Safe to just add it, MySQL handles positioning gracefully or validation.
            // But let's handle first_name/last_name first if possible? 
            // Actually, if last_name is missing, the AFTER clause might error.
            // Let's rely on simple ADD if last_name is missing.
            
            if (in_array('last_name', $columns)) {
                 // Retry if the first attempt failed? No, let's just do it intelligently below.
            }
             echo "<span style='color:green'>Success</span><br>";
        } catch (Exception $e) {
             // Try adding without AFTER clause if it failed
             try {
                $pdo->exec("ALTER TABLE users ADD COLUMN avatar_url VARCHAR(2048) NULL");
                echo "<span style='color:green'>Success (no position)</span><br>";
             } catch (Exception $e2) {
                echo "<span style='color:red'>Failed: " . $e2->getMessage() . "</span><br>";
             }
        }
    } else {
        echo "'avatar_url' column exists.<br>";
    }

    // 4. Handle Name Splitting (name -> first_name, last_name)
    $hasFirstName = in_array('first_name', $columns);
    $hasLastName = in_array('last_name', $columns);
    $hasName = in_array('name', $columns);

    if (!$hasFirstName || !$hasLastName) {
        echo "Missing 'first_name' or 'last_name'. Attempting to add...<br>";
        
        try {
            if (!$hasFirstName) {
                $pdo->exec("ALTER TABLE users ADD COLUMN first_name VARCHAR(100) NOT NULL DEFAULT '' AFTER id"); // Adjust position as needed
                echo "Added 'first_name'.<br>";
            }
            if (!$hasLastName) {
                $pdo->exec("ALTER TABLE users ADD COLUMN last_name VARCHAR(100) NOT NULL DEFAULT '' AFTER first_name");
                echo "Added 'last_name'.<br>";
            }

            // Migration Logic: Split 'name' if it exists
            if ($hasName) {
                echo "Migrating data from 'name' column... ";
                $stmt = $pdo->query("SELECT id, name FROM users");
                $users = $stmt->fetchAll();
                
                $count = 0;
                foreach ($users as $user) {
                    $fullName = trim($user['name']);
                    $parts = explode(' ', $fullName, 2);
                    $first = $parts[0] ?? '';
                    $last = $parts[1] ?? '';
                    
                    $update = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ? WHERE id = ?");
                    $update->execute([$first, $last, $user['id']]);
                    $count++;
                }
                echo "Migrated $count users.<br>";
                
                // Optional: Drop 'name'? Maybe safer to keep it for now but nullable?
                // $pdo->exec("ALTER TABLE users DROP COLUMN name");
            }
        } catch (Exception $e) {
             echo "<span style='color:red'>Error adding names or migrating: " . $e->getMessage() . "</span><br>";
        }
    } else {
        echo "'first_name' and 'last_name' columns exist.<br>";
    }

    echo "<h3>Done.</h3>";
    echo "<a href='auth_mysql.php'>Go back to Auth</a>";

} catch (Exception $e) {
    echo "<h1>Critical Error</h1>";
    echo $e->getMessage();
}
?>
