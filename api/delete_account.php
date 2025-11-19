<?php
// api/delete_account.php

// --- BOOTSTRAP & AUTHENTICATION ---
require_once '../check_auth.php'; // Ensures user is logged in
require_once '../db_mysql.php';   // Database connection

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

if (json_last_error() !== JSON_ERROR_NONE || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request. Password is required.']);
    exit;
}

$password = $input['password'];

try {
    $pdo = getDbConnection();

    // 1. Fetch the user's current password hash
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'error' => 'Incorrect password.']);
        exit;
    }

    // 2. Password is correct, proceed with deletion
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