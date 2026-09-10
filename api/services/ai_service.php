<?php
/**
 * AI Provider Service
 * Handles all external AI API calls: Gemini, Groq, DeepSeek, and Imagen.
 */

// llama-3.3-70b-versatile and llama-3.1-8b-instant were retired by Groq (confirmed
// via GET /openai/v1/models — neither appears in the current catalog, which is why
// this fallback started hard-failing with HTTP 404 "model does not exist"). Replaced
// with openai/gpt-oss-120b/20b, both currently active.
//
// IMPORTANT: the limits below are the account's TOKENS-PER-MINUTE rate limit, NOT
// the model's context window (131072 for both — a much bigger, unrelated number).
// Confirmed via the x-ratelimit-limit-tokens response header on this account's
// on_demand tier: both models are capped at 8000 TPM. An earlier fix here mistakenly
// used the context-window figure, which let the input budget run 10x+ over the real
// limit and immediately started throwing HTTP 413 "Request too large... TPM Limit
// 8000" — verify against the live header (not the model's advertised context
// window) if this account's tier ever changes.
function callGroqAPI($chatHistory, $systemPrompt, $apiKey, $model = 'openai/gpt-oss-120b') {
    $apiUrl = "https://api.groq.com/openai/v1/chat/completions";

    // Token limits per model (input tokens, leave headroom for the 2048-token response
    // within the shared 8000 TPM ceiling — see note above).
    $tokenLimits = [
        'openai/gpt-oss-120b' => 5000,
        'openai/gpt-oss-20b'  => 5000,
    ];
    $maxInputTokens = $tokenLimits[$model] ?? 5000;

    // Build history messages newest-first so we can truncate the oldest
    $historyMessages = [];
    foreach ($chatHistory as $msg) {
        $role = $msg['role'] === 'model' ? 'assistant' : 'user';
        $content = '';
        if (isset($msg['parts'])) {
            foreach ($msg['parts'] as $part) {
                if (isset($part['text'])) $content .= $part['text'] . "\n";
            }
        }
        if (!empty(trim($content))) {
            $historyMessages[] = ['role' => $role, 'content' => trim($content)];
        }
    }

    // Estimate tokens (4 chars ≈ 1 token) and trim oldest messages to stay within limit
    $estimateTokens = fn(string $s) => (int)(strlen($s) / 4);
    $usedTokens = $estimateTokens($systemPrompt) + 200; // base overhead
    $keptMessages = [];
    foreach (array_reverse($historyMessages) as $msg) {
        $cost = $estimateTokens($msg['content']) + 10;
        if ($usedTokens + $cost > $maxInputTokens) break;
        $usedTokens += $cost;
        $keptMessages[] = $msg;
    }
    $keptMessages = array_reverse($keptMessages);

    $messages = array_merge(
        [['role' => 'system', 'content' => $systemPrompt]],
        $keptMessages
    );

    $payload = json_encode([
        'model' => $model,
        'messages' => $messages,
        'max_tokens' => 2048,
        'temperature' => 0.7,
        'stream' => false
    ]);

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_TIMEOUT => 90
    ]);

    $response = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        throw new Exception('Groq cURL Error: ' . $curl_error);
    }

    if ($http_status !== 200) {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['error']['message'] ?? substr($response, 0, 200);

        // If the 120b model exceeded the TPM limit, retry with the smaller 20b model
        if ($http_status === 413 && $model === 'openai/gpt-oss-120b') {
            error_log("Groq 120b TPM limit hit, retrying with openai/gpt-oss-20b");
            return callGroqAPI($chatHistory, $systemPrompt, $apiKey, 'openai/gpt-oss-20b');
        }

        throw new Exception('Groq API Error (HTTP ' . $http_status . '): ' . $errorMsg);
    }

    $data = json_decode($response, true);

    if (isset($data['choices'][0]['message']['content'])) {
        return [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $data['choices'][0]['message']['content']]
                        ]
                    ]
                ]
            ],
            'usedFallback' => 'groq'
        ];
    }

    throw new Exception('Groq returned unexpected response structure.');
}

