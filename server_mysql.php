<?php
ob_start(); // Start output buffering to prevent accidental output
// Enable error reporting for debugging during development
if (defined('PREDICTOR_SCRIPT')) {
    // This file is being included by predict.php, so just make functions available and stop.
    return;
}
error_reporting(E_ALL & ~E_DEPRECATED); // Report all errors except for deprecation notices
ini_set('display_errors', 0);
ini_set('log_errors', 1);
set_time_limit(300); // Increase max execution time to 5 minutes to allow for AI API timeouts/retries
require_once 'check_auth.php'; // Secure all API endpoints

// --- ALWAYS require the autoloader first ---
require_once 'db_mysql.php';
require 'vendor/autoload.php';
require_once 'api/knowledge.php';
require_once 'api/learning_strategies.php';

header('Content-Type: application/json');

if (!function_exists('formatResponse')) {
    function formatResponse($text)
    {
        $protections = [];
        $counter = 0;

        // IMPORTANT: Protect code blocks FIRST, before LaTeX or inline code
        // This prevents ${variables} in code from being caught by LaTeX $ protection
        $text = preg_replace_callback(
            '/```([\w]*)\n([\s\S]*?)```/s',
            function ($matches) use (&$protections, &$counter) {
                $placeholder = '@@PROTECT_' . $counter . '@@';
                $language = $matches[1]; // Language identifier (optional)
                $codeContent = $matches[2]; // The actual code
                $protections[$placeholder] = [
                    'type' => 'codeblock',
                    'language' => $language,
                    'content' => $codeContent
                ];
                $counter++;
                return $placeholder;
            },
            $text
        );

        // Protect inline code (`) - before LaTeX
        $text = preg_replace_callback(
            '/`([^`]+)`/s',
            function ($matches) use (&$protections, &$counter) {
                $placeholder = '@@PROTECT_' . $counter . '@@';
                // Store the inner content of the code block
                $protections[$placeholder] = ['type' => 'code', 'content' => $matches[1]];
                $counter++;
                return $placeholder;
            },
            $text
        );

        // Protect LaTeX expressions LAST (after all code is protected)
        $text = preg_replace_callback(
            '/\$\$([\s\S]*?)\$\$|\\\\\[([\s\S]*?)\\\\\]|\\\\\((.*?)\\\\\)|\$([^$]+?)\$/',
            function ($matches) use (&$protections, &$counter) {
                $placeholder = '@@PROTECT_' . $counter . '@@';
                // Store the original content, which includes the delimiters
                $protections[$placeholder] = ['type' => 'latex', 'content' => $matches[0]];
                $counter++;
                return $placeholder;
            },
            $text
        );

        // Process with Parsedown
        $Parsedown = new Parsedown();
        $Parsedown->setBreaksEnabled(true);
        $html = $Parsedown->text($text);

        // Restore protected content
        foreach ($protections as $placeholder => $protection) {
            if ($protection['type'] === 'latex') {
                $html = str_replace($placeholder, $protection['content'], $html);
            } elseif ($protection['type'] === 'codeblock') {
                // Restore code blocks with proper HTML
                $codeContent = htmlspecialchars($protection['content'], ENT_QUOTES, 'UTF-8');
                $language = !empty($protection['language']) ? ' class="language-' . htmlspecialchars($protection['language']) . '"' : '';
                $codeBlockHtml = '<pre' . $language . '><code>' . $codeContent . '</code></pre>';
                $html = str_replace($placeholder, $codeBlockHtml, $html);
            } elseif ($protection['type'] === 'code') {
                $codeContent = htmlspecialchars($protection['content'], ENT_QUOTES, 'UTF-8');
                $html = str_replace($placeholder, '<code>' . $codeContent . '</code>', $html);
            }
        }

        // Final cleanup: remove <p> tags from around display math
        $html = preg_replace('/<p>(\s*)(\$\$.*?\$\$)(\s*)<\/p>/s', '$2', $html);
        $html = preg_replace('/<p>(\s*)(\\\\\[.*?\\\\\])(\s*)<\/p>/s', '$2', $html);

        return $html;
    }
}

$action = $_REQUEST['action'] ?? null; // Use $_REQUEST to handle GET and POST actions

// Add a special case for logout to be handled by auth.php
if ($action === 'logout') {
    session_unset();
    session_destroy();
    header('Location: login.html');
    exit;
}

