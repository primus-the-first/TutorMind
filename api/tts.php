<?php
/**
 * Text-to-Speech API using ElevenLabs
 * 
 * Converts text to natural-sounding speech.
 */

require_once __DIR__ . '/../check_auth.php';

header('Content-Type: application/json');

// Load API key from config
$configFiles = ['../config-sql.ini', '../config.ini'];
$config = null;

foreach ($configFiles as $configFile) {
    $path = __DIR__ . '/' . $configFile;
    if (file_exists($path)) {
        $config = parse_ini_file($path);
        if ($config !== false) break;
    }
}

$elevenLabsApiKey = $config['ELEVENLABS_API_KEY'] ?? null;

// Handle request
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $text = $input['text'] ?? '';
    
    if (empty($text)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Text is required']);
        exit;
    }
    
    // Limit text length to save API quota
    if (strlen($text) > 2000) {
        $text = substr($text, 0, 2000) . '...';
    }
    
    // Clean text for speech (remove markdown, code blocks, etc.)
    $text = cleanTextForSpeech($text);
    
    if (!$elevenLabsApiKey) {
        // Fallback: Return text for browser TTS
        echo json_encode([
            'success' => true,
            'fallback' => true,
            'text' => $text,
            'message' => 'ElevenLabs API key not configured. Using browser TTS.'
        ]);
        exit;
    }
    
    // ElevenLabs API call
    try {
        // Voice ID for "Rachel" - a natural female voice (free tier)
        // Other options: "21m00Tcm4TlvDq8ikWAM" (Rachel), "EXAVITQu4vr4xnSDxMaL" (Bella)
        $voiceId = $input['voice_id'] ?? '21m00Tcm4TlvDq8ikWAM';
        
        $url = "https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}";
        
        $payload = json_encode([
            'text' => $text,
            'model_id' => 'eleven_monolingual_v1',
            'voice_settings' => [
                'stability' => 0.5,
                'similarity_boost' => 0.75
            ]
        ]);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'xi-api-key: ' . $elevenLabsApiKey,
                'Accept: audio/mpeg'
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $audioData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("ElevenLabs API error: HTTP $httpCode - " . substr($audioData, 0, 200));
            // Fallback to browser TTS
            echo json_encode([
                'success' => true,
                'fallback' => true,
                'text' => $text,
                'message' => 'ElevenLabs API error. Using browser TTS.'
            ]);
            exit;
        }
        
        // Return audio as base64
        $audioBase64 = base64_encode($audioData);
        
        echo json_encode([
            'success' => true,
            'audio' => $audioBase64,
            'contentType' => 'audio/mpeg'
        ]);
        
    } catch (Exception $e) {
        error_log("TTS Error: " . $e->getMessage());
        echo json_encode([
            'success' => true,
            'fallback' => true,
            'text' => $text,
            'message' => 'TTS error. Using browser TTS.'
        ]);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

/**
 * Clean text for speech synthesis
 */
function cleanTextForSpeech($text) {
    // Remove code blocks
    $text = preg_replace('/```[\s\S]*?```/', '', $text);
    $text = preg_replace('/`[^`]+`/', '', $text);
    
    // Remove markdown formatting
    $text = preg_replace('/\*\*([^*]+)\*\*/', '$1', $text); // Bold
    $text = preg_replace('/\*([^*]+)\*/', '$1', $text);     // Italic
    $text = preg_replace('/#{1,6}\s+/', '', $text);         // Headers
    $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text); // Links
    
    // Remove bullet points and list markers
    $text = preg_replace('/^[\s]*[-*+]\s+/m', '', $text);
    $text = preg_replace('/^[\s]*\d+\.\s+/m', '', $text);
    
    // Clean up extra whitespace
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    $text = trim($text);
    
    return $text;
}
?>
