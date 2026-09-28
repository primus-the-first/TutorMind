<?php
// api/delete_account.php

// --- BOOTSTRAP & AUTHENTICATION ---
require_once '../includes/check_auth.php'; // Ensures user is logged in
require_once '../includes/db_mysql.php';   // Database connection

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Get the JSON payload from the request
$input = json_decode(file_get_contents('php://input'), true);

// Confirm with the password, or — for accounts that sign in with Google (whose
// password is a random one they never saw) — a fresh Google sign-in.
$password   = $input['password'] ?? null;
$credential = $input['google_credential'] ?? null;
if (json_last_error() !== JSON_ERROR_NONE || (!is_string($password) && !is_string($credential))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request. Confirm with your password or with Google.']);
    exit;
}

$deny = function (int $code, string $error) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
};

try {
    $pdo = getDbConnection();

    // 1. Fetch what we can confirm against
    $stmt = $pdo->prepare("SELECT password_hash, google_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) $deny(401, 'Account not found.');

    if (is_string($credential)) {
        require_once '../includes/google_auth.php';
        try {
            $google = verifyGoogleIdToken($credential);
        } catch (RuntimeException $e) {
            error_log("Account deletion: " . $e->getMessage());
            $deny(502, 'Could not reach Google to confirm. Please try again.');
        }
        if (!$google || empty($user['google_id']) || !hash_equals((string)$user['google_id'], $google['sub'])) {
            $deny(401, 'That Google account is not the one linked to this TutorMind account.');
        }
        // Must be a sign-in made just now for this, not one left over from earlier.
        if (time() - $google['iat'] > 300) $deny(401, 'That Google confirmation expired. Please try again.');
    } elseif (!password_verify($password, $user['password_hash'])) {
        $deny(401, 'Incorrect password.');
    }

    // 2. Confirmed, proceed with deletion
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);

    // 3. Destroy the session and log the user out
    session_destroy();

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Account deleted successfully.']);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Account deletion failed for user ID $user_id: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'A server error occurred. Please try again later.']);
}
?>