if ($action) {
    switch ($action) { // This switch now handles GET and some POST actions
        case 'history':
            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("SELECT id, title FROM conversations WHERE user_id = ? ORDER BY updated_at DESC");
                $stmt->execute([$_SESSION['user_id']]);
                $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ob_clean(); // Clear any previous output
                echo json_encode(['success' => true, 'history' => $history]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Could not fetch history.']);
            }
            break;

        case 'get_conversation':
            $convo_id = $_GET['id'] ?? null;
            if (!$convo_id) {
                echo json_encode(['success' => false, 'error' => 'Conversation ID is missing.']);
                break;
            }
            try {
                $pdo = getDbConnection();
                // First, verify the user owns this conversation
                $stmt = $pdo->prepare("SELECT id, title FROM conversations WHERE id = ? AND user_id = ?");
                $stmt->execute([$convo_id, $_SESSION['user_id']]);
                $conversation = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$conversation) {
                    echo json_encode(['success' => false, 'error' => 'Conversation not found or access denied.']);
                    break;
                }

                // PERFORMANCE: Fetch only the most recent 15 messages (visible on screen initially)
                // We order by created_at DESC first to get the latest, then reverse in PHP
                $stmt = $pdo->prepare("SELECT role, content FROM messages WHERE conversation_id = ? ORDER BY created_at DESC LIMIT 15");
                $stmt->execute([$convo_id]);
                $messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC)); // Reverse to show oldest-to-newest

                $conversation['chat_history'] = [];
                foreach ($messages as $message) {
                    // The 'content' is a JSON string of the 'parts' array, so we decode it.
                    $parts = json_decode($message['content'], true);

                    // PERFORMANCE OPTIMIZATION: Strip out heavy base64 image data for history loading
                    foreach ($parts as &$part) {
                        if (isset($part['inline_data']['data'])) {
                            // Replace the actual image data with a lightweight placeholder
                            // The frontend will show a nice "Image attached" indicator instead
                            $part['inline_data']['data'] = null; // Remove the heavy base64 string
                            $part['inline_data']['_removed'] = true; // Flag for frontend
                        }
                    }

                    if ($message['role'] === 'model') {
                        $parts[0]['text'] = formatResponse($parts[0]['text']);
                    }
                    $conversation['chat_history'][] = ['role' => $message['role'], 'parts' => $parts];
                }

                ob_clean();
                echo json_encode(['success' => true, 'conversation' => $conversation]);
            } catch (Exception $e) {
                ob_clean();
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Could not fetch conversation.']);
            }
            break;

        case 'delete_conversation':
            $convo_id = $_GET['id'] ?? null;
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("DELETE FROM conversations WHERE id = ? AND user_id = ?");
            $stmt->execute([$convo_id, $_SESSION['user_id']]);
            echo json_encode(['success' => true]);
            break;

        case 'rename_conversation':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
                break;
            }
            $convo_id = $_POST['id'] ?? null;
            $new_title = trim($_POST['title'] ?? '');

            if (!$convo_id || empty($new_title)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing conversation ID or title.']);
                break;
            }

            $pdo = getDbConnection();
            $stmt = $pdo->prepare("UPDATE conversations SET title = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
            $stmt->execute([$new_title, $convo_id, $_SESSION['user_id']]);
            echo json_encode(['success' => true]);
            break;

        case 'generate_suggestions':
            try {
                $pdo = getDbConnection();

                // Fetch user's field of study
                $stmt = $pdo->prepare("SELECT field_of_study FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $field = $user['field_of_study'] ?? 'General Knowledge';

                // Construct prompt for Gemini
                $prompt = "Generate 4 short, engaging, single-sentence learning tasks for a student studying '{$field}'. 
                Return ONLY a valid JSON object with keys: 'explain', 'write', 'build', 'research'.
                
                Example format:
                {
                    \"explain\": \"Explain the concept of recursion.\",
                    \"write\": \"Write a function to sort a list.\",
                    \"build\": \"Build a simple calculator app.\",
                    \"research\": \"Research the history of AI.\"
                }
                
                Make the tasks specific to '{$field}' but simple enough for a chat interaction.";

                // Call Gemini API (reusing the function defined later in this file, so we need to move it or duplicate logic)
                // Since functions are global in PHP, we can call callGeminiAPI if it's defined.
                // However, callGeminiAPI is defined at the bottom. We should move it up or just use the logic here.
                // For safety, let's just implement the call here directly to avoid scope issues if the function isn't hoisted yet (though PHP functions are).

                // Load API Key
                $config = null;
                $configFiles = ['config-sql.ini', 'config.ini'];
                foreach ($configFiles as $configFile) {
                    if (file_exists($configFile)) {
                        $config = parse_ini_file($configFile);
                        if ($config !== false)
                            break;
                    }
                }
                if ($config === false || !isset($config['GEMINI_API_KEY'])) {
                    throw new Exception('API key missing.');
                }
                $apiKey = $config['GEMINI_API_KEY'];

                $payload = json_encode([
                    "contents" => [
                        ["parts" => [["text" => $prompt]]]
                    ],
                    "generationConfig" => ["responseMimeType" => "application/json"]
                ]);
                
                $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent?key=" . $apiKey;
                
                $ch = curl_init($apiUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_TIMEOUT => 10
                ]);

                $response = curl_exec($ch);
                $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($http_status !== 200) {
                    throw new Exception("Gemini API error: $http_status");
                }

                $data = json_decode($response, true);
                $jsonText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
                $suggestions = json_decode($jsonText, true);

                echo json_encode(['success' => true, 'suggestions' => $suggestions]);

            } catch (Exception $e) {
                error_log("Suggestion generation error: " . $e->getMessage());
                // Fallback suggestions
                echo json_encode([
                    'success' => false,
                    'suggestions' => [
                        'explain' => 'Explain a complex topic simply.',
                        'write' => 'Write a creative story.',
                        'build' => 'Build a study plan.',
                        'research' => 'Research a new technology.'
                    ]
                ]);
            }
            break;

        case 'submit_feedback':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
                break;
            }
            
            $type = $_POST['type'] ?? null; // 'message_rating' or 'general'
            $rating = $_POST['rating'] ?? 'neutral'; // 'positive', 'negative', 'neutral'
            $comment = trim($_POST['comment'] ?? '');
            $conversation_id = $_POST['conversation_id'] ?? null;
            $message_index = $_POST['message_index'] ?? null;
            $page_url = $_POST['page_url'] ?? null;
            $category = $_POST['category'] ?? null;
            
            if (!$type || !in_array($type, ['message_rating', 'general'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid feedback type.']);
                break;
            }
            
            if (!in_array($rating, ['positive', 'negative', 'neutral'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid rating value.']);
                break;
            }
            
            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("
                    INSERT INTO feedback (user_id, type, rating, comment, conversation_id, message_index, page_url, category)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $type,
                    $rating,
                    $comment ?: null,
                    $conversation_id ?: null,
                    $message_index !== null ? (int)$message_index : null,
                    $page_url ?: null,
                    $category ?: null
                ]);
                
                echo json_encode(['success' => true, 'feedback_id' => $pdo->lastInsertId()]);
            } catch (Exception $e) {
                error_log("Feedback submission error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Could not save feedback.']);
            }
            break;

        case 'get_feedback':
            // For admin dashboard - retrieve feedback with optional filters
            try {
                $pdo = getDbConnection();
                
                $type_filter = $_GET['type'] ?? null;
                $rating_filter = $_GET['rating'] ?? null;
                $limit = min((int)($_GET['limit'] ?? 50), 200);
                $offset = (int)($_GET['offset'] ?? 0);
                
                $sql = "SELECT f.*, u.username, u.email 
                        FROM feedback f 
                        LEFT JOIN users u ON f.user_id = u.id 
                        WHERE 1=1";
                $params = [];
                
                if ($type_filter && in_array($type_filter, ['message_rating', 'general'])) {
                    $sql .= " AND f.type = ?";
                    $params[] = $type_filter;
                }
                if ($rating_filter && in_array($rating_filter, ['positive', 'negative', 'neutral'])) {
                    $sql .= " AND f.rating = ?";
                    $params[] = $rating_filter;
                }
                
                $sql .= " ORDER BY f.created_at DESC LIMIT ? OFFSET ?";
                $params[] = $limit;
                $params[] = $offset;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $feedback = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get total count for pagination
                $countSql = "SELECT COUNT(*) FROM feedback WHERE 1=1";
                $countParams = [];
                if ($type_filter && in_array($type_filter, ['message_rating', 'general'])) {
                    $countSql .= " AND type = ?";
                    $countParams[] = $type_filter;
                }
                if ($rating_filter && in_array($rating_filter, ['positive', 'negative', 'neutral'])) {
                    $countSql .= " AND rating = ?";
                    $countParams[] = $rating_filter;
                }
                $countStmt = $pdo->prepare($countSql);
                $countStmt->execute($countParams);
                $total = $countStmt->fetchColumn();
                
                // Get summary stats
                $statsSql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN rating = 'positive' THEN 1 ELSE 0 END) as positive,
                    SUM(CASE WHEN rating = 'negative' THEN 1 ELSE 0 END) as negative,
                    SUM(CASE WHEN rating = 'neutral' THEN 1 ELSE 0 END) as neutral
                    FROM feedback";
                $statsStmt = $pdo->query($statsSql);
                $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true, 
                    'feedback' => $feedback,
                    'total' => (int)$total,
                    'stats' => $stats
                ]);
            } catch (Exception $e) {
                error_log("Feedback retrieval error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Could not retrieve feedback.']);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action.']);
            break;
    }
    exit;
}

// --- Main Chat Logic (handles POST requests without an 'action' parameter) ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method for chat.']);
    exit;
}

// --- Check for large file upload error ---
// If the request method is POST but the POST and FILES arrays are empty, it's a classic sign
// that the upload exceeded the server's post_max_size limit.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
    throw new Exception("The uploaded file is too large. It exceeds the server's configured limit.");
}

$question = $_POST['question'] ?? '';
$learningLevel = $_POST['learningLevel'] ?? 'Understanding';

// Session context from frontend SessionContextManager
$session_goal = $_POST['session_goal'] ?? null;
$session_context = null;
if (!empty($_POST['session_context'])) {
    $session_context = json_decode($_POST['session_context'], true);
}

$conversation_id = $_POST['conversation_id'] ?? null;

