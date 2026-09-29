<?php
/**
 * Transactional email through Brevo's HTTPS API (no SMTP, no extra library).
 *
 * Config — [brevo] section of the same file getDbConnection() uses
 * (config-sql.ini locally, config.ini in production):
 *     api_key      = xkeysib-...        ; Brevo → SMTP & API → API keys
 *     sender_email = no-reply@tutormind.app   ; must be a verified sender/domain in Brevo
 *     sender_name  = TutorMind
 *
 * Without an api_key the message is written to logs/mail.log instead of being
 * sent, so development and tests never email anyone.
 */

function getMailConfig(): array
{
    foreach (['config-sql.ini', 'config.ini'] as $file) {
        $path = __DIR__ . '/' . $file;
        if (file_exists($path)) {
            $config = parse_ini_file($path, true);
            return is_array($config['brevo'] ?? null) ? $config['brevo'] : [];
        }
    }
    return [];
}

/**
 * @param array $headers extra headers, e.g. List-Unsubscribe for reminder emails
 * @return bool true when Brevo accepted the message (or it was logged in dev)
 */
function sendEmail(string $toEmail, string $toName, string $subject, string $html, string $text, array $headers = []): bool
{
    $cfg = getMailConfig();
    $apiKey = trim($cfg['api_key'] ?? '');

    if ($apiKey === '') {
        $dir = __DIR__ . '/../logs';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        @file_put_contents($dir . '/mail.log', sprintf(
            "[%s] NOT SENT (no [brevo] api_key) -> %s <%s>\nSubject: %s\n%s%s\n\n",
            date('Y-m-d H:i:s'), $toName, $toEmail, $subject,
            $headers ? implode('', array_map(fn($k, $v) => "$k: $v\n", array_keys($headers), $headers)) : '', $text
        ), FILE_APPEND);
        @file_put_contents($dir . '/mail-last.html', $html); // open in a browser to preview the design
        return true;
    }

    $payload = [
        'sender'      => ['email' => $cfg['sender_email'] ?? 'no-reply@tutormind.app', 'name' => $cfg['sender_name'] ?? 'TutorMind'],
        'to'          => [['email' => $toEmail, 'name' => $toName !== '' ? $toName : $toEmail]],
        'subject'     => $subject,
        'htmlContent' => $html,
        'textContent' => $text,
    ];
    if ($headers) $payload['headers'] = $headers;

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['accept: application/json', 'content-type: application/json', 'api-key: ' . $apiKey],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        // Never log the key; Brevo's error body says what's wrong (unverified sender, bad key, quota…)
        error_log("Brevo send failed ($status) to $toEmail: " . ($response === false ? $error : substr($response, 0, 300)));
        return false;
    }
    return true;
}
