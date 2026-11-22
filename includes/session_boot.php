<?php
/**
 * Session cookie hardening. Require this BEFORE session_start().
 *
 * Safe to load early and more than once: it does nothing once a session is
 * already active or output has started, and never starts a session itself.
 *
 *  - lifetime 0     : session cookie, gone when the browser closes
 *  - path '/'       : one session across the whole app
 *  - httponly       : page JavaScript can't read the cookie
 *  - samesite Lax   : not sent on cross-site POSTs (CSRF defence in depth);
 *                     still sent on normal top-level link navigation
 *  - secure         : only when the request is HTTPS, so http://localhost still works
 *  - strict mode    : PHP refuses a session id it didn't issue (session fixation)
 *  - only cookies   : never accept a session id from the URL
 */

if (!function_exists('tm_request_is_https')) {
    /** True when this request reached us over HTTPS (directly, or via a proxy/CDN). */
    function tm_request_is_https(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';
        if ($https !== '' && strtolower((string)$https) !== 'off') {
            return true;
        }
        if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
            return true;
        }
        return false;
    }
}

if (!function_exists('tm_session_boot')) {
    function tm_session_boot(): void
    {
        if (session_status() !== PHP_SESSION_NONE || headers_sent()) {
            return; // too late to change cookie params; leave the active session alone
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => tm_request_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

tm_session_boot();
