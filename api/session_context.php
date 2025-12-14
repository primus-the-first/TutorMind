<?php
/**
 * Session Context API
 * 
 * Handles session context management and recent activity retrieval.
 * 
 * Endpoints:
 * GET  ?action=recent_activity - Get incomplete sessions and upcoming tests
 * POST ?action=update_context  - Update session goal and context data
 * POST ?action=update_progress - Update session progress percentage
 * POST ?action=create_session  - Create a new session with goal
 */

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
$action = $_REQUEST['action'] ?? '';

try {
    $pdo = getDbConnection();

    switch ($action) {
        case 'recent_activity':
            // Get incomplete sessions (progress < 100%, within last 7 days)
            $stmt = $pdo->prepare("
                SELECT 
                    c.id,
                    c.title,
                    c.session_goal,
                    c.context_data,
                    c.progress,
                    c.updated_at,
                    (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id) as message_count
                FROM conversations c
                WHERE c.user_id = ?
                    AND c.completed = FALSE
                    AND c.progress < 100
                    AND c.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY c.updated_at DESC
                LIMIT 5
            ");
            $stmt->execute([$user_id]);
            $incompleteSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Parse context_data JSON for each session
            foreach ($incompleteSessions as &$session) {
                $session['context_data'] = json_decode($session['context_data'], true) ?? [];
            }
            
            // Get upcoming tests from user profile (stored in profile_data)
            $stmt = $pdo->prepare("SELECT profile_data FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $profile = json_decode($user['profile_data'] ?? '{}', true);
            
            $upcomingTests = [];
            if (isset($profile['upcomingTests']) && is_array($profile['upcomingTests'])) {
                $now = new DateTime();
                foreach ($profile['upcomingTests'] as $test) {
                    if (isset($test['testDate'])) {
                        $testDate = new DateTime($test['testDate']);
                        $diff = $now->diff($testDate);
                        $daysRemaining = $diff->invert ? -1 : $diff->days;
                        
                        if ($daysRemaining >= 0 && $daysRemaining <= 7) {
                            $test['daysRemaining'] = $daysRemaining;
                            $upcomingTests[] = $test;
                        }
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'recentSessions' => $incompleteSessions,
                'upcomingTests' => $upcomingTests
            ]);
            break;

        case 'update_context':
            $input = json_decode(file_get_contents('php://input'), true);
            $conversation_id = $input['conversation_id'] ?? null;
            $session_goal = $input['session_goal'] ?? null;
            $context_data = $input['context_data'] ?? null;
            
            if (!$conversation_id) {
                throw new Exception('Conversation ID required');
            }
            
            // Verify ownership
            $stmt = $pdo->prepare("SELECT id FROM conversations WHERE id = ? AND user_id = ?");
            $stmt->execute([$conversation_id, $user_id]);
            if (!$stmt->fetch()) {
                throw new Exception('Conversation not found or access denied');
            }
            
            // Build update query
            $updates = [];
            $params = [];
            
            if ($session_goal !== null) {
                $updates[] = "session_goal = ?";
                $params[] = $session_goal;
            }
            
            if ($context_data !== null) {
                $updates[] = "context_data = ?";
                $params[] = json_encode($context_data);
            }
            
            if (!empty($updates)) {
                $params[] = $conversation_id;
                $sql = "UPDATE conversations SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }
            
            echo json_encode(['success' => true]);
            break;

        case 'update_progress':
            $input = json_decode(file_get_contents('php://input'), true);
            $conversation_id = $input['conversation_id'] ?? null;
            $progress = intval($input['progress'] ?? 0);
            
            if (!$conversation_id) {
                throw new Exception('Conversation ID required');
            }
            
            // Clamp progress to 0-100
            $progress = max(0, min(100, $progress));
            $completed = ($progress >= 95);
            
            $stmt = $pdo->prepare("
                UPDATE conversations 
                SET progress = ?, completed = ?, updated_at = NOW() 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$progress, $completed, $conversation_id, $user_id]);
            
            echo json_encode(['success' => true, 'progress' => $progress, 'completed' => $completed]);
            break;

        case 'create_session':
            $input = json_decode(file_get_contents('php://input'), true);
            $session_goal = $input['session_goal'] ?? null;
            $title = $input['title'] ?? 'New Chat';
            $context_data = $input['context_data'] ?? [];
            
            // Create new conversation with session goal
            $stmt = $pdo->prepare("
                INSERT INTO conversations (user_id, title, session_goal, context_data, progress, completed, created_at, updated_at)
                VALUES (?, ?, ?, ?, 0, FALSE, NOW(), NOW())
            ");
            $stmt->execute([
                $user_id,
                $title,
                $session_goal,
                json_encode($context_data)
            ]);
            
            $conversation_id = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'conversation_id' => $conversation_id
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }

} catch (Exception $e) {
    error_log("Session Context API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
