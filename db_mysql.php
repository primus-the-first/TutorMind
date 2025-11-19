<?php

/**
 * Establishes a connection to the MySQL database.
 *
 * @return PDO A PDO database connection object.
 * @throws PDOException If the connection fails.
 */
function getDbConnection() {
    // Read database configuration from config.ini
    // NOTE: This uses MySQL database credentials instead of PostgreSQL
    $config = parse_ini_file('config-sql.ini', true);
    if ($config === false || !isset($config['database'])) {
        throw new Exception("Database configuration is missing or unreadable in config.ini.");
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
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        return $pdo;
    } catch (PDOException $e) {
        // In a real application, you would log this error, not display it to the user.
        throw new PDOException("Database connection failed: " . $e->getMessage());
    }
}