function prepareFileParts($file, $user_question)
{
    $filePath = $file['tmp_name'];
    // Check if file exists to avoid warnings
    if (!file_exists($filePath)) {
        throw new Exception("File upload failed: Temporary file not found.");
    }

    $fileType = mime_content_type($filePath);
    $originalName = $file['name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    $allowed_types = [
        'txt' => 'text/plain',
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'webp' => 'image/webp',
    ];

    if (!in_array($extension, array_keys($allowed_types))) {
        // Let's be more generic in the error message now
        throw new Exception("Unsupported file type: {$extension}.");
    }

    // Double-check MIME type
    if (!in_array($fileType, $allowed_types)) {
        // Allow for some variation in MIME types reported by servers
        if ($extension !== 'docx' || $fileType !== 'application/zip') {
            throw new Exception("File content does not match its extension ({$extension} vs {$fileType}).");
        }
    }

    // Handle images
    if (strpos($fileType, 'image/') === 0) {
        if (!extension_loaded('gd')) {
            throw new Exception("The 'gd' PHP extension is required to process images but it is not enabled. Please enable it in your php.ini file.");
        }

        // Load and potentially resize the image to prevent "server has gone away" errors
        $srcImage = null;
        switch ($fileType) {
            case 'image/jpeg':
                $srcImage = imagecreatefromjpeg($filePath);
                break;
            case 'image/png':
                $srcImage = imagecreatefrompng($filePath);
                break;
            case 'image/gif':
                $srcImage = imagecreatefromgif($filePath);
                break;
            case 'image/bmp':
                $srcImage = imagecreatefrombmp($filePath);
                break;
            case 'image/webp':
                $srcImage = imagecreatefromwebp($filePath);
                break;
        }

        if (!$srcImage) {
            throw new Exception("Could not process the image file '{$originalName}'.");
        }

        // Get original dimensions
        $origWidth = imagesx($srcImage);
        $origHeight = imagesy($srcImage);

        // Resize to smaller dimensions to prevent DB packet errors
        // Most MySQL servers have max_allowed_packet around 4-16MB
        // After base64 encoding, size increases by ~33%, so we need to be conservative
        $maxWidth = 1024;  // Reduced from 1920 for better compression
        $maxHeight = 1024;

        // Always resize and compress to JPEG for consistency and smaller size
        // Calculate new dimensions maintaining aspect ratio
        $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
        $newWidth = (int) ($origWidth * $ratio);
        $newHeight = (int) ($origHeight * $ratio);

        // Create resized image
        $dstImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG/GIF by using white background
        if ($fileType === 'image/png' || $fileType === 'image/gif') {
            $white = imagecolorallocate($dstImage, 255, 255, 255);
            imagefilledrectangle($dstImage, 0, 0, $newWidth, $newHeight, $white);
        }

        imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        // Convert to JPEG with more aggressive compression
        ob_start();
        imagejpeg($dstImage, null, 75); // 75% quality - more aggressive compression
        $fileData = ob_get_clean();
        $fileType = 'image/jpeg';

        imagedestroy($srcImage);
        imagedestroy($dstImage);

        // Final safety check: if the image is still too large, reject it
        $maxSizeBytes = 3 * 1024 * 1024; // 3MB raw limit (becomes ~4MB after base64)
        if (strlen($fileData) > $maxSizeBytes) {
            throw new Exception("Image file is too large even after compression. Please use a smaller image.");
        }

        $base64Data = base64_encode($fileData);

        return [
            'inline_data' => ['mime_type' => $fileType, 'data' => $base64Data]
        ];
    }

    $text = '';
    switch ($extension) {
        case 'txt':
            $text = file_get_contents($filePath);
            break;
        case 'pdf':
            if (!class_exists('\Smalot\PdfParser\Parser')) {
                throw new Exception("PDF parsing library is not installed. Please run 'composer require smalot/pdfparser'.");
            }
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();
            break;
        case 'docx':
            if (!class_exists('\PhpOffice\PhpWord\IOFactory')) {
                throw new Exception("Word document parsing library is not installed. Please run 'composer require phpoffice/phpword'.");
            }
            if (!extension_loaded('zip')) {
                throw new Exception("The 'zip' PHP extension is required to read .docx files but it is not enabled. Please enable it in your php.ini file.");
            }
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
            $textExtractor = new \PhpOffice\PhpWord\Shared\Html();
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                        foreach ($element->getElements() as $textElement) {
                            if ($textElement instanceof \PhpOffice\PhpWord\Element\Text) {
                                $text .= $textElement->getText() . ' ';
                            }
                        }
                    }
                }
            }
            break;
        case 'pptx':
            if (!class_exists('\PhpOffice\PhpPresentation\IOFactory')) {
                throw new Exception("PowerPoint parsing library is not installed. Please run 'composer require phpoffice/phppresentation'.");
            }
            if (!extension_loaded('zip')) {
                throw new Exception("The 'zip' PHP extension is required to read .pptx files but it is not enabled. Please enable it in your php.ini file.");
            }
            // Increase memory limit for large PPTX files (some presentations are memory-intensive)
            $originalMemoryLimit = ini_get('memory_limit');
            ini_set('memory_limit', '1G');
            try {
                $phpPresentation = \PhpOffice\PhpPresentation\IOFactory::load($filePath);
            } finally {
                // Restore original memory limit
                ini_set('memory_limit', $originalMemoryLimit);
            }
            foreach ($phpPresentation->getAllSlides() as $slide) {
                foreach ($slide->getShapeCollection() as $shape) {
                    if ($shape instanceof \PhpOffice\PhpPresentation\Shape\RichText) {
                        $text .= $shape->getPlainText() . "\n\n";
                    }
                }
            }
            break;
    }

    if (empty($text)) {
        throw new Exception("Could not extract any text from the file '{$originalName}'. It might be empty, image-based, or corrupted.");
    }

    // Truncate to a reasonable length to avoid excessive API costs/limits
    $maxLength = 20000; // Approx 5000 tokens
    if (strlen($text) > $maxLength) {
        $text = substr($text, 0, $maxLength) . "\n\n... [File content truncated] ...\n\n";
    }

    $combined_text = "Context from uploaded file '{$originalName}':\n---\n{$text}\n---\n";
    return [
        'text' => $combined_text
    ];
}

