<?php
/**
 * AI Provider Service
 * Handles all external AI API calls: Gemini, Groq, DeepSeek, and Imagen.
 */

function callGroqAPI($chatHistory, $systemPrompt, $apiKey) {
    $apiUrl = "https://api.groq.com/openai/v1/chat/completions";

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
        'model' => 'llama-3.3-70b-versatile',
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
        throw new Exception('Groq cURL Error: ' . $curl_error);
    }

    if ($http_status !== 200) {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['error']['message'] ?? substr($response, 0, 200);
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

        if ($curl_error)
            throw new Exception('cURL Error: ' . $curl_error);

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
