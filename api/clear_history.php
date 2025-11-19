<?php
// api/clear_history.php

// --- BOOTSTRAP & AUTHENTICATION ---
require_once '../check_auth.php'; // Ensures user is logged in
require_once '../db_mysql.php';   // Database connection

// Only allow POST requests for this destructive action
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// Get user ID from the session
$user_id = $_SESSION['user_id'];

try {
    $pdo = getDbConnection();

    // Prepare and execute the DELETE statement.
    // This will delete all conversations belonging to the logged-in user.
    // I'm assuming your table is named 'conversations' and has a 'user_id' column.
    $stmt = $pdo->prepare("DELETE FROM conversations WHERE user_id = ?");
    $stmt->execute([$user_id]);

    // Respond with success
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'All chat history has been cleared.']);

} catch (PDOException $e) {
    error_log("Clear history failed for user ID $user_id: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'A server error occurred while clearing history.']);
}
?>