function callGeminiAPI($payload, $apiKey) {
    // Model selection: Use gemini-3-flash-preview for improved reasoning with function calling
    $model = 'gemini-3-flash-preview'; 
    
    // Add function calling tools for image generation
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
    $max_retries = 5; // Increased retries
    $delay = 2; // Increased initial delay

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

            // If we hit a rate limit with the experimental model, try falling back to stable 2.5-flash
            if ($model === 'gemini-3-flash-preview' && $retries > 1) {
                $model = 'gemini-2.5-flash';
                $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;
                // Don't sleep, try immediately
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

        // Check if AI wants to generate an image (function call)
        if (isset($responseData['candidates'][0]['content']['parts'][0]['functionCall'])) {
            $functionCall = $responseData['candidates'][0]['content']['parts'][0]['functionCall'];
            $functionName = $functionCall['name'];

            if ($functionName === 'generate_image') {
                $functionArgs = $functionCall['args'];
                $imagePrompt = $functionArgs['prompt'];
                $aspectRatio = $functionArgs['aspectRatio'] ?? '1:1';

                error_log("Chat Service: AI requested image generation - " . substr($imagePrompt, 0, 100));

                try {
                    // Call the image microservice
                    $imageServiceUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
                        . "://{$_SERVER['HTTP_HOST']}/api/image.php";

                    $imagePayload = json_encode([
                        'prompt' => $imagePrompt,
                        'options' => [
                            'aspectRatio' => $aspectRatio
                        ]
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
                        // Image generated successfully
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
                        // Image service returned an error
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

        // No function call or normal response
        return $responseData;
    }
}

function generateImageWithImagen($prompt, $apiKey)
{
    // Use Imagen 4 Ultra endpoint (stable version)
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

/**
 * Generate a structured learning outline for a topic using AI.
 * 
 * @param string $topic The topic to create an outline for
 * @param string $sessionGoal The session goal (explore, test_prep, homework_help, practice)
 * @param string $educationLevel The student's education level
 * @param string $apiKey The Gemini API key
 * @return array|null The learning outline with milestones, or null on failure
 */
function generateLearningOutline($topic, $sessionGoal, $educationLevel, $apiKey)
{
    // Adjust complexity based on session goal
    $goalInstructions = [
        'explore' => 'Create a comprehensive exploration outline. Include foundational concepts, key theories, practical applications, and interesting connections to other fields.',
        'test_prep' => 'Create a focused study outline. Prioritize commonly tested concepts, formulas, and problem types. Include practice checkpoints.',
        'homework_help' => 'Create a problem-solving outline. Focus on understanding the problem, required concepts, solution steps, and verification.',
        'practice' => 'Create a skill-building outline. Start with simple examples and progressively increase difficulty. Include plenty of practice opportunities.'
    ];

    $goalInstruction = $goalInstructions[$sessionGoal] ?? $goalInstructions['explore'];

    $prompt = <<<EOT
You are a curriculum designer creating a learning outline for teaching "{$topic}" to a {$educationLevel} student.

{$goalInstruction}

Requirements:
1. Break down the topic into logical milestones (minimum 4, no maximum - use as many as needed for the topic's complexity)
2. Order milestones from foundational to advanced
3. Each milestone should be achievable in 2-5 minutes of discussion
4. Include practical application or synthesis as the final milestone
5. Make milestone titles clear and specific

Return ONLY a valid JSON object in this exact format:
{
    "topic": "{$topic}",
    "totalMilestones": <number>,
    "milestones": [
        {"id": 1, "title": "...", "description": "Brief description of what will be covered", "keyPoints": ["point1", "point2"]},
        ...
    ]
}
EOT;

    try {
        $payload = json_encode([
            "contents" => [
                ["parts" => [["text" => $prompt]]]
            ],
            "generationConfig" => [
                "responseMimeType" => "application/json",
                "temperature" => 0.3 // Lower temperature for more consistent structure
            ]
        ]);

        $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 15
        ]);

        $response = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_status !== 200) {
            error_log("Outline generation failed: HTTP $http_status");
            return null;
        }

        $data = json_decode($response, true);
        $jsonText = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (!$jsonText) {
            error_log("Outline generation: No text in response");
            return null;
        }

        $outline = json_decode($jsonText, true);

        if (!$outline || !isset($outline['milestones'])) {
            error_log("Outline generation: Invalid JSON structure");
            return null;
        }

        // Initialize completion status for each milestone
        foreach ($outline['milestones'] as &$milestone) {
            $milestone['completed'] = false;
            $milestone['coveredAt'] = null;
        }

        $outline['generatedAt'] = date('c');
        $outline['lastUpdated'] = date('c');

        return $outline;

    } catch (Exception $e) {
        error_log("Outline generation error: " . $e->getMessage());
        return null;
    }
}

/**
 * Analyze AI response to detect which milestones were covered.
 * 
 * @param string $aiResponse The AI's response text
 * @param array $milestones The current milestones array
 * @return array Updated milestones with completion status
 */
function detectMilestoneCompletion($aiResponse, $milestones)
{
    if (empty($milestones)) {
        return $milestones;
    }

    $lowerResponse = strtolower($aiResponse);

    foreach ($milestones as &$milestone) {
        if ($milestone['completed']) {
            continue; // Already completed
        }

        // Check if milestone title or key points are substantially covered
        $titleWords = preg_split('/\s+/', strtolower($milestone['title']));
        $matchCount = 0;

        foreach ($titleWords as $word) {
            if (strlen($word) > 3 && strpos($lowerResponse, $word) !== false) {
                $matchCount++;
            }
        }

        // If more than 50% of significant words from title appear, consider it covered
        $significantWords = count(array_filter($titleWords, fn($w) => strlen($w) > 3));
        if ($significantWords > 0 && ($matchCount / $significantWords) >= 0.5) {
            $milestone['completed'] = true;
            $milestone['coveredAt'] = date('c');
        }

        // Also check key points if available
        if (!$milestone['completed'] && isset($milestone['keyPoints'])) {
            $keyPointsMatched = 0;
            foreach ($milestone['keyPoints'] as $point) {
                $pointWords = preg_split('/\s+/', strtolower($point));
                foreach ($pointWords as $word) {
                    if (strlen($word) > 3 && strpos($lowerResponse, $word) !== false) {
                        $keyPointsMatched++;
                        break;
                    }
                }
            }

            // If most key points are touched, mark as completed
            if (
                count($milestone['keyPoints']) > 0 &&
                ($keyPointsMatched / count($milestone['keyPoints'])) >= 0.6
            ) {
                $milestone['completed'] = true;
                $milestone['coveredAt'] = date('c');
            }
        }
    }

    return $milestones;
}

/**
 * Analyze user message for comprehension signals.
 * 
 * @param string $userMessage The user's message
 * @return float Comprehension delta (-0.15 to +0.15)
 */
function analyzeComprehension($userMessage)
{
    $lowerMessage = strtolower($userMessage);

    // Positive signals
    $positivePatterns = [
        '/\bi (get|got|understand|see)\b/' => 0.10,
        '/\bmakes sense\b/' => 0.12,
        '/\bah+\b.*\bok\b/' => 0.08,
        '/\bthat\'s? (clear|helpful)\b/' => 0.10,
        '/\bthank(s| you)\b/' => 0.05,
        '/\bnow i (know|understand)\b/' => 0.15,
        '/\bperfect\b/' => 0.08,
        '/\bi can (see|follow)\b/' => 0.10
    ];

    // Negative signals
    $negativePatterns = [
        '/\bi (don\'t|do not) (get|understand)\b/' => -0.12,
        '/\bconfus(ed|ing)\b/' => -0.10,
        '/\bwhat (do you mean|does that mean)\b/' => -0.05,
        '/\bcan you explain (again|more)\b/' => -0.08,
        '/\bi\'m (lost|stuck)\b/' => -0.10,
        '/\bstill (don\'t|unclear)\b/' => -0.12,
        '/\bhuh\??\b/' => -0.05,
        '/\bwait,? what\b/' => -0.08
    ];

    $delta = 0.0;

    foreach ($positivePatterns as $pattern => $score) {
        if (preg_match($pattern, $lowerMessage)) {
            $delta += $score;
        }
    }

    foreach ($negativePatterns as $pattern => $score) {
        if (preg_match($pattern, $lowerMessage)) {
            $delta += $score; // score is already negative
        }
    }

    // Clamp to reasonable range
    return max(-0.15, min(0.15, $delta));
}

/**
 * Calculate overall progress from milestones and comprehension.
 * 
 * @param array $contextData The session context data
 * @return int Progress percentage (0-100)
 */
function calculateHybridProgress($contextData)
{
    $milestones = $contextData['outline']['milestones'] ?? [];
    $comprehensionScore = $contextData['comprehensionScore'] ?? 0.5;
    $messageCount = $contextData['messageCount'] ?? 0;

    // If no milestones, use message-based progress
    if (empty($milestones)) {
        return min(100, intval(($messageCount / 10) * 100));
    }

    // Calculate milestone completion
    $completedCount = count(array_filter($milestones, fn($m) => $m['completed'] ?? false));
    $totalCount = count($milestones);
    $milestoneProgress = ($completedCount / $totalCount) * 100;

    // Calculate engagement score (capped at 1.0)
    $engagementScore = min(1.0, $messageCount / 10);

    // Weighted combination: 70% milestones, 20% comprehension, 10% engagement
    $hybridProgress =
        ($milestoneProgress * 0.70) +
        ($comprehensionScore * 100 * 0.20) +
        ($engagementScore * 100 * 0.10);

    return min(100, max(0, intval($hybridProgress)));
}

try {
    // Initialize variables that might be used in conditional paths to avoid warnings
    $a = null;
    $x = null;

    $pdo = getDbConnection();

    // --- Secure API Key Handling ---
    // Try to load config-sql.ini first, then fall back to config.ini
    $config = null;
    $configFiles = ['config-sql.ini', 'config.ini'];

    foreach ($configFiles as $configFile) {
        if (file_exists($configFile)) {
            $config = parse_ini_file($configFile);
            if ($config !== false) {
                break; // Successfully loaded, exit loop
            }
        }
    }

    if ($config === false || !isset($config['GEMINI_API_KEY'])) {
        throw new Exception('API key configuration is missing or unreadable in config-sql.ini or config.ini.');
    }
    define('GEMINI_API_KEY', $config['GEMINI_API_KEY']);

    if (GEMINI_API_KEY === 'YOUR_ACTUAL_GEMINI_API_KEY_HERE' || empty(GEMINI_API_KEY)) {
        throw new Exception('Gemini API Key is not configured in config.ini.');
    }

    $user_message_parts = [];

    // Check for file upload (Multiple files supported)
    file_put_contents('debug_log.txt', "--- New Request ---\n", FILE_APPEND);
    file_put_contents('debug_log.txt', "FILES: " . print_r($_FILES, true) . "\n", FILE_APPEND);

    if (isset($_FILES['attachment'])) {
        // Re-organize the $_FILES array if multiple files are uploaded
        $files = [];
        if (is_array($_FILES['attachment']['name'])) {
            $count = count($_FILES['attachment']['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['attachment']['error'][$i] === UPLOAD_ERR_OK) {
                    $files[] = [
                        'name' => $_FILES['attachment']['name'][$i],
                        'type' => $_FILES['attachment']['type'][$i],
                        'tmp_name' => $_FILES['attachment']['tmp_name'][$i],
                        'error' => $_FILES['attachment']['error'][$i],
                        'size' => $_FILES['attachment']['size'][$i]
                    ];
                }
            }
        } else {
            // Single file fallback (shouldn't happen with multiple attribute but good for safety)
            if ($_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $files[] = $_FILES['attachment'];
            }
        }

        file_put_contents('debug_log.txt', "Processed Files Array: " . print_r($files, true) . "\n", FILE_APPEND);

        // Process each file
        foreach ($files as $file) {
            try {
                $part = prepareFileParts($file, $question);
                $user_message_parts[] = $part;
            } catch (Exception $e) {
                file_put_contents('debug_log.txt', "Error processing file: " . $e->getMessage() . "\n", FILE_APPEND);
                // Instead of failing the whole request, let's add a system message to the parts
                // so the AI (and the user) knows something went wrong with this specific file.
                $errorMsg = "System Error: Could not process file '{$file['name']}'. Reason: " . $e->getMessage();
                $user_message_parts[] = ['text' => $errorMsg];
            }
        }
    }

    // Add the text question as the final part
    if (!empty($question)) {
        $user_message_parts[] = ['text' => $question];
    }

    file_put_contents('debug_log.txt', "User Message Parts: " . print_r($user_message_parts, true) . "\n", FILE_APPEND);

    // Basic validation
    $is_empty = empty($user_message_parts) || (count($user_message_parts) === 1 && empty(trim($user_message_parts[0]['text'])));
    if ($is_empty) {
        echo json_encode(['success' => false, 'error' => 'Question is missing or file content is empty.']);
        exit;
    }

    // If no conversation ID, create a new one in the database
    if (!$conversation_id) {
        $stmt = $pdo->prepare("INSERT INTO conversations (user_id, title) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], 'New Chat on ' . date('Y-m-d')]);
        $conversation_id = $pdo->lastInsertId();
    } else {
        // Verify the user owns the conversation they are trying to post to.
        $stmt = $pdo->prepare("SELECT id FROM conversations WHERE id = ? AND user_id = ?");
        $stmt->execute([$conversation_id, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            throw new Exception("Conversation access denied.");
        }
    }

    // Save user message to the database
    $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, role, content) VALUES (?, 'user', ?)");
    $stmt->execute([$conversation_id, json_encode($user_message_parts)]);

    // Fetch the conversation history from the database to send to the AI
    $stmt = $pdo->prepare("SELECT role, content FROM messages WHERE conversation_id = ? ORDER BY created_at ASC");
    $stmt->execute([$conversation_id]);
    $db_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $chat_history = [];
    foreach ($db_messages as $message) {
        $chat_history[] = [
            'role' => $message['role'],
            'parts' => json_decode($message['content'], true)
        ];
    }

    // Add the new user message (with its parts) to the history
    // If possible, respond immediately to the client and continue processing
    // in the background to avoid client-side timeouts for slow AI responses.
    $backgrounded = false;
    if (function_exists('fastcgi_finish_request')) {
        $backgrounded = true;
        $ack = ['success' => true, 'processing' => true, 'conversation_id' => $conversation_id];
        header('Content-Type: application/json');
        echo json_encode($ack);
        // flush all response data to the client and finish the request
        fastcgi_finish_request();
        // script continues to run after this point to call the AI and save results
    }

    // Fetch user personalization data for AI context
    $stmt = $pdo->prepare("SELECT country, primary_language, education_level, field_of_study FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_profile = $stmt->fetch(PDO::FETCH_ASSOC);

    // Build personalization context for the AI
    $personalization_context = "";
    
    // Only add profile context if user has profile data
    $hasProfile = $user_profile && ($user_profile['country'] || $user_profile['education_level'] || $user_profile['field_of_study'] || $user_profile['primary_language']);
    
    if ($hasProfile) {
        // CRITICAL: Add instruction to use personalization subtly
        $personalization_context .= <<<EOT
**⚠️ CRITICAL INSTRUCTION - READ CAREFULLY:**
The following profile information is for YOUR INTERNAL USE ONLY to calibrate your responses. 

**NEVER explicitly mention or reference this information in your responses.** Examples of what NOT to do:
- ❌ "In the world of Information Technology..." or "As someone in IT..."
- ❌ "Since you're studying [field]..."
- ❌ "As a university student..."
- ❌ "Given your background in..."

**INSTEAD:** Just answer the question naturally. Use appropriate vocabulary and examples that match their level, but don't call attention to WHY you're doing it. The personalization should be invisible to the user.

Profile context (USE IMPLICITLY, DO NOT MENTION):

EOT;
        
        if ($user_profile['country']) {
            $country = $user_profile['country'];
            $personalization_context .= <<<EOT
- **Region: {$country}**
  → Use real-world examples, scenarios, and analogies from {$country}
  → Reference local landmarks, companies, sports, foods, or cultural elements when explaining concepts
  → Use the local currency, measurement systems, and conventions familiar to someone from {$country}
  → Example: If explaining economics, use local businesses or industries. If explaining biology, reference local flora/fauna.

EOT;
        }
        if ($user_profile['education_level']) {
            $personalization_context .= "- Academic level: {$user_profile['education_level']}\n";
        }
        if ($user_profile['field_of_study']) {
            $personalization_context .= "- Field: {$user_profile['field_of_study']}\n";
        }
        if ($user_profile['primary_language'] && $user_profile['primary_language'] !== 'English') {
            $personalization_context .= "- Primary language: {$user_profile['primary_language']}\n";
        }
    }

    // Session goal-based teaching instructions
    $session_goal_instructions = "";
    if ($session_goal) {
        switch ($session_goal) {
            case 'homework_help':
                $session_goal_instructions = <<<EOT

### Current Session Goal: Homework Help
The learner is seeking help with homework. Prioritize:
- **Step-by-step guidance** rather than just giving answers
- **Teaching the underlying concepts** while helping solve problems
- **Checking understanding** at each step before moving on
- **Explaining your reasoning** so they can apply it to similar problems
- Ask "Does this make sense?" or "Should I explain this part further?"
EOT;
                break;
            case 'test_prep':
                $session_goal_instructions = <<<EOT

### Current Session Goal: Test Preparation
The learner is preparing for an exam. Prioritize:
- **Practice problems** at increasing difficulty levels
- **Identifying weak areas** and focusing on them
- **Quick review** of key concepts and formulas
- **Exam strategies** (time management, common mistakes to avoid)
- **Confidence building** while being honest about areas needing work
EOT;
                break;
            case 'explore':
                $session_goal_instructions = <<<EOT

### Current Session Goal: Topic Exploration
The learner wants to explore and learn something new. Prioritize:
- **Curiosity-driven learning** - follow their interests
- **Making connections** to things they already know
- **Interesting examples and real-world applications**
- **Open-ended questions** to spark deeper thinking
- **No pressure** - this is about discovery and enjoyment
EOT;
                break;
            case 'practice':
                $session_goal_instructions = <<<EOT

### Current Session Goal: Practice Session
The learner wants to practice and reinforce skills. Prioritize:
- **Generating practice problems** at appropriate difficulty
- **Immediate feedback** on their attempts
- **Identifying patterns** in their errors
- **Building fluency** through repetition with variety
- **Celebrating progress** and correct answers
EOT;
                break;
        }

        // Add session context if available
        if ($session_context) {
            $context_details = [];
            if (!empty($session_context['subject'])) {
                $context_details[] = "Subject: **{$session_context['subject']}**";
            }
            if (!empty($session_context['topic'])) {
                $context_details[] = "Topic: **{$session_context['topic']}**";
            }
            if (!empty($session_context['difficulty'])) {
                $context_details[] = "Difficulty level: **{$session_context['difficulty']}**";
            }
            if (!empty($session_context['testDate'])) {
                $context_details[] = "Test date: **{$session_context['testDate']}**";
            }
            if (!empty($context_details)) {
                $session_goal_instructions .= "\n\n**Session Context:**\n- " . implode("\n- ", $context_details);
            }
        }
    }

    // Append session goal instructions to personalization context
    $personalization_context .= $session_goal_instructions;

    // --- RAG Knowledge Retrieval ---
    // Retrieve relevant knowledge from the knowledge base
    $knowledge_context = "";
    try {
        $knowledgeService = getKnowledgeService();
        // Get the user's question text for retrieval
        $userQuestionText = '';
        foreach ($user_message_parts as $part) {
            if (isset($part['text'])) {
                $userQuestionText .= $part['text'] . ' ';
            }
        }
        $userQuestionText = trim($userQuestionText);
        
        if (strlen($userQuestionText) > 10) {
            $relevantKnowledge = $knowledgeService->retrieveRelevant($userQuestionText, 3);
            
            if (!empty($relevantKnowledge)) {
                $knowledge_context = "\n\n## Retrieved Knowledge Context\nThe following information was retrieved from our knowledge base and may be relevant:\n\n";
                foreach ($relevantKnowledge as $i => $chunk) {
                    $title = $chunk['title'] ?? 'Source';
                    $content = substr($chunk['content'], 0, 800); // Limit chunk size
                    $knowledge_context .= "**Source " . ($i + 1) . ":** {$title}\n{$content}\n\n";
                }
                $knowledge_context .= "Use this information to enhance your response if relevant, but don't mention that you retrieved it from a knowledge base.\n";
            }
        }
    } catch (Exception $e) {
        error_log("Knowledge retrieval error: " . $e->getMessage());
    }
    
    // Append knowledge context to personalization
    $personalization_context .= $knowledge_context;

    // --- Learning Strategies Context ---
    // Add evidence-based learning strategies (Justin Sung methodology)
    try {
        $strategiesService = getLearningStrategiesService();
        
        // Get relevant strategies based on user's question
        $strategyContext = $strategiesService->generateStrategyContext($userQuestionText);
        $personalization_context .= $strategyContext;
        
    } catch (Exception $e) {
        error_log("Learning strategies error: " . $e->getMessage());
    }

    // System prompt
    $system_prompt = <<<PROMPT
# Adaptive AI Tutor System Prompt

You are an expert AI tutor designed to facilitate deep learning across any subject. Your goal is not just to provide answers, but to guide learners toward understanding through adaptive, personalized instruction.

## Core Philosophy

- **Learning > Answers**: Prioritize understanding over quick solutions
- **Adaptive**: Continuously adjust to the learner's needs
- **Socratic**: Use questions to guide discovery when appropriate
- **Encouraging**: Build confidence and maintain engagement
- **Metacognitive**: Help learners understand their own thinking

---

## PHASE 1: ASSESS THE LEARNER

Before responding, analyze the learner's message. The user has indicated a desired learning goal based on Bloom's Taxonomy: **{$learningLevel}**. Use this as a starting point, but adapt based on your analysis of their actual message.

### Learner Profile
{$personalization_context}

### A. Knowledge State Indicators

**General Proficiency**:
- **Novice**: Vague questions, missing vocabulary, fundamental confusion
- **Developing**: Partial understanding, specific confusion points, some correct terminology
- **Proficient**: Detailed questions, mostly correct understanding, seeking nuance
- **Expert**: Deep questions, looking for edge cases or advanced applications

**Bloom's Taxonomy Level** (Cognitive Dimension):
Identify which level(s) the learner is operating at or needs to reach:

1.  **Remember**: Recall facts, terms, basic concepts. Keywords: "what is", "define", "list".
2.  **Understand**: Explain ideas, interpret meaning, summarize. Keywords: "explain", "describe", "why".
3.  **Apply**: Use information in new situations, solve problems. Keywords: "calculate", "solve", "what happens if".
4.  **Analyze**: Draw connections, distinguish between parts. Keywords: "compare", "contrast", "examine".
5.  **Evaluate**: Justify decisions, make judgments, critique. Keywords: "assess", "judge", "which is better".
6.  **Create**: Generate new ideas, design solutions. Keywords: "design", "create", "propose".

**Target Bloom's Level**: Where should you guide them?
- The user's stated goal is **{$learningLevel}**.
- If their question seems below this level, help them build up to it.
- If their question is already at or above this level, engage them there.
- Build foundations before advancing. Don't jump more than 1-2 levels in a single interaction.

### B. Interaction Intent
- **Seeking explanation**: "What is...", "Can you explain..."
- **Seeking confirmation**: "Is this correct?"
- **Stuck on problem**: "I'm stuck on...", shows work
- **Seeking challenge**: "What's a harder problem?"
- **Exploring curiosity**: "Why...", "What if..."

### C. Emotional/Motivational State
- **Frustrated**: Negative language, giving up signals
- **Confused**: Contradictory statements, uncertainty
- **Confident**: Assertive statements, ready for more
- **Curious**: Exploratory questions, enthusiasm

### D. Error Pattern Recognition
- **Conceptual**: Fundamental misunderstanding
- **Procedural**: Knows concept but wrong steps
- **Careless**: Simple mistake, likely understands

---

## PHASE 2: SELECT STRATEGY

Based on assessment, choose your pedagogical approach:

| Learner State | Primary Strategy |
|---|---|
| Novice seeking explanation | **Direct Teaching** with examples |
| Developing, specific confusion | **Socratic Questioning** |
| Proficient, seeking nuance | **Elaborative Discussion** |
| Stuck on problem | **Scaffolded Guidance** |
| Made an error | **Diagnostic Questions** |
| Showing mastery | **Challenge Extension** |
| Frustrated | **Encouraging Reset** |
| Curious exploration | **Guided Discovery** |

### Teaching Strategies Defined

1.  **Direct Teaching**: Clear, structured explanation with examples and analogies. Check for understanding.
2.  **Socratic Questioning**: Guide through strategic questions to help them discover answers.
3.  **Scaffolded Guidance**: Start with minimal hints, gradually increasing support.
4.  **Diagnostic Questions**: Ask questions that reveal thinking ("How did you get that?"). Guide to self-correction.
5.  **Elaborative Discussion**: Explore implications and connections ("How does this relate to...?" ).
6.  **Challenge Extension**: Pose harder problems or introduce advanced applications.

---

## DISCIPLINE-SPECIFIC ENHANCEMENTS

When you detect the subject area, apply these additional strategies on top of your primary strategy:

### IF MATHEMATICS:
- Always explain WHY procedures work, not just HOW.
- Use multiple representations (numerical, algebraic, graphical, verbal).
- When students make errors, ask diagnostic questions before correcting.
- Guide through: Understand → Plan → Execute → Check.
- Never let them just memorize formulas without understanding.
- **CRITICAL: Use LaTeX for ALL mathematical notation and formulas.** For inline math, wrap with `$...$` or `\(...\)`. For display/block equations, use `$$...$$` or `\[...\]`.
- **Examples of CORRECT format:**
  - Pythagorean theorem (inline): The formula is $a^2 + b^2 = c^2$ where...
  - Pythagorean theorem (display): $$a^2 + b^2 = c^2$$
  - Square root: $\sqrt{25} = 5$
  - Exponents: $2^3 = 8$ and $x^2 - 4 = 0$
  - Fractions: $\frac{1}{2}$ for inline, or $$\frac{a^2 + b^2}{c}$$ for display
- **NEVER write:** a2+b2=c2 or 3^2 as plain text. **ALWAYS wrap in LaTeX.**
- When showing calculations step-by-step, wrap each formula in LaTeX delimiters.

### IF SCIENCE (Physics, Chemistry, Biology):
- Start with observable phenomena before abstract explanations.
- Connect macroscopic (what we see) to microscopic (atoms/cells/particles).
- Actively confront common misconceptions.
- Build mental models through prediction and testing.
- Always ask "What's happening at the [molecular/atomic/cellular] level?"

### IF BIOLOGY specifically:
- Emphasize structure-function relationships ("Why does it exist? What's its purpose?").
- Walk through processes step-by-step with causation ("which causes... leading to...").
- Don't just teach vocabulary - teach the concepts, terminology follows.
- Connect to evolution ("What survival advantage does this provide?").

### IF HUMANITIES (History, Literature, Philosophy):
- Multiple valid interpretations exist, but all need textual evidence.
- Always ask "What evidence from the text/source supports that?".
- Emphasize historical/cultural context.
- Build arguments: Claim → Evidence → Reasoning → Counterargument.
- Ask "What would someone from that time period have thought?".

### IF PROGRAMMING:
- Focus on computational thinking first, syntax second.
- Normalize errors: "Errors are feedback, not failure".
- Guide through: Understand → Examples → Decompose → Pseudocode → Code.
- When debugging: "What did you expect? What actually happened? Where's the gap?".
- Ask them to read/trace code before writing it.

---

## PHASE 3: CRAFT YOUR RESPONSE

### Response Structure Template

```
[Optional: Brief acknowledgment of their effort/emotional state]
[Main instructional content - tailored to strategy]
[Engagement element: question, challenge, or check for understanding]
[Optional: Encouragement or next steps]
```

### Response Guidelines

- **Tone**: Patient for novices, supportive for developing, collegial for proficient, reassuring for frustrated.
- **Language**: Match their vocabulary. Introduce technical terms with definitions. Use analogies.
- **Scaffolding Levels** (for problem-solving):
    1.  **Metacognitive Prompt**: "What have you tried so far?"
    2.  **Directional Hint**: "Think about how [concept] applies here."
    3.  **Strategic Hint**: "Try breaking this into smaller steps."
    4.  **Partial Solution**: "Let's start with... can you continue?"
    5.  **Worked Example** (Last resort): Show a full solution, then ask them to try a similar problem.

---

## PHASE 4: ADAPTIVE FOLLOW-UP

- **If They Understand**: Acknowledge success, reinforce, and extend ("Now try this variation...").
- **If Still Confused**: Don't repeat. Try a different approach (analogy, simpler language). Ask diagnostic questions.
- **If They Made Progress**: Celebrate progress and provide a targeted hint for the next step.
- **If They're Frustrated**: Normalize the struggle, reframe what they DO understand, and simplify to rebuild confidence.

---

## SPECIAL SCENARIOS

### When They Ask for Direct Answer
**Don't immediately comply**. Instead:
1.  "I want to help you learn this, not just give you the answer. Let me guide you."
2.  "What do you understand so far?"
3.  If truly stuck after scaffolding, provide the answer with a thorough explanation and follow up with a similar problem for them to solve.

### When They Share Wrong Work/Thinking
**Never say "That's wrong" directly**. Instead:
1.  "I can see your thinking here..."
2.  Ask diagnostically: "Can you walk me through why you chose...?"
3.  Guide them to see the error themselves.

### When They Ask Homework Questions
1.  Never solve homework directly.
2.  State: "I'll help you learn to solve it yourself."
3.  Use the scaffolding approach to teach the method, not the specific answer.

---

## QUALITY CHECKS

Before sending your response, verify:
- [ ] Did I assess their knowledge state, using their stated goal of **{$learningLevel}** as a guide?
- [ ] Did I choose an appropriate strategy?
- [ ] Am I facilitating learning, not just giving answers?
- [ ] Is my language and tone appropriate?
- [ ] Did I include an engagement element (a question or challenge)?
- [ ] Have I avoided robbing them of the "aha!" moment?

Remember: You are a **learning facilitator**. Your success is measured by how deeply you help learners understand.
PROMPT;
    // Construct the prompt for the AI
    $payload = json_encode([
        "contents" => $chat_history,
        "system_instruction" => [
            "role" => "system",
            "parts" => [["text" => $system_prompt]]
        ],
        "generationConfig" => [
            "maxOutputTokens" => 8192,  // Prevent truncation of long responses
            "temperature" => 0.7,
            "topP" => 0.95,
            "topK" => 40
        ]
    ]);

    $responseData = callGeminiAPI($payload, GEMINI_API_KEY);

    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        $answer = $responseData['candidates'][0]['content']['parts'][0]['text'];

        // Save AI response to the database
        $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, role, content) VALUES (?, 'model', ?)");
        $stmt->execute([$conversation_id, json_encode([['text' => $answer]])]);

        // --- HYBRID PROGRESS TRACKING ---
        // Fetch and update context_data for this conversation
        $stmt = $pdo->prepare("SELECT context_data, session_goal FROM conversations WHERE id = ?");
        $stmt->execute([$conversation_id]);
        $convoData = $stmt->fetch(PDO::FETCH_ASSOC);
        $contextData = json_decode($convoData['context_data'] ?? '{}', true) ?: [];
        $currentSessionGoal = $convoData['session_goal'] ?? $session_goal;

        // Initialize message count if not set
        $contextData['messageCount'] = ($contextData['messageCount'] ?? 0) + 1;

        // Initialize comprehension score if not set
        if (!isset($contextData['comprehensionScore'])) {
            $contextData['comprehensionScore'] = 0.5; // Start neutral
        }

        // Analyze user message for comprehension signals
        $comprehensionDelta = analyzeComprehension($question);
        $contextData['comprehensionScore'] = max(0, min(1, $contextData['comprehensionScore'] + $comprehensionDelta));

        // Generate learning outline if topic detected and no outline exists
        if (!isset($contextData['outline']) && !empty($question)) {
            // Extract topic from user's first substantive message or session context
            $detectedTopic = $session_context['topic'] ?? null;

            // If no explicit topic, try to extract from the question
            if (!$detectedTopic && strlen($question) > 10) {
                // Use the question itself as the topic for outline generation
                // This works especially well for "I want to learn about X" type messages
                if (preg_match('/(?:learn|teach me|explain|understand|study|help with|about)\s+(.{10,100})(?:\?|$|\.)/i', $question, $matches)) {
                    $detectedTopic = trim($matches[1]);
                } elseif ($contextData['messageCount'] <= 2) {
                    // For early messages, use the full question as topic hint
                    $detectedTopic = substr($question, 0, 100);
                }
            }

            // Generate outline if we have a topic and a session goal
            if ($detectedTopic && $currentSessionGoal) {
                $educationLevel = $user_profile['education_level'] ?? 'High School';
                $outline = generateLearningOutline($detectedTopic, $currentSessionGoal, $educationLevel, GEMINI_API_KEY);

                if ($outline) {
                    $contextData['outline'] = $outline;
                    $contextData['topic'] = $detectedTopic;
                    error_log("Generated learning outline for: $detectedTopic with " . count($outline['milestones']) . " milestones");
                }
            }
        }

        // If we have milestones, detect which were covered in this response
        if (isset($contextData['outline']['milestones'])) {
            $contextData['outline']['milestones'] = detectMilestoneCompletion(
                $answer,
                $contextData['outline']['milestones']
            );
            $contextData['outline']['lastUpdated'] = date('c');

            // Count newly completed milestones for notification
            $completedMilestones = array_filter(
                $contextData['outline']['milestones'],
                fn($m) => $m['completed'] ?? false
            );
            $contextData['milestonesCompleted'] = count($completedMilestones);
            $contextData['milestonesTotal'] = count($contextData['outline']['milestones']);
        }

        // Calculate hybrid progress
        $hybridProgress = calculateHybridProgress($contextData);
        $contextData['calculatedProgress'] = $hybridProgress;

        // Save updated context and progress to database
        $stmt = $pdo->prepare("UPDATE conversations SET context_data = ?, progress = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([json_encode($contextData), $hybridProgress, $conversation_id]);

        // Also update session_goal if provided and not already set
        if ($session_goal && !$convoData['session_goal']) {
            $stmt = $pdo->prepare("UPDATE conversations SET session_goal = ? WHERE id = ?");
            $stmt->execute([$session_goal, $conversation_id]);
        }
        // --- END HYBRID PROGRESS TRACKING ---

        // For new chats, generate an AI title
        $generated_title = null;
        if (count($chat_history) === 1) {
            // This is a new chat - generate a title using AI
            try {
                $title_prompt = "Based on this user question: \"$question\"\n\nGenerate a very concise, descriptive title for this conversation in 3-5 words maximum. The title should capture the main topic or question. Respond with ONLY the title, nothing else.";

                $title_payload = json_encode([
                    "contents" => [
                        [
                            "role" => "user",
                            "parts" => [["text" => $title_prompt]]
                        ]
                    ]
                ]);

                $title_response = callGeminiAPI($title_payload, GEMINI_API_KEY);

                if (isset($title_response['candidates'][0]['content']['parts'][0]['text'])) {
                    $generated_title = trim($title_response['candidates'][0]['content']['parts'][0]['text']);

                    // Limit to 60 characters max and remove any quotes
                    $generated_title = str_replace(['"', "'"], '', $generated_title);
                    if (strlen($generated_title) > 60) {
                        $generated_title = substr($generated_title, 0, 57) . '...';
                    }

                    // Update the conversation title
                    $stmt = $pdo->prepare("UPDATE conversations SET title = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$generated_title, $conversation_id]);
                }
            } catch (Exception $e) {
                // If title generation fails, use a fallback
                error_log("Title generation failed: " . $e->getMessage());
                $generated_title = "New Chat";
            }
        }

        // --- RAG: Detect and Process Resource Mentions ---
        // Detect books, papers, articles mentioned in AI response for knowledge acquisition
        try {
            $resourcePatterns = [
                // "book X by Author" or "X by Author (book)"
                '/(?:book|textbook|text)\s+["\']?([^"\']+?)["\']?\s+by\s+([A-Z][a-zA-Z]+(?:\s+[A-Z][a-zA-Z]+)?)/i',
                // "Author's book X"
                '/([A-Z][a-zA-Z]+(?:\s+[A-Z][a-zA-Z]+)?)[\'s]+\s+(?:book|textbook)\s+["\']?([^"\']+)["\']?/i',
                // Paper/article references
                '/(?:paper|article|research)\s+(?:titled\s+)?["\']([^"\']+)["\']/i',
                // arXiv links
                '/arxiv\.org\/abs\/(\d+\.\d+)/i',
            ];
            
            $detectedResources = [];
            foreach ($resourcePatterns as $pattern) {
                if (preg_match_all($pattern, $answer, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        if (count($match) >= 2) {
                            $resourceName = $match[1];
                            $author = $match[2] ?? null;
                            $detectedResources[] = ['name' => $resourceName, 'author' => $author];
                        }
                    }
                }
            }
            
            // Process up to 2 resources asynchronously (to not slow down response)
            if (!empty($detectedResources)) {
                $knowledgeService = getKnowledgeService();
                $processCount = 0;
                foreach (array_slice($detectedResources, 0, 2) as $resource) {
                    // Don't block - just log for now. In production, queue this.
                    error_log("RAG: Detected resource - " . $resource['name'] . ($resource['author'] ? " by " . $resource['author'] : ""));
                    
                    // Process in background (PHP doesn't have true async, but we can spawn a request)
                    // For now, process synchronously but quickly (just search, don't extract)
                    if ($processCount < 1) { // Limit to 1 to avoid slowing down response
                        $knowledgeService->processResourceMention($resource['name'], $resource['author']);
                        $processCount++;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("RAG resource detection error: " . $e->getMessage());
        }

        $formattedAnswer = formatResponse($answer);
        $response_payload = [
            'success' => true,
            'answer' => $formattedAnswer,
            'conversation_id' => $conversation_id
        ];
        if ($generated_title) {
            $response_payload['generated_title'] = $generated_title;
        }

        // Include milestone progress in response for frontend
        if (isset($contextData['outline'])) {
            $completedMilestones = array_filter(
                $contextData['outline']['milestones'] ?? [],
                fn($m) => $m['completed'] ?? false
            );
            $response_payload['progress'] = [
                'percentage' => $hybridProgress,
                'milestonesCompleted' => count($completedMilestones),
                'milestonesTotal' => count($contextData['outline']['milestones'] ?? []),
                'comprehensionScore' => round($contextData['comprehensionScore'] ?? 0.5, 2),
                'topic' => $contextData['topic'] ?? null,
                // Include recently completed milestone titles for notification
                'recentlyCompleted' => array_values(array_filter(
                    $contextData['outline']['milestones'] ?? [],
                    fn($m) => ($m['completed'] ?? false) &&
                    isset($m['coveredAt']) &&
                    (strtotime($m['coveredAt']) > strtotime('-1 minute'))
                ))
            ];
        }
        if (empty($backgrounded)) {
            echo json_encode($response_payload);
            exit();
        } else {
            // We already sent an acknowledgement to the client and finished the request.
            // Background worker (this continuing PHP process) will finish saving the model
            // response above. End the script quietly.
            return;
        }
    } else {
        error_log("Gemini API: Unexpected response structure - " . print_r($responseData, true));
        throw new Exception('No content generated or unexpected response structure from AI.');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server Error: ' . $e->getMessage()]);
}
