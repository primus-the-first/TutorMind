<?php
// api/user_onboarding.php
// Handles user onboarding operations - checking status and saving onboarding data

// --- BOOTSTRAP & AUTHENTICATION ---
require_once '../check_auth.php';
require_once '../db_mysql.php';

// Set JSON header
header('Content-Type: application/json');

// Get the database connection
try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit;
}

// Get the user ID from the session
$user_id = $_SESSION['user_id'];

// --- ROUTING based on HTTP method ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    handleGetRequest($pdo, $user_id);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handlePostRequest($pdo, $user_id);
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
}

// --- FUNCTION DEFINITIONS ---

/**
 * Handles GET requests to check onboarding status
 *
 * @param PDO $pdo The database connection object
 * @param int $user_id The ID of the logged-in user
 */
function handleGetRequest(PDO $pdo, int $user_id): void {
    try {
        // Fetch onboarding status and existing data
        $stmt = $pdo->prepare("
            SELECT 
                onboarding_completed,
                country,
                primary_language,
                education_level,
                field_of_study,
                institution
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$user_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            // Convert onboarding_completed to boolean
            $data['onboarding_completed'] = (bool)$data['onboarding_completed'];
            
            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'User not found.']);
        }
    } catch (PDOException $e) {
        error_log("Onboarding GET Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch onboarding status.']);
    }
}

/**
 * Handles POST requests to save onboarding data
 *
 * @param PDO $pdo The database connection object
 * @param int $user_id The ID of the logged-in user
 */
function handlePostRequest(PDO $pdo, int $user_id): void {
    // Get the JSON payload from the request
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON payload.']);
        return;
    }

    // --- VALIDATION ---
    $errors = [];

    // Required fields validation
    if (empty($input['country'])) {
        $errors[] = 'Country is required.';
    }

    if (empty($input['education_level'])) {
        $errors[] = 'Education level is required.';
    } else {
        // Validate education level enum
        $valid_levels = ['Primary', 'Secondary', 'University', 'Graduate', 'Professional', 'Other'];
        if (!in_array($input['education_level'], $valid_levels)) {
            $errors[] = 'Invalid education level.';
        }
    }

    // Optional field validation
    if (isset($input['primary_language']) && empty(trim($input['primary_language']))) {
        $input['primary_language'] = 'English'; // Default
    }

    // Field of study is only relevant for University and above
    if (isset($input['field_of_study']) && !empty($input['field_of_study'])) {
        if (strlen($input['field_of_study']) > 255) {
            $errors[] = 'Field of study is too long.';
        }
    }

    // Institution validation
    if (isset($input['institution']) && !empty($input['institution'])) {
        if (strlen($input['institution']) > 255) {
            $errors[] = 'Institution name is too long.';
        }
    }

    // If there are validation errors, return them
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => $errors]);
        return;
    }

    // --- DATABASE UPDATE ---
    try {
        $stmt = $pdo->prepare("
            UPDATE users SET
                country = ?,
                primary_language = ?,
                education_level = ?,
                field_of_study = ?,
                institution = ?,
                onboarding_completed = 1,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $input['country'],
            $input['primary_language'] ?? 'English',
            $input['education_level'],
            $input['field_of_study'] ?? null,
            $input['institution'] ?? null,
            $user_id
        ]);

        if ($stmt->rowCount() > 0) {
            // Update session variables if they exist
            $_SESSION['onboarding_completed'] = true;
            
            http_response_code(200);
            echo json_encode([
                'success' => true, 
                'message' => 'Onboarding completed successfully!'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to save onboarding data.']);
        }
    } catch (PDOException $e) {
        error_log("Onboarding POST Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error occurred.']);
    }
}
?>
