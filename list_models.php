<?php
// List available models

// 1. Load API Key
$config = null;
$configFiles = ['config-sql.ini', 'config.ini'];
foreach ($configFiles as $configFile) {
    if (file_exists($configFile)) {
        $config = parse_ini_file($configFile);
        if ($config !== false) break;
    }
}

if ($config === false || !isset($config['GEMINI_API_KEY'])) {
    die("Error: GEMINI_API_KEY not found in config files.\n");
}

$apiKey = $config['GEMINI_API_KEY'];

$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $apiKey;

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if (isset($data['models'])) {
    foreach ($data['models'] as $model) {
        echo "Model: " . $model['name'] . "\n";
        echo "Supported Generation Methods: " . implode(', ', $model['supportedGenerationMethods']) . "\n";
        echo "---\n";
    }
} else {
    echo "Error listing models: " . substr($response, 0, 200) . "\n";
}
