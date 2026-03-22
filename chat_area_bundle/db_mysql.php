<?php

/**
 * Establishes a connection to the MySQL database.
 * Uses singleton pattern to reuse connection within a single request.
 *
 * @return PDO A PDO database connection object.
 * @throws PDOException If the connection fails.
 */
function getDbConnection() {
    // PERFORMANCE: Singleton pattern - reuse connection within same request
    static $pdo = null;
    
    if ($pdo !== null) {
        return $pdo;
    }
    
    // Automatically detect which config file to use based on environment
    // Local (XAMPP): uses config-sql.ini
    // Production (cPanel): uses config.ini
    // Check both current directory and parent directory (for API calls from subdirectories)
    $configFile = null;
    // Priority: 
    // 1. Local/MySQL config (config-sql.ini) - Checked first for local XAMPP dev
    // 2. Prod config (config.ini)
    
    // Check current directory
    if (file_exists('config-sql.ini')) {
        $configFile = 'config-sql.ini';
    } elseif (file_exists('config.ini')) {
        $configFile = 'config.ini';
    
    // Check parent directory
    } elseif (file_exists('../config-sql.ini')) {
        $configFile = '../config-sql.ini';
    } elseif (file_exists('../config.ini')) {
        $configFile = '../config.ini';
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
            PDO::ATTR_TIMEOUT => 5, // 5 second timeout - fail fast
            PDO::ATTR_PERSISTENT => false, // Explicit: don't use persistent connections
        ]);
        
        return $pdo;
    } catch (PDOException $e) {
        throw new PDOException("Database connection failed: " . $e->getMessage());
    }
}