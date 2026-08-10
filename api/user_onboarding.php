<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db_mysql.php';

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

// Build field_of_study from whichever subject fields the user filled in
$field_of_study = null;
$edu = isset($input['educationLevel']) && is_string($input['educationLevel']) ? $input['educationLevel'] : null;
if ($edu === 'college' || $edu === 'university' || $edu === 'University') {
    $parts = array_filter([
        isset($input['universityProgram']) && is_string($input['universityProgram']) ? $input['universityProgram'] : null,
        !empty($input['customSubjects']) ? implode(', ', (array)$input['customSubjects']) : null,
    ]);
    $field_of_study = implode(' — ', $parts) ?: null;
} elseif ($edu === 'high' || $edu === 'shs' || $edu === 'Secondary') {
    $parts = array_filter([
        isset($input['shsProgram']) && is_string($input['shsProgram']) ? $input['shsProgram'] : null,
        !empty($input['shsElectives']) ? implode(', ', (array)$input['shsElectives']) : null,
    ]);
    $field_of_study = implode(': ', $parts) ?: null;
} else {
    $primary = isset($input['primarySubject']) && is_string($input['primarySubject']) ? $input['primarySubject'] : null;
    $others  = !empty($input['subjects']) ? implode(', ', array_filter(array_map(fn($s) => is_string($s) ? $s : null, (array)$input['subjects']))) : null;
    $field_of_study = $primary ?: $others ?: null;
}
if ($field_of_study) {
    $field_of_study = substr($field_of_study, 0, 255);
}

// Map education level to the DB ENUM
$edu_level_map = [
    'primary'    => 'Primary',
    'jhs'        => 'Secondary',
    'high'       => 'Secondary',
    'shs'        => 'Secondary',
    'secondary'  => 'Secondary',
    'college'    => 'University',
    'university' => 'University',
    'graduate'   => 'Graduate',
    'professional' => 'Professional',
];
$education_level  = $edu !== null ? ($edu_level_map[strtolower($edu)] ?? null) : null;
$rawCountry       = $input['country'] ?? null;
$country          = (isset($rawCountry) && is_string($rawCountry)) ? substr($rawCountry, 0, 100) : null;
$rawLanguage      = $input['language'] ?? null;
$primary_language = (isset($rawLanguage) && is_string($rawLanguage)) ? substr($rawLanguage, 0, 50) : null;

// Personal interests/hobbies — mined by the tutor for examples, scenarios, and
// widget content; kept out of the tutor's hands only when inviting the learner's
// own analogy, so that specific connection stays learner-generated.
// An explicitly supplied [] (empty array) is still stored as '[]' so onboarding.php
// can distinguish "interests saved" from "interests never submitted" (NULL).
$interests = null;
if (isset($input['interests']) && is_array($input['interests'])) {
    $cleanInterests = array_slice(array_values(array_filter(array_map('trim', array_map('strval', $input['interests'])))), 0, 20);
    $interests = json_encode($cleanInterests);
}

try {
    $pdo = getDbConnection();

    // Step 1: Mark Onboarding as Complete
    try {
        $stmt = $pdo->prepare("UPDATE users SET onboarding_completed = 1 WHERE id = ?");
        $stmt->execute([$user_id]);
    } catch (Exception $e) {
        error_log("Onboarding status update failed for user $user_id: " . $e->getMessage());
        $response['errors'][] = "Status update failed; retrying profile save.";
        // Continue to try saving profile...
    }

    // Step 2: Save Profile Data (full JSON blob)
    try {
        $stmt = $pdo->prepare("UPDATE users SET profile_data = ? WHERE id = ?");
        $stmt->execute([$profile_data, $user_id]);
        $response['success'] = true;
    } catch (Exception $e) {
        error_log("Onboarding profile save failed for user $user_id: " . $e->getMessage());
        $response['errors'][] = "Profile save failed; attempting backup.";
        throw $e; // Trigger catch block for file backup
    }

    // Step 3: Save personalization columns extracted from profile JSON (best-effort)
    try {
        $fields = [];
        $params = [];
        if ($knowledge_level)  { $fields[] = 'knowledge_level = ?';  $params[] = $knowledge_level; }
        if ($field_of_study)   { $fields[] = 'field_of_study = ?';   $params[] = $field_of_study; }
        if ($education_level)  { $fields[] = 'education_level = ?';  $params[] = $education_level; }
        if ($country)          { $fields[] = 'country = ?';          $params[] = $country; }
        if ($primary_language) { $fields[] = 'primary_language = ?'; $params[] = $primary_language; }
        if ($interests)        { $fields[] = 'interests = ?';        $params[] = $interests; }

        if (!empty($fields)) {
            $params[] = $user_id;
            $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?");
            $stmt->execute($params);
        }
    } catch (Exception $e) {
        error_log("Error saving personalization columns: " . $e->getMessage());
        $response['errors'][] = "Personalization data partially saved; some fields may not appear immediately.";
    }

} catch (Exception $e) {
    // OUTER CATCH: Handles Connection Failures OR Query Failures
    error_log("Onboarding critical DB error for user $user_id: " . $e->getMessage());
    $response['errors'][] = "Could not save to the database; attempting backup.";

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
