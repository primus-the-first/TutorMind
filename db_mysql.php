<?php

/**
 * Establishes a connection to the MySQL database.
 *
 * @return PDO A PDO database connection object.
 * @throws PDOException If the connection fails.
 */
function getDbConnection() {
    // Automatically detect which config file to use based on environment
    // Local (XAMPP): uses config-sql.ini
    // Production (cPanel): uses config.ini
    // Check both current directory and parent directory (for API calls from subdirectories)
    $configFile = null;
    // Priority: 
    // 1. Prod config (config.ini) - Checked first to prevent local config accidental usage on prod
    // 2. Local config (config-sql.ini)
    if (file_exists('config.ini')) {
        $configFile = 'config.ini';
    } elseif (file_exists('config-sql.ini')) {
        $configFile = 'config-sql.ini';
    } elseif (file_exists('../config.ini')) {
        $configFile = '../config.ini';
    } elseif (file_exists('../config-sql.ini')) {
        $configFile = '../config-sql.ini';
    } else {
        throw new Exception("Database configuration file not found. Please ensure config-sql.ini or config.ini exists in the root directory.");
    }
    
    $config = parse_ini_file($configFile, true);
    if ($config === false || !isset($config['database'])) {
        throw new Exception("Database configuration is missing or unreadable in {$configFile}.");
    }
    $dbConfig = $config['database'];

    $host = $dbConfig['host'];
    $port = $dbConfig['port'];
    $dbname = $dbConfig['dbname'];
    $user = $dbConfig['user'];
    $password = $dbConfig['password'];

    // --- Data Source Name (DSN) for MySQL ---
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 60, // Increase timeout for large uploads
        ]);
        
        // Configure MySQL for handling large data transfers
        // Note: max_allowed_packet is read-only at session level, must be set globally
        // Increase wait timeout to prevent connection drops during image processing
        $pdo->exec("SET SESSION wait_timeout=300"); // 5 minutes
        $pdo->exec("SET SESSION interactive_timeout=300"); // 5 minutes
        
        return $pdo;
    } catch (PDOException $e) {
        // In a real application, you would log this error, not display it to the user.
        throw new PDOException("Database connection failed: " . $e->getMessage());
    }
}