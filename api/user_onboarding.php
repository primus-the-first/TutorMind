<?php
session_start();
header('Content-Type: application/json');
require_once '../db_mysql.php';

// Security check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$profile_data = json_encode($input); 

// Fallback function to save to file
function saveToFileBackup($user_id, $profile_data) {
    $backupDir = __DIR__ . '/../user_data/profiles/';
    if (!file_exists($backupDir)) {
        if (!mkdir($backupDir, 0777, true)) {
            return false;
        }
    }
    return file_put_contents($backupDir . $user_id . '.json', $profile_data) !== false;
}

$response = ['success' => false, 'errors' => []];

// Extract structured fields from the submitted profile JSON
$knowledge_level = isset($input['knowledgeLevel']) ? $input['knowledgeLevel'] : null;
$valid_levels = ['beginner', 'intermediate', 'advanced'];
if (!in_array($knowledge_level, $valid_levels, true)) {
    $knowledge_level = null;
}

try {
    $pdo = getDbConnection();

    // Step 1: Mark Onboarding as Complete
    try {
        $stmt = $pdo->prepare("UPDATE users SET onboarding_completed = 1 WHERE id = ?");
        $stmt->execute([$user_id]);
    } catch (Exception $e) {
        $response['errors'][] = "Status Update Failed: " . $e->getMessage();
        // Continue to try saving profile...
    }

    // Step 2: Save Profile Data (full JSON blob)
    try {
        $stmt = $pdo->prepare("UPDATE users SET profile_data = ? WHERE id = ?");
        $stmt->execute([$profile_data, $user_id]);
        $response['success'] = true;
    } catch (Exception $e) {
        $response['errors'][] = "DB Profile Save Failed: " . $e->getMessage();
        throw $e; // Trigger catch block for file backup
    }

    // Step 3: Save knowledge_level to dedicated column (best-effort — column may not exist yet)
    if ($knowledge_level) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET knowledge_level = ? WHERE id = ?");
            $stmt->execute([$knowledge_level, $user_id]);
        } catch (Exception $e) {
            // Column missing — not fatal, profile_data JSON has the value as fallback
            error_log("Error updating knowledge_level: " . $e->getMessage() . " [Code: " . $e->getCode() . "]");
            $response['errors'][] = "Unable to read user knowledge level; please retry or contact support";
        }
    }

} catch (Exception $e) {
    // OUTER CATCH: Handles Connection Failures OR Query Failures
    $response['errors'][] = "Critical DB Error: " . $e->getMessage();
    
    // Attempt File Backup
    if (saveToFileBackup($user_id, $profile_data)) {
        $response['success'] = true;
        $response['errors'][] = "Saved to file backup successfully.";
        // IMPORTANT: We update session to reflect completion so middleware respects it immediately
        $_SESSION['onboarding_completed'] = true; // Use session flag if DB fail
    } else {
        $response['errors'][] = "File backup also failed.";
    }
}

if ($response['success']) {
    echo json_encode($response);
} else {
    http_response_code(500);
    echo json_encode($response);
}
?>