function callDeepSeekAPI($chatHistory, $systemPrompt, $apiKey) {
    $apiUrl = "https://api.deepseek.com/v1/chat/completions";

    $messages = [
        ['role' => 'system', 'content' => $systemPrompt]
    ];

    foreach ($chatHistory as $msg) {
        $role = $msg['role'] === 'model' ? 'assistant' : 'user';
        $content = '';

        if (isset($msg['parts'])) {
            foreach ($msg['parts'] as $part) {
                if (isset($part['text'])) {
                    $content .= $part['text'] . "\n";
                }
            }
        }

        if (!empty(trim($content))) {
            $messages[] = ['role' => $role, 'content' => trim($content)];
        }
    }

    $payload = json_encode([
        'model' => 'deepseek-chat',
        'messages' => $messages,
        'max_tokens' => 8192,
        'temperature' => 0.7,
        'stream' => false
    ]);

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_TIMEOUT => 90
    ]);

    $response = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        throw new Exception('DeepSeek cURL Error: ' . $curl_error);
    }

    if ($http_status !== 200) {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['error']['message'] ?? substr($response, 0, 200);
        throw new Exception('DeepSeek API Error (HTTP ' . $http_status . '): ' . $errorMsg);
    }

    $data = json_decode($response, true);

    if (isset($data['choices'][0]['message']['content'])) {
        return [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $data['choices'][0]['message']['content']]
                        ]
                    ]
                ]
            ],
            'usedFallback' => 'deepseek'
        ];
    }

    throw new Exception('DeepSeek returned unexpected response structure.');
}

