<?php
/**
 * Image generation (Google Imagen)
 *
 * Shared by api/image.php (logged-in HTTP endpoint) and ai_service.php (the chat
 * tutor's generate_image tool call). The chat path used to call image.php over HTTP
 * using a client-controlled Host header; it now calls this in-process.
 */

// Aspect ratios Imagen accepts; anything else falls back to square.
const IMAGEN_ASPECT_RATIOS = ['1:1', '3:4', '4:3', '9:16', '16:9'];

/**
 * Generate an image using the Imagen API
 *
 * @param string $prompt - Text description of the image to generate
 * @param string $apiKey - Google API key
 * @param array $options - Optional parameters (aspectRatio only; sampleCount is always 1)
 * @return array - ['success' => true, 'imageData', 'mimeType', 'prompt']
 * @throws Exception on any failure
 */
function generateImage($prompt, $apiKey, $options = []) {
    // Model selection - use the stable ultra version
    $model = 'imagen-4.0-ultra-generate-001';

    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:predict?key=" . $apiKey;

    $aspectRatio = $options['aspectRatio'] ?? '1:1';
    if (!is_string($aspectRatio) || !in_array($aspectRatio, IMAGEN_ASPECT_RATIOS, true)) {
        $aspectRatio = '1:1';
    }

    // Build payload. sampleCount is fixed: it is billed per image, so callers never choose it.
    $payload = [
        'instances' => [
            ['prompt' => $prompt]
        ],
        'parameters' => [
            'sampleCount' => 1,
            'aspectRatio' => $aspectRatio
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
