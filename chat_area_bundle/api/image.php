<?php
/**
 * Image Generation Microservice
 * 
 * Handles all image generation requests using Google's Imagen API
 * Can be called internally or as a standalone service
 */

header('Content-Type: application/json');

// Allow internal calls from same domain
$internal_call = isset($_SERVER['HTTP_X_INTERNAL_CALL']) && $_SERVER['HTTP_X_INTERNAL_CALL'] === 'tutormind';

// For now, only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Load configuration
function loadConfig() {
    $configFiles = ['../config-sql.ini', '../config.ini'];
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

/**
 * Generate image using Imagen API
 * 
 * @param string $prompt - Text description of the image to generate
 * @param string $apiKey - Google API key
 * @param array $options - Optional parameters (aspectRatio, etc.)
 * @return array - Array with success status and either imageData or error
 */
function generateImage($prompt, $apiKey, $options = []) {
    // Model selection - use the stable ultra version
    $model = 'imagen-4.0-ultra-generate-001';
    
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:predict?key=" . $apiKey;
    
    // Build payload
    $payload = [
        'instances' => [
            ['prompt' => $prompt]
        ],
        'parameters' => [
            'sampleCount' => $options['sampleCount'] ?? 1,
            'aspectRatio' => $options['aspectRatio'] ?? '1:1'
        ]
    ];
    
    error_log("Image Service: Generating image with prompt: " . substr($prompt, 0, 100));
    
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 60
    ]);

    $response = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        error_log("Image Service: cURL error - " . $curl_error);
        throw new Exception("Network error: " . $curl_error);
    }

    if ($http_status !== 200) {
        $errorBody = json_decode($response, true);
        $errorMessage = $errorBody['error']['message'] ?? substr($response, 0, 200);
        
        error_log("Image Service: HTTP {$http_status} - {$errorMessage}");
        
        // Provide helpful error messages based on status code
        if ($http_status === 400 && strpos($errorMessage, 'billed') !== false) {
            throw new Exception("Image generation requires billing to be enabled on your Google Cloud account");
        } elseif ($http_status === 429) {
            throw new Exception("Rate limit exceeded. Please try again in a moment");
        } elseif ($http_status === 403) {
            throw new Exception("API key does not have permission for image generation");
        } else {
            throw new Exception("Imagen API Error (HTTP {$http_status}): " . $errorMessage);
        }
    }

    $data = json_decode($response, true);
    
    if (!isset($data['predictions'][0]['bytesBase64Encoded'])) {
        error_log("Image Service: No image data in response - " . substr($response, 0, 200));
        throw new Exception("No image data returned from API");
    }

    $base64 = $data['predictions'][0]['bytesBase64Encoded'];
    $mimeType = $data['predictions'][0]['mimeType'] ?? 'image/png';
    
    error_log("Image Service: Image generated successfully");
    
    return [
        'success' => true,
        'imageData' => "data:{$mimeType};base64,{$base64}",
        'mimeType' => $mimeType,
        'prompt' => $prompt
    ];
}

// Main execution
try {
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['prompt']) || empty(trim($input['prompt']))) {
        throw new Exception('Prompt is required');
    }
    
    $prompt = trim($input['prompt']);
    $options = $input['options'] ?? [];
    
    // Load API key
    $config = loadConfig();
    $apiKey = $config['GEMINI_API_KEY'];
    
    // Generate image
    $result = generateImage($prompt, $apiKey, $options);
    
    // Return success response
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Image Service: Error - " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
