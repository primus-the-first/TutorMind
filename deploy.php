<?php
// --- Configuration ---
$secret = 'Shadowborn'; // CHANGE THIS to a secure random string
$branch = 'main'; // or 'master', depending on your repository default
$logFile = '../deploy.log'; // Path to log file (outside public_html if possible)

// --- Verification ---
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '';
if (!$signature) {
    http_response_code(403);
    die('Signature missing.');
}

list($algo, $hash) = explode('=', $signature, 2);
$payload = file_get_contents('php://input');
$payloadHash = hash_hmac($algo, $payload, $secret);

if (!hash_equals($hash, $payloadHash)) {
    http_response_code(403);
    die('Invalid signature.');
}

// --- Execution ---
// Navigate to the repository root (usually one level up from public_html, or just . if public_html IS the root)
// Adjust this path if your .git folder is not in the current directory.
// For many shared hosts, public_html is the root of the site, so we stay here.
$cmd = "git pull origin {$branch} 2>&1";

// Execute the command
exec($cmd, $output, $return_var);

// --- Logging ---
$log = date('[Y-m-d H:i:s] ') . "Deployment attempted.\n";
$log .= "Command: $cmd\n";
$log .= "Output:\n" . implode("\n", $output) . "\n";
$log .= "Return Status: $return_var\n";
$log .= str_repeat('-', 40) . "\n";
file_put_contents($logFile, $log, FILE_APPEND);

// --- Output ---
echo ($return_var === 0) ? "Deployment successful." : "Deployment failed. Check logs.";
?>