function callGeminiAPI($payload, $apiKey) {
    $model = 'gemini-2.5-flash';

    $payloadArr = json_decode($payload, true);
    $payloadArr['tools'] = [
        [
            'function_declarations' => [
                [
                    'name' => 'generate_image',
                    'description' => 'Generate an image based on a detailed text prompt. Use this when the user explicitly asks to create, draw, generate, or visualize an image. Do not use for displaying existing images.',
                    'parameters' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'prompt' => [
                                'type' => 'STRING',
                                'description' => 'A highly detailed, descriptive prompt for the image to generate. Include style, colors, composition, and other visual details.'
                            ],
                            'aspectRatio' => [
                                'type' => 'STRING',
                                'description' => 'Aspect ratio for the image. Options: "1:1", "16:9", "9:16", "4:3", "3:4". Default is "1:1".',
                                'enum' => ['1:1', '16:9', '9:16', '4:3', '3:4']
                            ]
                        ],
                        'required' => ['prompt']
                    ]
                ]
            ]
        ]
    ];
    $payload = json_encode($payloadArr);

    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;

    $retries = 0;
    $max_retries = 5;
    $delay = 2;
    // Separate, smaller budget for cURL-level failures (timeout, connection
    // reset) — each attempt can itself take up to CURLOPT_TIMEOUT seconds,
    // unlike a 429/503 which fails fast, so this can't share $max_retries
    // without risking the 300s set_time_limit() in server_mysql.php.
    $curl_retries = 0;
    $max_curl_retries = 2;

    while ($retries < $max_retries) {
        if (!function_exists('curl_init')) {
            throw new Exception('PHP cURL extension is not enabled.');
        }

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 60
        ]);

        $response = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            $curl_retries++;
            if ($curl_retries >= $max_curl_retries) {
                throw new Exception('cURL Error: ' . $curl_error);
            }
            error_log("Gemini: cURL error ({$curl_error}), retrying ({$curl_retries}/{$max_curl_retries})");
            sleep(1);
            continue;
        }

        if ($http_status === 429 || $http_status === 503) {
            $retries++;

            if ($model === 'gemini-3-flash-preview' && $retries > 1) {
                $model = 'gemini-2.5-flash';
                $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;
                continue;
            }

            if ($retries >= $max_retries)
                throw new Exception('AI service rate limit exceeded. Please try again in a moment.');
            sleep($delay);
            $delay *= 2;
            continue;
        }

        if ($http_status !== 200) {
            throw new Exception('AI service returned an error: HTTP ' . $http_status . ' - ' . substr($response, 0, 200));
        }

        $responseData = json_decode($response, true);

        // Gemini sometimes misidentifies code blocks as function call arguments.
        // When this happens it returns MALFORMED_FUNCTION_CALL with no content.
        // Retry once without the tools declaration so a normal text response is returned.
        if (isset($responseData['candidates'][0]['finishReason']) &&
            $responseData['candidates'][0]['finishReason'] === 'MALFORMED_FUNCTION_CALL') {
            error_log("Gemini: MALFORMED_FUNCTION_CALL detected, retrying without tools");
            $payloadArr = json_decode($payload, true);
            unset($payloadArr['tools']);
            $payload = json_encode($payloadArr);
            $retries = $max_retries - 1; // allow exactly one more attempt
            continue;
        }

        if (isset($responseData['candidates'][0]['content']['parts'][0]['functionCall'])) {
            $functionCall = $responseData['candidates'][0]['content']['parts'][0]['functionCall'];
            $functionName = $functionCall['name'];

            if ($functionName === 'generate_image') {
                $functionArgs = $functionCall['args'];
                $imagePrompt = $functionArgs['prompt'];
                $aspectRatio = $functionArgs['aspectRatio'] ?? '1:1';

                error_log("Chat Service: AI requested image generation - " . substr($imagePrompt, 0, 100));

                try {
                    $imageServiceUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
                        . "://{$_SERVER['HTTP_HOST']}/api/image.php";

                    $imagePayload = json_encode([
                        'prompt' => $imagePrompt,
                        'options' => ['aspectRatio' => $aspectRatio]
                    ]);

                    $ch = curl_init($imageServiceUrl);
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => $imagePayload,
                        CURLOPT_HTTPHEADER => [
                            'Content-Type: application/json',
                            'X-Internal-Call: tutormind'
                        ],
                        CURLOPT_TIMEOUT => 90
                    ]);

                    $imageResponse = curl_exec($ch);
                    $imageHttpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    $imageResult = json_decode($imageResponse, true);

                    if ($imageHttpStatus === 200 && isset($imageResult['success']) && $imageResult['success']) {
                        $imageData = $imageResult['imageData'];
                        error_log("Chat Service: Image generation succeeded");

                        return [
                            'candidates' => [
                                [
                                    'content' => [
                                        'parts' => [
                                            [
                                                'text' => "Here is the image I created for you:\n\n![Generated Image]({$imageData})\n\n*Prompt: {$imagePrompt}*"
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ];
                    } else {
                        $errorMsg = $imageResult['error'] ?? 'Unknown error from image service';
                        error_log("Chat Service: Image generation failed - " . $errorMsg);

                        return [
                            'candidates' => [
                                [
                                    'content' => [
                                        'parts' => [
                                            [
                                                'text' => "I tried to generate an image but encountered an issue:\n\n**Error:** {$errorMsg}\n\nThis could be due to:\n- Image generation requires billing enabled on Google Cloud\n- API quota limits\n- Content policy restrictions\n\nWould you like me to help you with something else?"
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ];
                    }

                } catch (Exception $e) {
                    error_log("Chat Service: Exception calling image service - " . $e->getMessage());

                    return [
                        'candidates' => [
                            [
                                'content' => [
                                    'parts' => [
                                        [
                                            'text' => "I apologize, but I'm unable to generate images at the moment due to a technical issue. I can still help you with text-based tasks, explanations, and problem-solving. What else can I assist you with?"
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ];
                }
            }
        }

        return $responseData;
    }
}

function generateImageWithImagen($prompt, $apiKey)
{
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/imagen-4.0-ultra-generate-001:predict?key=" . $apiKey;

    $payload = json_encode([
        'instances' => [
            ['prompt' => $prompt]
        ],
        'parameters' => [
            'sampleCount' => 1,
            'aspectRatio' => '1:1'
        ]
    ]);

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 60
    ]);

    $response = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_status !== 200) {
        $errorBody = json_decode($response, true);
        $errorMessage = $errorBody['error']['message'] ?? substr($response, 0, 200);

        if ($http_status === 400 && strpos($errorMessage, 'billed users') !== false) {
            throw new Exception("Image generation requires a billed Google Cloud account. Please enable billing for your project.");
        }

        throw new Exception("Imagen API Error (HTTP $http_status): " . $errorMessage);
    }

    $data = json_decode($response, true);

    if (isset($data['predictions'][0]['bytesBase64Encoded'])) {
        $base64 = $data['predictions'][0]['bytesBase64Encoded'];
        $mimeType = $data['predictions'][0]['mimeType'] ?? 'image/png';
        return "data:{$mimeType};base64,{$base64}";
    }

    throw new Exception("No image data found in response.");
}
