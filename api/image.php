<?php
/**
 * Image Generation Endpoint
 *
 * Handles image generation requests using Google's Imagen API.
 * Requires a logged-in session. The chat tutor does NOT go through here; it calls
 * generateImage() in-process (see services/image_generation.php).
 */

require_once __DIR__ . '/../includes/check_auth.php';
require_once __DIR__ . '/../includes/csrf.php';
requireCSRFForWrites(); // writes need the X-CSRF-Token header
require_once __DIR__ . '/../includes/db_mysql.php';
require_once __DIR__ . '/../includes/usage_quota.php';
require_once __DIR__ . '/services/image_generation.php';

header('Content-Type: application/json');

// For now, only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Load configuration
function loadConfig() {
    $configFiles = [__DIR__ . '/../includes/config-sql.ini', __DIR__ . '/../includes/config.ini'];
    foreach ($configFiles as $configFile) {
        if (file_exists($configFile)) {
            $config = parse_ini_file($configFile);
            if ($config !== false && isset($config['GEMINI_API_KEY'])) {
                return $config;
            }
        }
    }
    throw new Exception('Configuration file not found or API key missing');
}

// Main execution
try {
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input) || !isset($input['prompt']) || !is_string($input['prompt']) || empty(trim($input['prompt']))) {
        throw new Exception('Prompt is required');
    }

    $prompt = trim($input['prompt']);
    $options = is_array($input['options'] ?? null) ? $input['options'] : [];

    // Load API key
    $config = loadConfig();
    $apiKey = $config['GEMINI_API_KEY'];

    // Imagen is billed per image: cap how many a student can make per day
    requireQuota(getDbConnection(), (int) $_SESSION['user_id'], 'image');

    // Generate image (sampleCount and aspectRatio are validated inside)
    $result = generateImage($prompt, $apiKey, $options);

    // Return success response
    echo json_encode($result);

} catch (Exception $e) {
    error_log("Image Service: Error - " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Could not generate the image. Please try again.'
    ]);
}
