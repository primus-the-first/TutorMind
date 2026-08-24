<?php
/**
 * Reads the [webpush] section from the same config file getDbConnection()
 * uses (config-sql.ini locally, config.ini in production).
 */
function getWebPushConfig(): array {
    $configFile = null;

    if (file_exists(__DIR__ . '/config-sql.ini')) {
        $configFile = __DIR__ . '/config-sql.ini';
    } elseif (file_exists(__DIR__ . '/config.ini')) {
        $configFile = __DIR__ . '/config.ini';
    } else {
        throw new Exception("Configuration file not found. Please ensure config-sql.ini or config.ini exists in the includes directory.");
    }

    $config = parse_ini_file($configFile, true);
    if ($config === false || !isset($config['webpush'])) {
        throw new Exception("Missing [webpush] section in {$configFile}.");
    }

    $webPushConfig = $config['webpush'];
    $requiredKeys = ['vapid_public_key', 'vapid_private_key', 'vapid_subject'];
    foreach ($requiredKeys as $key) {
        if (!isset($webPushConfig[$key])) {
            throw new Exception("Required webpush configuration key '{$key}' is missing in {$configFile}.");
        }
    }

    return $webPushConfig;
}
