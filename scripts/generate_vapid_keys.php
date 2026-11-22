<?php
/**
 * One-off CLI utility: generates a VAPID keypair for Web Push.
 *
 * Run once, then paste the output into includes/config-sql.ini under a
 * [webpush] section (see includes/config-sql.ini.example for the format).
 */

// CLI only: these files run schema/maintenance work and must never answer a web request.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }


require_once __DIR__ . '/../vendor/autoload.php';

$keys = \Minishlink\WebPush\VAPID::createVapidKeys();

echo "vapid_public_key  = \"{$keys['publicKey']}\"\n";
echo "vapid_private_key = \"{$keys['privateKey']}\"\n";
