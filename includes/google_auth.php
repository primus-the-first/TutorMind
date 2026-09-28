<?php
/**
 * Google Sign-In ID token verification — one place for login and for
 * re-confirming sensitive actions (account deletion).
 *
 * A token is only trusted when Google's tokeninfo endpoint accepts it (over
 * verified TLS) AND it was issued to TutorMind's own client ID (`aud`) by
 * Google (`iss`) and hasn't expired. Without the `aud` check, an ID token that
 * any other site obtained for its users could be replayed here.
 *
 * Callers decide what `email_verified` means for them: linking a Google login
 * to an existing account *by email* requires it.
 */

// Also in login.php / register.php (data-client_id) and settings.js — keep in sync.
const GOOGLE_CLIENT_ID = '1083917773706-gc0f400l24eavps3ckcnj04581gj3plk.apps.googleusercontent.com';

/**
 * @return array{sub:string,email:string,email_verified:bool,iat:int,payload:array}|null
 *         null when the token is missing, invalid, expired or not for TutorMind.
 * @throws RuntimeException when Google can't be reached (caller shows a retry message).
 */
function verifyGoogleIdToken(string $credential): ?array
{
    if ($credential === '' || strlen($credential) > 4096) return null;

    $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        $err = '[' . curl_errno($ch) . '] ' . curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("Google token check failed: $err");
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $p = json_decode($response, true);
    if ($httpCode !== 200 || !is_array($p) || isset($p['error']) || isset($p['error_description'])) return null;
    return googleTokenClaims($p);
}

/**
 * The checks on a tokeninfo payload, separate from the network call so they can be tested.
 * @return array|null see verifyGoogleIdToken()
 */
function googleTokenClaims(array $p): ?array
{
    if (($p['aud'] ?? '') !== GOOGLE_CLIENT_ID) return null;
    if (!in_array($p['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)) return null;
    if ((int)($p['exp'] ?? 0) < time()) return null;
    if (empty($p['sub'])) return null;

    return [
        'sub'            => (string)$p['sub'],
        'email'          => (string)($p['email'] ?? ''),
        'email_verified' => ($p['email_verified'] ?? '') === 'true' || ($p['email_verified'] ?? false) === true,
        'iat'            => (int)($p['iat'] ?? 0),
        'payload'        => $p,
    ];
}
