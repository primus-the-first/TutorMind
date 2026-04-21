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

// Catch fatal errors (E_ERROR, E_PARSE, etc.) and return JSON instead of empty 500
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        http_response_code(500);
        $msg = basename($error['file']) . ':' . $error['line'] . ' — ' . $error['message'];
        error_log('[server_mysql fatal] ' . $msg);
        echo json_encode(['success' => false, 'error' => 'Server error. Check PHP error log.', '_debug' => $msg]);
    }
});

// --- PERFORMANCE: Debug Mode ---
// Set to false in production to disable debug logging (saves disk I/O)
define('DEBUG_MODE', false);

require_once 'check_auth.php'; // Secure all API endpoints
require_once __DIR__ . '/api/services/document_service.php';
require_once __DIR__ . '/api/services/ai_service.php';

// --- CHAT RATE LIMITING ---
function checkChatRateLimit($pdo, $user_id) {
    $window = 60;        // 60-second window
    $max_requests = 15;  // Max 15 messages per minute per user
    $now = time();

    try {
        // Start transaction for atomic read-modify-write with locking
        $pdo->beginTransaction();

        // 1. Clean up old entries first
        $pdo->prepare("DELETE FROM chat_rate_limits WHERE window_start < (UNIX_TIMESTAMP() - ?)")
            ->execute([$window * 2]);

        // 2. Get and LOCK the current record for this user
        $stmt = $pdo->prepare("
            SELECT request_count, window_start 
            FROM chat_rate_limits 
            WHERE user_id = ?
            FOR UPDATE
        ");
        $stmt->execute([$user_id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            // First request — create record
            $pdo->prepare("
                INSERT INTO chat_rate_limits (user_id, request_count, window_start) 
                VALUES (?, 1, ?)
            ")->execute([$user_id, $now]);
            $pdo->commit();
            return true;
        }

        if (($now - $record['window_start']) > $window) {
            // Window expired — reset
            $pdo->prepare("
                UPDATE chat_rate_limits 
                SET request_count = 1, window_start = ? 
                WHERE user_id = ?
            ")->execute([$now, $user_id]);
            $pdo->commit();
            return true;
        }

        if ($record['request_count'] >= $max_requests) {
            // Limit exceeded
            $pdo->rollBack();
            return false;
        }

        // Increment count
        $pdo->prepare("
            UPDATE chat_rate_limits 
            SET request_count = request_count + 1 
            WHERE user_id = ?
        ")->execute([$user_id]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Rate limit check failed: " . $e->getMessage());
        return true; // Fail-open approach: allow request if limit logic fails
    }
}

// --- ALWAYS require the autoloader first ---
require_once 'db_mysql.php';
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
require_once 'api/knowledge.php';
require_once 'api/learning_strategies.php';
require_once __DIR__ . '/api/services/response_formatter.php';
require_once __DIR__ . '/api/services/comprehension_service.php';
require_once __DIR__ . '/api/services/tutor_service.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? null; // Use $_REQUEST to handle GET and POST actions

// Add a special case for logout to be handled by auth.php
if ($action === 'logout') {
    session_unset();
    session_destroy();
    header('Location: login');
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
                // Also fetch content_html cache column for faster rendering
                $stmt = $pdo->prepare("SELECT id, role, content, content_html, is_edited FROM messages WHERE conversation_id = ? ORDER BY created_at DESC LIMIT 15");
                $stmt->execute([$convo_id]);
                $messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC)); // Reverse to show oldest-to-newest

                $conversation['chat_history'] = [];
                $messagesToCache = []; // Track messages that need their HTML cached
                
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
                        // PERFORMANCE: Use cached HTML if available, otherwise generate and cache
                        if (!empty($message['content_html'])) {
                            $parts[0]['text'] = $message['content_html'];
                        } else {
                            $formattedHtml = formatResponse($parts[0]['text']);
                            $parts[0]['text'] = $formattedHtml;
                            // Queue for async caching (don't slow down response)
                            $messagesToCache[] = ['id' => $message['id'], 'html' => $formattedHtml];
                        }
                    }
                    $conversation['chat_history'][] = [
                        'role' => $message['role'],
                        'parts' => $parts,
                        'message_id' => $message['id'],
                        'is_edited' => (int)$message['is_edited']
                    ];
                }
                
                // PERFORMANCE: Cache formatted HTML for future requests (batch update)
                if (!empty($messagesToCache)) {
                    $cacheStmt = $pdo->prepare("UPDATE messages SET content_html = ? WHERE id = ?");
                    foreach ($messagesToCache as $toCache) {
                        $cacheStmt->execute([$toCache['html'], $toCache['id']]);
                    }
                }

                ob_clean();
                echo json_encode(['success' => true, 'conversation' => $conversation]);
            } catch (Exception $e) {
                error_log("get_conversation error [convo_id=$convo_id]: " . $e->getMessage());
                ob_end_clean(); // Discard any partial output safely
                ob_start(); // Restart clean buffer for JSON response
                http_response_code(500);
                echo json_encode([
                    'success' => false, 
                    'error' => 'Could not fetch conversation.',
                    'debug' => 'DB error: ' . $e->getMessage()
                ]);
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
                
                // Use Quiz/Task specific key if available, otherwise fallback to main key
                $apiKey = (isset($config['QUIZ_API_KEY']) && !empty($config['QUIZ_API_KEY'])) 
                    ? $config['QUIZ_API_KEY'] 
                    : $config['GEMINI_API_KEY'];

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

        case 'edit_message':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
                break;
            }

            $message_id = $_POST['message_id'] ?? null;
            $new_content = trim($_POST['new_content'] ?? '');
            $edit_convo_id = $_POST['conversation_id'] ?? null;

            if (!$message_id || empty($new_content) || !$edit_convo_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
                break;
            }

            try {
                $pdo = getDbConnection();

                // Verify user owns the conversation
                $stmt = $pdo->prepare("SELECT id FROM conversations WHERE id = ? AND user_id = ?");
                $stmt->execute([$edit_convo_id, $_SESSION['user_id']]);
                if (!$stmt->fetch()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => 'Conversation access denied.']);
                    break;
                }

                // Verify this is the last user message in the conversation
                $stmt = $pdo->prepare("SELECT id FROM messages WHERE conversation_id = ? AND role = 'user' ORDER BY created_at DESC LIMIT 1");
                $stmt->execute([$edit_convo_id]);
                $lastUserMsg = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$lastUserMsg || $lastUserMsg['id'] != $message_id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Only the last user message can be edited.']);
                    break;
                }

                // Wrap database operations in a transaction for atomicity
                $pdo->beginTransaction();

                try {
                    // Update the user message content
                    $stmt = $pdo->prepare("UPDATE messages SET content = ?, is_edited = 1, content_html = NULL WHERE id = ? AND conversation_id = ?");
                    $stmt->execute([json_encode([['text' => $new_content]]), $message_id, $edit_convo_id]);

                    // Delete all messages after the edited message (the AI response)
                    $stmt = $pdo->prepare("DELETE FROM messages WHERE conversation_id = ? AND id > ?");
                    $stmt->execute([$edit_convo_id, $message_id]);
                    
                    // Update conversation timestamp
                    $stmt = $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$edit_convo_id]);

                    $pdo->commit();
                    ob_clean();
                    echo json_encode(['success' => true]);
                } catch (Exception $innerE) {
                    $pdo->rollBack();
                    throw $innerE;
                }
            } catch (Exception $e) {
                error_log("Edit message error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Could not edit message.']);
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

// Check chat rate limit
$pdo = getDbConnection();
if (!checkChatRateLimit($pdo, $_SESSION['user_id'])) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'You are sending messages too quickly. Please wait a moment before trying again.'
    ]);
    exit;
}

