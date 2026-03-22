<?php
/**
 * Text-to-Speech API
 *
 * Fallback chain: ElevenLabs → inference.sh → Browser TTS
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

$elevenLabsApiKey = is_array($config) ? ($config['ELEVEN_LABS_API'] ?? $config['ELEVENLABS_API_KEY'] ?? null) : null;

// Handle request
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
        exit;
    }

    $text = $input['text'] ?? '';
    $provider = $input['provider'] ?? 'auto'; // auto, elevenlabs, infsh, browser

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

    // Provider selection logic
    if ($provider === 'auto') {
        // Try ElevenLabs first if configured
        if ($elevenLabsApiKey) {
            $result = tryElevenLabs($text, $elevenLabsApiKey, $input['voice_id'] ?? null);
            if ($result['success'] && !isset($result['fallback'])) {
                echo json_encode($result);
                exit;
            }
        }

        // Try inference.sh (Kokoro TTS)
        $result = tryInferenceSh($text, $input['model'] ?? 'kokoro');
        if ($result['success'] && !isset($result['fallback'])) {
            echo json_encode($result);
            exit;
        }

        // Fallback to browser TTS
        echo json_encode([
            'success' => true,
            'fallback' => true,
            'text' => $text,
            'message' => 'Using browser TTS.'
        ]);
        exit;
    }

    // Specific provider requested
    switch ($provider) {
        case 'elevenlabs':
            if (!$elevenLabsApiKey) {
                echo json_encode(['success' => false, 'error' => 'ElevenLabs API key not configured']);
                exit;
            }
            echo json_encode(tryElevenLabs($text, $elevenLabsApiKey, $input['voice_id'] ?? null));
            break;

        case 'infsh':
            echo json_encode(tryInferenceSh($text, $input['model'] ?? 'kokoro'));
            break;

        case 'browser':
        default:
            echo json_encode([
                'success' => true,
                'fallback' => true,
                'text' => $text
            ]);
            break;
    }

} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}

/**
 * Try ElevenLabs TTS
 */
function tryElevenLabs($text, $apiKey, $voiceId = null) {
    try {
        // Voice ID for "Rachel" - a natural female voice
        $voiceId = $voiceId ?? '21m00Tcm4TlvDq8ikWAM';

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
                'xi-api-key: ' . $apiKey,
                'Accept: audio/mpeg'
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $audioData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($audioData === false) {
            error_log("ElevenLabs cURL error: " . $curlError);
            return [
                'success' => true,
                'fallback' => true,
                'text' => $text,
                'message' => 'ElevenLabs connection error.'
            ];
        }

        if ($httpCode !== 200) {
            error_log("ElevenLabs API error: HTTP $httpCode");
            return [
                'success' => true,
                'fallback' => true,
                'text' => $text,
                'message' => 'ElevenLabs API error.'
            ];
        }

        return [
            'success' => true,
            'audio' => base64_encode($audioData),
            'contentType' => 'audio/mpeg',
            'provider' => 'elevenlabs'
        ];

    } catch (Exception $e) {
        error_log("ElevenLabs Error: " . $e->getMessage());
        return [
            'success' => true,
            'fallback' => true,
            'text' => $text,
            'message' => 'ElevenLabs error.'
        ];
    }
}

/**
 * Try inference.sh TTS (Kokoro, DIA, Chatterbox, etc.)
 */
function tryInferenceSh($text, $model = 'kokoro') {
    // Check if infsh CLI is available
    $infshPath = shell_exec('which infsh 2>/dev/null') ?: shell_exec('where infsh 2>nul');

    if (empty(trim($infshPath ?? ''))) {
        error_log("inference.sh CLI (infsh) not found");
        return [
            'success' => true,
            'fallback' => true,
            'text' => $text,
            'message' => 'inference.sh CLI not installed.'
        ];
    }

    // Map model names to app IDs
    $modelMap = [
        'kokoro' => 'infsh/kokoro-tts',
        'dia' => 'infsh/dia-tts',
        'chatterbox' => 'infsh/chatterbox',
        'higgs' => 'infsh/higgs-audio',
        'vibevoice' => 'infsh/vibevoice'
    ];

    $appId = $modelMap[$model] ?? $modelMap['kokoro'];

    try {
        // Escape text for shell
        $escapedText = escapeshellarg($text);

        // Create temp file for input JSON
        $tempInput = tempnam(sys_get_temp_dir(), 'tts_input_');
        file_put_contents($tempInput, json_encode(['text' => $text]));

        try {
            // Run inference.sh
            $cmd = "infsh app run {$appId} --input " . escapeshellarg($tempInput) . " --output json 2>&1";
            $output = shell_exec($cmd);

            if (empty($output)) {
                error_log("inference.sh returned empty output");
                return [
                    'success' => true,
                    'fallback' => true,
                    'text' => $text,
                    'message' => 'inference.sh returned no output.'
                ];
            }

            // Parse output
            $result = json_decode($output, true);

            if (!$result || !isset($result['output'])) {
                error_log("inference.sh invalid output: " . substr($output, 0, 200));
                return [
                    'success' => true,
                    'fallback' => true,
                    'text' => $text,
                    'message' => 'inference.sh parsing error.'
                ];
            }

            // Check for audio URL in output
            $audioUrl = $result['output']['audio_url'] ?? $result['output']['url'] ?? null;

            if ($audioUrl) {
                // Validate HTTPS URL
                if (!filter_var($audioUrl, FILTER_VALIDATE_URL) || parse_url($audioUrl, PHP_URL_SCHEME) !== 'https') {
                    throw new Exception("Invalid or non-HTTPS audio URL");
                }
                
                // Basic SSRF protection for common local/private IPs
                $host = parse_url($audioUrl, PHP_URL_HOST);
                if (empty($host) || in_array($host, ['localhost', '127.0.0.1', '::1']) || preg_match('/^(10\.|172\.1[6-9]\.|172\.2[0-9]\.|172\.3[0-1]\.|192\.168\.)/', $host)) {
                    throw new Exception("Forbidden audio URL host");
                }

                // Fetch the audio file using cURL
                $ch = curl_init($audioUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 3,
                    CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                    CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_FAILONERROR => true
                ]);
                
                $audioData = curl_exec($ch);
                curl_close($ch);

                if ($audioData) {
                    return [
                        'success' => true,
                        'audio' => base64_encode($audioData),
                        'contentType' => 'audio/wav',
                        'provider' => 'infsh-' . $model
                    ];
                }
            }

            // Check for base64 audio in output
            if (isset($result['output']['audio'])) {
                return [
                    'success' => true,
                    'audio' => $result['output']['audio'],
                    'contentType' => $result['output']['content_type'] ?? 'audio/wav',
                    'provider' => 'infsh-' . $model
                ];
            }

            return [
                'success' => true,
                'fallback' => true,
                'text' => $text,
                'message' => 'inference.sh no audio in response.'
            ];
        } finally {
            // Clean up temp file
            if (file_exists($tempInput)) {
                unlink($tempInput);
            }
        }

    } catch (Exception $e) {
        error_log("inference.sh Error: " . $e->getMessage());
        return [
            'success' => true,
            'fallback' => true,
            'text' => $text,
            'message' => 'inference.sh error.'
        ];
    }
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
