<?php
// api/push_subscribe.php
// Manages a user's Web Push subscriptions (one row per browser/device).

require_once '../includes/check_auth.php';
require_once __DIR__ . '/../includes/csrf.php';
requireCSRFForWrites(); // writes need the X-CSRF-Token header
require_once '../includes/db_mysql.php';

try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleSubscribe($pdo, $user_id);
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    handleUnsubscribe($pdo, $user_id);
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
}

/**
 * True only for https endpoints on a known browser push service.
 */
function isAllowedPushEndpoint(string $endpoint): bool {
    $parts = parse_url($endpoint);
    if ($parts === false || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
        return false;
    }

    $host = strtolower(rtrim($parts['host'], '.'));
    if (in_array($host, ['fcm.googleapis.com', 'updates.push.services.mozilla.com', 'web.push.apple.com'], true)) {
        return true;
    }
    foreach (['.notify.windows.com', '.push.apple.com'] as $suffix) {
        if (strlen($host) > strlen($suffix) && substr($host, -strlen($suffix)) === $suffix) {
            return true;
        }
    }
    return false;
}

/**
 * Stores (or refreshes) a push subscription for the logged-in user.
 *
 * @param PDO $pdo The database connection object.
 * @param int $user_id The ID of the logged-in user.
 */
function handleSubscribe(PDO $pdo, int $user_id): void {
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE || empty($input['endpoint']) || empty($input['keys']['p256dh']) || empty($input['keys']['auth'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid push subscription payload.']);
        return;
    }

    $endpoint = $input['endpoint'];
    $p256dh = $input['keys']['p256dh'];
    $auth = $input['keys']['auth'];

    // The reminder script later POSTs to this endpoint, so only real push services are
    // accepted (otherwise a user could aim the server at an internal address).
    if (!is_string($endpoint) || !is_string($p256dh) || !is_string($auth)
        || strlen($endpoint) > 2048 || strlen($p256dh) > 255 || strlen($auth) > 255
        || !isAllowedPushEndpoint($endpoint)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid push subscription payload.']);
        return;
    }

    $endpointHash = hash('sha256', $endpoint);
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO push_subscriptions (user_id, endpoint, endpoint_hash, p256dh, auth, user_agent)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), p256dh = VALUES(p256dh), auth = VALUES(auth), user_agent = VALUES(user_agent)"
        );
        $stmt->execute([$user_id, $endpoint, $endpointHash, $p256dh, $auth, $userAgent]);

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Push subscription saved.']);
    } catch (PDOException $e) {
        error_log("Failed to save push subscription: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save push subscription.']);
    }
}

/**
 * Removes a push subscription belonging to the logged-in user.
 *
 * @param PDO $pdo The database connection object.
 * @param int $user_id The ID of the logged-in user.
 */
function handleUnsubscribe(PDO $pdo, int $user_id): void {
    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE || empty($input['endpoint'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid request.']);
        return;
    }

    $endpointHash = hash('sha256', $input['endpoint']);

    try {
        $stmt = $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint_hash = ? AND user_id = ?");
        $stmt->execute([$endpointHash, $user_id]);

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Push subscription removed.']);
    } catch (PDOException $e) {
        error_log("Failed to remove push subscription: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to remove push subscription.']);
    }
}
