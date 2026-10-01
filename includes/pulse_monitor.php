<?php

/**
 * Pulse Monitor — sends request events to the Pulse backend.
 * Uses register_shutdown_function to capture the real status code and
 * duration. Shutdown functions still run BEFORE the response is finished
 * (mod_php, LiteSpeed) and while the session lock is held, so the send
 * first hands the client its response — see pulse_register().
 */

define('PULSE_INGEST_URL', 'https://pulse-server-ceb5.onrender.com/ingest');
define('PULSE_SERVICE', 'tutormind');
define('PULSE_ENABLED', true);

function pulse_send_event(array $event): void {
    $payload = json_encode($event);
    $ch = curl_init(PULSE_INGEST_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Content-Length: ' . strlen($payload)],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 2, // never block the user
        CURLOPT_CONNECTTIMEOUT => 1,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// Production runs on Linux (cPanel); every Windows install is a dev machine (XAMPP).
// Unlike the Host header this also holds for CLI scripts and cron.
function pulse_environment(): string {
    if (PHP_OS_FAMILY === 'Windows') return 'local';
    $host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
    return in_array($host, ['localhost', '127.0.0.1', '[::1]'], true) ? 'local' : 'prod';
}

// Path relative to the app root ('' in production, '/TutorMind' on XAMPP), so the
// same endpoint gets the same lane name in every environment.
function pulse_endpoint(): string {
    if (PHP_SAPI === 'cli') {
        $script = $_SERVER['argv'][0] ?? '';
        return 'cli:' . ($script !== '' && $script !== '-' ? basename($script) : 'inline');
    }
    $path    = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    $docRoot = str_replace('\\', '/', (string)realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $appDir  = str_replace('\\', '/', (string)realpath(__DIR__ . '/..'));
    $root    = ($docRoot !== '' && stripos($appDir, $docRoot) === 0) ? rtrim(substr($appDir, strlen($docRoot)), '/') : '';
    if ($root !== '' && stripos($path, $root) === 0 && in_array(substr($path, strlen($root), 1), ['', '/'], true)) {
        $path = substr($path, strlen($root));
    }
    return $path === '' ? '/' : $path;
}

// Lets a request say more than its status code: why a 403 was rejected, or which
// step of a slow chat reply ate the time. Shown as the event's errorMessage; a
// fatal error still overrides it.
function pulse_note(string $message): void {
    $GLOBALS['pulse_note'] = $message;
}

function pulse_register(): void {
    if (!PULSE_ENABLED) return;

    $startTime   = microtime(true);
    $traceId     = bin2hex(random_bytes(8));
    $spanId      = bin2hex(random_bytes(4));
    $method      = $_SERVER['REQUEST_METHOD'] ?? (PHP_SAPI === 'cli' ? 'CLI' : 'GET');
    $endpoint    = pulse_endpoint();
    $environment = pulse_environment();

    register_shutdown_function(function() use ($startTime, $traceId, $spanId, $method, $endpoint, $environment) {
        $duration   = round((microtime(true) - $startTime) * 1000, 2); // ms
        $statusCode = http_response_code() ?: 200;

        $error = error_get_last();
        $errorMessage = $GLOBALS['pulse_note'] ?? null;
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $errorMessage = $error['message'];
            $statusCode   = 500;
        }

        $event = [
            'traceId'      => $traceId,
            'spanId'       => $spanId,
            'service'      => PULSE_SERVICE,
            'endpoint'     => $endpoint,
            'method'       => $method,
            'statusCode'   => $statusCode,
            'duration'     => $duration,
            'errorMessage' => $errorMessage,
            'environment'  => $environment,
        ];

        // Queued from inside shutdown, so it runs after every other shutdown
        // handler (e.g. server_mysql.php's fatal-error JSON) has written output.
        register_shutdown_function(function () use ($event) {
            // The HTTPS round trip to Pulse took ~1s. Done inline it delayed every
            // response by that much, with the session lock still held, so each of a
            // user's requests queued behind the previous one's telemetry.
            if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
            if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();       // PHP-FPM
            elseif (function_exists('litespeed_finish_request')) litespeed_finish_request(); // LiteSpeed (production)
            pulse_send_event($event);
        });
    });
}

pulse_register();
