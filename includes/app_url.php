<?php
/**
 * The public base URL for links TutorMind puts in emails.
 *
 * Never trust the Host header blindly: a forged Host would put a working link
 * to someone else's site in a real user's inbox (reset-link poisoning). Uses
 * [app] base_url from config, else an allowlisted request host, else
 * https://tutormind.app (always the case for CLI jobs like the reminder cron).
 */
function appBaseUrl(): string
{
    foreach (['config-sql.ini', 'config.ini'] as $file) {
        $path = __DIR__ . '/' . $file;
        if (file_exists($path)) {
            $cfg = parse_ini_file($path, true);
            if (!empty($cfg['app']['base_url'])) return rtrim($cfg['app']['base_url'], '/');
            break;
        }
    }
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    $bare = preg_replace('/:\d+$/', '', $host);
    if (PHP_SAPI !== 'cli' && in_array($bare, ['tutormind.app', 'www.tutormind.app', 'localhost', '127.0.0.1'], true)) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        // App root = the folder above includes/, relative to the web root ('' in production, '/TutorMind' on XAMPP)
        $docRoot = str_replace('\\', '/', (string)realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $appDir  = str_replace('\\', '/', (string)realpath(__DIR__ . '/..'));
        $root = ($docRoot !== '' && stripos($appDir, $docRoot) === 0) ? rtrim(substr($appDir, strlen($docRoot)), '/') : '';
        return ($https ? 'https' : 'http') . "://$host$root";
    }
    return 'https://tutormind.app';
}