// --- Check for large file upload error ---
// If the request method is POST but the POST and FILES arrays are empty, it's a classic sign
// that the upload exceeded the server's post_max_size limit.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    http_response_code(413);
    echo json_encode(['success' => false, 'error' => "The uploaded file is too large. It exceeds the server's configured limit (post_max_size)."]);
    exit;
}

$question = $_POST['question'] ?? '';
$learningLevel = $_POST['learningLevel'] ?? 'Understanding';

// Validate inputs early
if (empty(trim($question)) && empty($_FILES['attachment'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please provide a question or an attachment for the AI tutor.']);
    exit;
}

// Enforce strict Bloom taxonomy levels
$validLevels = ['Foundation', 'Understanding', 'Analysis', 'Synthesis'];
if (!in_array($learningLevel, $validLevels, true)) {
    $learningLevel = 'Understanding'; // Safe default fallback
}

// Session context from frontend SessionContextManager
$session_goal = $_POST['session_goal'] ?? null;
$session_context = null;
if (!empty($_POST['session_context'])) {
    $session_context = json_decode($_POST['session_context'], true);
}

$conversation_id = $_POST['conversation_id'] ?? null;


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
    
    // Load Quiz/Test Prep API Key
    if (isset($config['QUIZ_API_KEY']) && !empty($config['QUIZ_API_KEY'])) {
        define('QUIZ_API_KEY', $config['QUIZ_API_KEY']);
    }
    
    // Load Groq API key for fallback (free tier)
    if (isset($config['GROQ_API_KEY']) && !empty($config['GROQ_API_KEY'])) {
        define('GROQ_API_KEY', $config['GROQ_API_KEY']);
    }
    
    // Load DeepSeek API key for fallback (paid)
    if (isset($config['DEEPSEEK_API_KEY']) && !empty($config['DEEPSEEK_API_KEY'])) {
        define('DEEPSEEK_API_KEY', $config['DEEPSEEK_API_KEY']);
    }

    if (GEMINI_API_KEY === 'YOUR_ACTUAL_GEMINI_API_KEY_HERE' || empty(GEMINI_API_KEY)) {
        throw new Exception('Gemini API Key is not configured in config.ini.');
    }

    $user_message_parts = [];

    // Check for file upload (Multiple files supported)
    if (DEBUG_MODE) {
        file_put_contents('debug_log.txt', "--- New Request ---\n", FILE_APPEND);
        file_put_contents('debug_log.txt', "FILES: " . print_r($_FILES, true) . "\n", FILE_APPEND);
    }

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

        if (DEBUG_MODE) {
            file_put_contents('debug_log.txt', "Processed Files Array: " . print_r($files, true) . "\n", FILE_APPEND);
        }

        // Process each file
        foreach ($files as $file) {
            try {
                $part = prepareFileParts($file, $question);
                $user_message_parts[] = $part;
            } catch (Exception $e) {
                if (DEBUG_MODE) {
                    file_put_contents('debug_log.txt', "Error processing file: " . $e->getMessage() . "\n", FILE_APPEND);
                }
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

    if (DEBUG_MODE) {
        file_put_contents('debug_log.txt', "User Message Parts: " . print_r($user_message_parts, true) . "\n", FILE_APPEND);
    }

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

    // Check if this is a resubmission after an edit (skip inserting new user message)
    $is_edit_resubmit = isset($_POST['resubmit_after_edit']) && $_POST['resubmit_after_edit'] === '1';

    if (!$is_edit_resubmit) {
        // Save user message to the database
        $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, role, content) VALUES (?, 'user', ?)");
        $stmt->execute([$conversation_id, json_encode($user_message_parts)]);
        $user_message_id = $pdo->lastInsertId();
    } else {
        // For edit resubmit, get the last user message ID
        $stmt = $pdo->prepare("SELECT id FROM messages WHERE conversation_id = ? AND role = 'user' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$conversation_id]);
        $user_message_id = $stmt->fetchColumn();
    }

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
    $stmt = $pdo->prepare("SELECT country, primary_language, education_level, field_of_study, knowledge_level FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_profile = $stmt->fetch(PDO::FETCH_ASSOC);

    // Build personalization context for the AI
    $personalization_context = "";

    // Only add profile context if user has profile data
    $hasProfile = $user_profile && ($user_profile['country'] || $user_profile['education_level'] || $user_profile['field_of_study'] || $user_profile['primary_language'] || $user_profile['knowledge_level']);

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
        if (!empty($user_profile['knowledge_level'])) {
            $kl = $user_profile['knowledge_level'];
            $kl_instructions = [
                'beginner'     => "Start with foundational concepts. Avoid jargon or define it immediately. Use simple analogies. Break down every step. Reassure and encourage frequently.",
                'intermediate' => "Assume basic familiarity. Build on what they know; bridge gaps. Introduce terminology with brief explanations. Mix examples with theory.",
                'advanced'     => "Skip basics unless explicitly asked. Use precise technical language. Engage with nuance, edge cases, and deeper implications. Challenge them when appropriate.",
            ];
            $personalization_context .= "- Prior knowledge level (from onboarding assessment): **{$kl}**\n";
            $personalization_context .= "  → {$kl_instructions[$kl]}\n";
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

            // If nothing found, proactively seed the knowledge base then retry
            if (empty($relevantKnowledge) && strlen($userQuestionText) > 20) {
                $knowledgeService->searchAndStore($userQuestionText);
                $relevantKnowledge = $knowledgeService->retrieveRelevant($userQuestionText, 3);
            }

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
    $system_prompt = buildSystemPrompt($learningLevel, $personalization_context);
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

    // Determine which API key to use based on session goal
    $activeApiKey = GEMINI_API_KEY;
    if (($session_goal === 'test_prep' || $session_goal === 'practice') && defined('QUIZ_API_KEY')) {
        $activeApiKey = QUIZ_API_KEY;
        if (DEBUG_MODE) {
            error_log("Using dedicated QUIZ_API_KEY for session goal: $session_goal");
        }
    }

    // Cascade fallback: Gemini → Groq (free) → DeepSeek (paid)
    $usedFallback = false;
    $fallbackProvider = null;
    
    try {
        $responseData = callGeminiAPI($payload, $activeApiKey);
    } catch (Exception $geminiError) {
        $isRateLimitError = strpos($geminiError->getMessage(), 'rate limit') !== false || 
                           strpos($geminiError->getMessage(), '429') !== false ||
                           strpos($geminiError->getMessage(), '500') !== false ||
                           strpos($geminiError->getMessage(), '503') !== false;
        
        if (!$isRateLimitError) {
            throw $geminiError;
        }
        
        error_log("Gemini error, trying fallbacks: " . $geminiError->getMessage());
        
        // Try Groq first (free tier)
        if (defined('GROQ_API_KEY')) {
            try {
                error_log("Attempting Groq fallback...");
                $responseData = callGroqAPI($chat_history, $system_prompt, GROQ_API_KEY);
                $usedFallback = true;
                $fallbackProvider = 'groq';
            } catch (Exception $groqError) {
                error_log("Groq fallback failed: " . $groqError->getMessage());
                
                // Try DeepSeek as last resort (paid)
                if (defined('DEEPSEEK_API_KEY')) {
                    try {
                        error_log("Attempting DeepSeek fallback...");
                        $responseData = callDeepSeekAPI($chat_history, $system_prompt, DEEPSEEK_API_KEY);
                        $usedFallback = true;
                        $fallbackProvider = 'deepseek';
                    } catch (Exception $deepseekError) {
                        error_log("DeepSeek fallback failed: " . $deepseekError->getMessage());
                        throw new Exception("All AI providers failed. Gemini: {$geminiError->getMessage()}");
                    }
                } else {
                    throw new Exception("Groq fallback failed and DeepSeek not configured. Error: " . $groqError->getMessage());
                }
            }
        } elseif (defined('DEEPSEEK_API_KEY')) {
            // No Groq, try DeepSeek directly
            try {
                error_log("Attempting DeepSeek fallback (no Groq configured)...");
                $responseData = callDeepSeekAPI($chat_history, $system_prompt, DEEPSEEK_API_KEY);
                $usedFallback = true;
                $fallbackProvider = 'deepseek';
            } catch (Exception $deepseekError) {
                error_log("DeepSeek fallback failed: " . $deepseekError->getMessage());
                throw new Exception("All AI providers failed. Error: " . $geminiError->getMessage());
            }
        } else {
            throw $geminiError; // No fallbacks configured
        }
    }

    $responseParts = $responseData['candidates'][0]['content']['parts'] ?? [];
    $answer = '';
    foreach ($responseParts as $part) {
        // Skip thinking/thought parts that Gemini 2.5 Flash inserts before the real response
        if (!empty($part['thought'])) continue;
        if (isset($part['text'])) $answer .= $part['text'];
    }
    if (!empty($answer)) {
        
        // PERFORMANCE: Pre-format HTML and cache it immediately
        $formattedAnswer = formatResponse($answer);

        // Save AI response to the database with cached HTML
        $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, role, content, content_html) VALUES (?, 'model', ?, ?)");
        $stmt->execute([$conversation_id, json_encode([['text' => $answer]]), $formattedAnswer]);

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
                // Also use the active API key for outline generation if relevant
                $outline = generateLearningOutline($detectedTopic, $currentSessionGoal, $educationLevel, $activeApiKey);

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
        $stmt = $pdo->prepare("UPDATE conversations SET context_data = ?, progress = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->execute([json_encode($contextData), $hybridProgress, $conversation_id, $_SESSION['user_id']]);

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


        // $formattedAnswer already computed above when saving to DB
        $response_payload = [
            'success' => true,
            'answer' => $formattedAnswer,
            'conversation_id' => $conversation_id,
            'user_message_id' => $user_message_id
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
        // Detailed error logging for debugging
        $errorDetails = [];
        
        // Check if response was blocked due to safety settings
        if (isset($responseData['promptFeedback']['blockReason'])) {
            $blockReason = $responseData['promptFeedback']['blockReason'];
            error_log("Gemini API: Prompt blocked - Reason: " . $blockReason);
            throw new Exception("Your message was blocked due to content safety policies. Please try rephrasing your question.");
        }
        
        // Check if candidates array exists but is empty
        if (isset($responseData['candidates']) && empty($responseData['candidates'])) {
            error_log("Gemini API: Empty candidates array");
            throw new Exception("The AI could not generate a response. Please try again.");
        }
        
        // Check if candidates exist but content is missing (finish reason issues)
        if (isset($responseData['candidates'][0])) {
            $candidate = $responseData['candidates'][0];
            
            if (isset($candidate['finishReason']) && $candidate['finishReason'] !== 'STOP') {
                $finishReason = $candidate['finishReason'];
                error_log("Gemini API: Non-standard finish reason - " . $finishReason);
                
                if ($finishReason === 'SAFETY') {
                    throw new Exception("The AI response was blocked due to safety settings. Please try a different question.");
                } elseif ($finishReason === 'MAX_TOKENS') {
                    throw new Exception("The AI response was too long. Please ask a more specific question.");
                } elseif ($finishReason === 'RECITATION') {
                    throw new Exception("Response blocked due to content policies. Please try rephrasing.");
                }
            }
            
            if (!isset($candidate['content'])) {
                error_log("Gemini API: No content in candidate - " . print_r($candidate, true));
                throw new Exception("The AI returned an empty response. Please try again.");
            }
        }
        
        // Log full response for debugging unknown issues
        error_log("Gemini API: Unexpected response structure - " . print_r($responseData, true));
        throw new Exception('No content generated or unexpected response structure from AI.');
    }
} catch (Exception $e) {
    // Return 200 for chat errors so the frontend can properly display the error message
    // instead of throwing a JS exception and showing "couldn't connect" network error.
    http_response_code(200);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
