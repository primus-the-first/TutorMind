<?php

/**
 * Pulse Monitor — sends request events to the Pulse backend.
 * Uses register_shutdown_function so it fires AFTER the response
 * is sent, capturing the real status code and duration.
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

function pulse_register(): void {
    if (!PULSE_ENABLED) return;

    $startTime  = microtime(true);
    $traceId    = bin2hex(random_bytes(8));
    $spanId     = bin2hex(random_bytes(4));
    $method     = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $endpoint   = strtok($_SERVER['REQUEST_URI'] ?? '/', '?'); // strip query string

    register_shutdown_function(function() use ($startTime, $traceId, $spanId, $method, $endpoint) {
        $duration   = round((microtime(true) - $startTime) * 1000, 2); // ms
        $statusCode = http_response_code() ?: 200;

        $error = error_get_last();
        $errorMessage = null;
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $errorMessage = $error['message'];
            $statusCode   = 500;
        }

        pulse_send_event([
            'traceId'      => $traceId,
            'spanId'       => $spanId,
            'service'      => PULSE_SERVICE,
            'endpoint'     => $endpoint,
            'method'       => $method,
            'statusCode'   => $statusCode,
            'duration'     => $duration,
            'errorMessage' => $errorMessage,
        ]);
    });
}

pulse_register();
