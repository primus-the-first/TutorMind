<?php

require_once __DIR__ . '/pulse_monitor.php';

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
    $configFile = null;

    if (file_exists(__DIR__ . '/config-sql.ini')) {
        $configFile = __DIR__ . '/config-sql.ini';
    } elseif (file_exists(__DIR__ . '/config.ini')) {
        $configFile = __DIR__ . '/config.ini';
    } else {
        throw new Exception("Database configuration file not found. Please ensure config-sql.ini or config.ini exists in the includes directory.");
    }

    $config = parse_ini_file($configFile, true);
    if ($config === false || !isset($config['database'])) {
        throw new Exception("Database configuration is missing or unreadable in {$configFile}.");
    }
    $dbConfig = $config['database'];

    $requiredKeys = ['host', 'dbname', 'user', 'password', 'port'];
    foreach ($requiredKeys as $key) {
        if (!isset($dbConfig[$key])) {
            throw new Exception("Required database configuration key '{$key}' is missing in {$configFile}.");
        }
    }

    $host     = $dbConfig['host'];
    $port     = $dbConfig['port'];
    $dbname   = $dbConfig['dbname'];
    $user     = $dbConfig['user'];
    $password = $dbConfig['password'];

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
            PDO::ATTR_PERSISTENT         => false,
        ]);

        // Brief concurrent write conflicts (chat flow + session_context hitting the same
        // conversations row) typically resolve in <1 s. 30 s gives enough headroom while
        // still failing faster than the MySQL default (50 s) on genuine deadlocks.
        $pdo->exec("SET SESSION innodb_lock_wait_timeout = 30");

        return $pdo;
    } catch (PDOException $e) {
        throw new PDOException("Database connection failed: " . $e->getMessage());
    }
}

/**
 * Execute a callable that runs PDO statements, retrying on transient lock errors.
 * Handles error 1205 (lock wait timeout) and 1213 (deadlock found).
 *
 * Usage:
 *   pdo_retry(fn() => $stmt->execute($params));
 *
 * @param callable $fn       The work to attempt. Receives no arguments.
 * @param int      $attempts Maximum total attempts (default 3).
 * @return mixed             Whatever $fn returns on success.
 * @throws PDOException      Re-thrown if all attempts fail or the error is not lock-related.
 */
function pdo_retry(callable $fn, int $attempts = 3): mixed
{
    $try = 0;
    while (true) {
        try {
            return $fn();
        } catch (PDOException $e) {
            $code = (int) ($e->errorInfo[1] ?? 0);
            if (in_array($code, [1205, 1213]) && ++$try < $attempts) {
                usleep(150000 * $try); // 150 ms, then 300 ms
                continue;
            }
            throw $e;
        }
    }
}
