<?php
// api/user_settings.php

// --- BOOTSTRAP & AUTHENTICATION ---
// Start session and check if the user is logged in.
require_once '../check_auth.php';
// Include the database connection function.
require_once '../db_mysql.php';

// --- CONFIGURATION ---
// Define the list of fields that are managed by this API.
$allowed_fields = [
    'first_name', 'last_name', 'email', 'username', 'learning_level', 'response_style',
    'email_notifications', 'study_reminders', 'feature_announcements', 'weekly_summary',
    'data_sharing', 'dark_mode', 'font_size', 'chat_density'
];

// --- MAIN LOGIC ---
// Get the database connection.
try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    // If the database connection fails, return a server error.
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit;
}

// Get the user ID from the session.
$user_id = $_SESSION['user_id'];

// --- ROUTING based on HTTP method ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    handleGetRequest($pdo, $user_id);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handlePostRequest($pdo, $user_id, $allowed_fields);
} else {
    // If the method is not GET or POST, return a 405 Method Not Allowed error.
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
}

// --- FUNCTION DEFINITIONS ---

/**
 * Handles GET requests to fetch user settings.
 *
 * @param PDO $pdo The database connection object.
 * @param int $user_id The ID of the logged-in user.
 */
function handleGetRequest(PDO $pdo, int $user_id): void {
    try {
        // Prepare and execute the query to get user settings.
        // We also fetch created_at for display purposes.
        $stmt = $pdo->prepare("SELECT first_name, last_name, email, username, created_at, learning_level, response_style, email_notifications, study_reminders, feature_announcements, weekly_summary, data_sharing, dark_mode, font_size, chat_density FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($settings) {
            // Convert boolean-like fields (0/1) to actual booleans for easier frontend handling.
            $settings['email_notifications'] = (bool)$settings['email_notifications'];
            $settings['study_reminders'] = (bool)$settings['study_reminders'];
            $settings['feature_announcements'] = (bool)$settings['feature_announcements'];
            $settings['weekly_summary'] = (bool)$settings['weekly_summary'];
            $settings['data_sharing'] = (bool)$settings['data_sharing'];
            $settings['dark_mode'] = (bool)$settings['dark_mode'];

            // Return the settings as JSON.
            http_response_code(200);
            echo json_encode(['success' => true, 'settings' => $settings]);
        } else {
            // If no user is found, return a 404 Not Found error.
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'User not found.']);
        }
    } catch (PDOException $e) {
        // In case of a database error, return a 500 Internal Server Error.
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch settings: ' . $e->getMessage()]);
    }
}

/**
 * Handles POST requests to update user settings.
 *
 * @param PDO $pdo The database connection object.
 * @param int $user_id The ID of the logged-in user.
 * @param array $allowed_fields A whitelist of fields that can be updated.
 */
function handlePostRequest(PDO $pdo, int $user_id, array $allowed_fields): void {
    // Get the JSON payload from the request.
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON payload.']);
        return;
    }

    // --- VALIDATION ---
    $updates = [];
    $params = [];

    foreach ($allowed_fields as $field) {
        if (isset($input[$field])) {
            // Sanitize and prepare the value for the query.
            $value = $input[$field];

            // Special validation for email
            if ($field === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid email format.']);
                return;
            }
            
            // Special validation for username (example: must be alphanumeric)
            if ($field === 'username' && !preg_match('/^[a-zA-Z0-9_]+$/', $value)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Username can only contain letters, numbers, and underscores.']);
                return;
            }

            // Convert boolean values from frontend (true/false) to integer (1/0) for the database.
            if (is_bool($value)) {
                $value = $value ? 1 : 0;
            }

            $updates[] = "`$field` = ?";
            $params[] = $value;
        }
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No valid fields provided for update.']);
        return;
    }

    // Add the user ID to the parameters for the WHERE clause.
    $params[] = $user_id;

    // --- DATABASE UPDATE ---
    try {
        // Construct the SQL query dynamically from the valid fields.
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Check if any rows were actually updated.
        if ($stmt->rowCount() > 0) {
            // If the username, first_name, or last_name was changed, update the session variables.
            if (isset($input['username'])) {
                $_SESSION['username'] = $input['username'];
            }
            if (isset($input['first_name'])) {
                $_SESSION['first_name'] = $input['first_name'];
            }
            if (isset($input['last_name'])) {
                $_SESSION['last_name'] = $input['last_name'];
            }
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Settings updated successfully.']);
        } else {
            // This can happen if the submitted values are the same as the current ones.
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'No changes detected.']);
        }
    } catch (PDOException $e) {
        // Handle potential unique constraint violations (e.g., email or username already exists).
        if ($e->getCode() == 23000) { // Integrity constraint violation
            http_response_code(409); // Conflict
            echo json_encode(['success' => false, 'error' => 'Email or username already exists.']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to update settings: ' . $e->getMessage()]);
        }
    }
}
?>
