<?php
/**
 * Analytics API
 * 
 * Provides aggregated learning data for the dashboard.
 */

require_once __DIR__ . '/../check_auth.php';
require_once __DIR__ . '/../db_mysql.php';

header('Content-Type: application/json');

try {
    $pdo = getDbConnection();
    $user_id = $_SESSION['user_id'];
    
    // Get date range filter (default: last 30 days)
    $period = $_GET['period'] ?? '30days';
    $dateFilter = match($period) {
        '7days' => 'DATE_SUB(NOW(), INTERVAL 7 DAY)',
        '30days' => 'DATE_SUB(NOW(), INTERVAL 30 DAY)',
        '90days' => 'DATE_SUB(NOW(), INTERVAL 90 DAY)',
        'all' => "'1970-01-01'",
        default => 'DATE_SUB(NOW(), INTERVAL 30 DAY)'
    };
    
    // 1. Basic stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_sessions,
            COUNT(DISTINCT DATE(created_at)) as active_days,
            AVG(progress) as avg_progress,
            MAX(created_at) as last_session
        FROM conversations 
        WHERE user_id = ? AND created_at >= $dateFilter
    ");
    $stmt->execute([$user_id]);
    $basicStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 2. Topics studied (from titles)
    $stmt = $pdo->prepare("
        SELECT title, progress, session_goal, created_at, context_data
        FROM conversations 
        WHERE user_id = ? AND created_at >= $dateFilter AND title IS NOT NULL
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user_id]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Extract topics from titles
    $topicCounts = [];
    foreach ($sessions as $session) {
        $title = $session['title'];
        // Simple topic extraction - first 2-3 words
        $words = explode(' ', $title);
        $topic = implode(' ', array_slice($words, 0, min(3, count($words))));
        $topicCounts[$topic] = ($topicCounts[$topic] ?? 0) + 1;
    }
    arsort($topicCounts);
    $topTopics = array_slice($topicCounts, 0, 8, true);
    
    // 3. Progress over time (daily averages)
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            AVG(progress) as avg_progress,
            COUNT(*) as session_count
        FROM conversations 
        WHERE user_id = ? AND created_at >= $dateFilter
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$user_id]);
    $progressOverTime = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 4. Session goal distribution
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(session_goal, 'general') as goal,
            COUNT(*) as count
        FROM conversations 
        WHERE user_id = ? AND created_at >= $dateFilter
        GROUP BY session_goal
        ORDER BY count DESC
    ");
    $stmt->execute([$user_id]);
    $goalDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 5. Learning streak calculation
    $stmt = $pdo->prepare("
        SELECT DISTINCT DATE(created_at) as study_date
        FROM conversations 
        WHERE user_id = ?
        ORDER BY study_date DESC
        LIMIT 365
    ");
    $stmt->execute([$user_id]);
    $studyDates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $currentStreak = 0;
    $today = new DateTime('today');
    
    foreach ($studyDates as $dateStr) {
        $studyDate = new DateTime($dateStr);
        $diff = $today->diff($studyDate)->days;
        
        if ($diff === $currentStreak) {
            $currentStreak++;
        } else {
            break;
        }
    }
    
    // 6. Milestones completed (from context_data)
    $totalMilestones = 0;
    $completedMilestones = 0;
    
    foreach ($sessions as $session) {
        if ($session['context_data']) {
            $contextData = json_decode($session['context_data'], true);
            if (isset($contextData['outline']['milestones'])) {
                $milestones = $contextData['outline']['milestones'];
                $totalMilestones += count($milestones);
                $completedMilestones += count(array_filter($milestones, fn($m) => $m['completed'] ?? false));
            }
        }
    }
    
    // 7. Recent sessions for display
    $recentSessions = array_map(function($s) {
        return [
            'title' => $s['title'],
            'progress' => round($s['progress'] ?? 0),
            'goal' => $s['session_goal'],
            'date' => $s['created_at']
        ];
    }, array_slice($sessions, 0, 10));
    
    // Compile response
    echo json_encode([
        'success' => true,
        'period' => $period,
        'stats' => [
            'totalSessions' => (int)$basicStats['total_sessions'],
            'activeDays' => (int)$basicStats['active_days'],
            'avgProgress' => round($basicStats['avg_progress'] ?? 0),
            'currentStreak' => $currentStreak,
            'topicsStudied' => count($topicCounts),
            'milestonesCompleted' => $completedMilestones,
            'milestonesTotal' => $totalMilestones
        ],
        'topTopics' => $topTopics,
        'progressOverTime' => $progressOverTime,
        'goalDistribution' => $goalDistribution,
        'recentSessions' => $recentSessions
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
