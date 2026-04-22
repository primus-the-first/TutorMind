<?php
/**
 * Analytics API
 *
 * Provides aggregated learning data for the dashboard.
 */

require_once __DIR__ . '/../includes/check_auth.php';
require_once __DIR__ . '/../includes/db_mysql.php';

header('Content-Type: application/json');

try {
    $pdo = getDbConnection();
    $user_id = $_SESSION['user_id'];

    // Date range filter
    $period = $_GET['period'] ?? '30days';
    switch ($period) {
        case '7days':
            $intervalDays = 7;
            $dateFilter   = 'DATE_SUB(NOW(), INTERVAL 7 DAY)';
            break;
        case '90days':
            $intervalDays = 90;
            $dateFilter   = 'DATE_SUB(NOW(), INTERVAL 90 DAY)';
            break;
        case 'all':
            $intervalDays = null;
            $dateFilter   = "'1970-01-01'";
            break;
        default: // 30days
            $intervalDays = 30;
            $dateFilter   = 'DATE_SUB(NOW(), INTERVAL 30 DAY)';
    }

    // Previous-period start/end for trend deltas (only when intervalDays is set)
    $prevDateStart = null;
    $prevDateEnd   = null;
    if ($intervalDays) {
        $prevDateStart = "DATE_SUB(NOW(), INTERVAL " . ($intervalDays * 2) . " DAY)";
        $prevDateEnd   = "DATE_SUB(NOW(), INTERVAL $intervalDays DAY)";
    }

    // -------------------------------------------------------------------------
    // 1. Basic stats (current period)
    // -------------------------------------------------------------------------
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

    // -------------------------------------------------------------------------
    // 2. Topics + session list (current period, up to 200 for subject analysis)
    // -------------------------------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT title, progress, session_goal, created_at, context_data
        FROM conversations
        WHERE user_id = ? AND created_at >= $dateFilter AND title IS NOT NULL
        ORDER BY created_at DESC
        LIMIT 200
    ");
    $stmt->execute([$user_id]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Extract topics from titles for the donut chart
    $topicCounts = [];
    foreach ($sessions as $session) {
        $title     = $session['title'];
        $stopWords = ['help', 'me', 'with', 'the', 'a', 'an', 'how', 'to', 'what', 'is', 'can', 'i', 'about', 'explain', 'understand', 'why', 'does', 'do', 'my', 'for', 'in', 'on'];
        $words     = array_filter(
            explode(' ', strtolower($title)),
            fn($w) => strlen($w) > 2 && !in_array($w, $stopWords)
        );
        $topic = implode(' ', array_slice(array_values($words), 0, 2));
        if (empty($topic)) $topic = 'General';
        $topic = ucwords($topic);
        $topicCounts[$topic] = ($topicCounts[$topic] ?? 0) + 1;
    }
    arsort($topicCounts);
    $topTopics = array_slice($topicCounts, 0, 8, true);

    // -------------------------------------------------------------------------
    // 3. Progress over time (daily averages)
    // -------------------------------------------------------------------------
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

    // -------------------------------------------------------------------------
    // 4. Session goal distribution
    // -------------------------------------------------------------------------
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

    // -------------------------------------------------------------------------
    // 5. Learning streak
    // -------------------------------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT DISTINCT DATE(created_at) as study_date
        FROM conversations
        WHERE user_id = ?
        ORDER BY study_date DESC
        LIMIT 365
    ");
    $stmt->execute([$user_id]);
    $studyDates   = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $currentStreak = 0;
    $today         = new DateTime('today');

    foreach ($studyDates as $dateStr) {
        $studyDate = new DateTime($dateStr);
        $diff      = $today->diff($studyDate)->days;
        if ($diff === 0 || $diff === $currentStreak) {
            if ($diff > 0) $currentStreak++;
        } else {
            break;
        }
    }

    // -------------------------------------------------------------------------
    // 6. Milestones + subject progress (from context_data)
    // -------------------------------------------------------------------------
    $totalMilestones     = 0;
    $completedMilestones = 0;
    $subjectMap          = [];

    foreach ($sessions as $session) {
        $cd = $session['context_data'] ? json_decode($session['context_data'], true) : null;
        if (!$cd) continue;

        $milestones = $cd['outline']['milestones'] ?? [];
        $totalMilestones     += count($milestones);
        $completedMilestones += count(array_filter($milestones, fn($m) => $m['completed'] ?? false));

        // Per-subject aggregation using AI-detected topic
        $subjectKey = isset($cd['topic']) && $cd['topic'] !== '' ? ucwords(trim($cd['topic'])) : null;
        if (!$subjectKey) continue;

        if (!isset($subjectMap[$subjectKey])) {
            $subjectMap[$subjectKey] = [
                'topic'               => $subjectKey,
                'sessions'            => 0,
                'totalProgress'       => 0,
                'milestonesCompleted' => 0,
                'milestonesTotal'     => 0,
            ];
        }
        $subjectMap[$subjectKey]['sessions']++;
        $subjectMap[$subjectKey]['totalProgress'] += (float)($session['progress'] ?? 0);
        $subjectMap[$subjectKey]['milestonesCompleted'] += count(array_filter($milestones, fn($m) => $m['completed'] ?? false));
        $subjectMap[$subjectKey]['milestonesTotal']     += count($milestones);
    }

    $subjectProgress = [];
    foreach ($subjectMap as $entry) {
        $avgProgress   = $entry['sessions'] > 0 ? round($entry['totalProgress'] / $entry['sessions']) : 0;
        $completionPct = $entry['milestonesTotal'] > 0
            ? round(($entry['milestonesCompleted'] / $entry['milestonesTotal']) * 100)
            : $avgProgress;

        $subjectProgress[] = [
            'topic'               => $entry['topic'],
            'sessions'            => $entry['sessions'],
            'avgProgress'         => $avgProgress,
            'milestonesCompleted' => $entry['milestonesCompleted'],
            'milestonesTotal'     => $entry['milestonesTotal'],
            'completionPct'       => $completionPct,
        ];
    }
    usort($subjectProgress, fn($a, $b) => $b['completionPct'] - $a['completionPct']);
    $subjectProgress = array_slice($subjectProgress, 0, 10);

    // -------------------------------------------------------------------------
    // 7. Recent sessions for display
    // -------------------------------------------------------------------------
    $recentSessions = array_map(function ($s) {
        return [
            'title'    => $s['title'],
            'progress' => round($s['progress'] ?? 0),
            'goal'     => $s['session_goal'],
            'date'     => $s['created_at'],
        ];
    }, array_slice($sessions, 0, 10));

    // -------------------------------------------------------------------------
    // 8. Trend indicators (previous period vs current)
    // -------------------------------------------------------------------------
    $trends = null;
    if ($prevDateStart && $prevDateEnd) {
        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) as total_sessions,
                COUNT(DISTINCT DATE(created_at)) as active_days,
                AVG(progress) as avg_progress
            FROM conversations
            WHERE user_id = ? AND created_at >= $prevDateStart AND created_at < $prevDateEnd
        ");
        $stmt->execute([$user_id]);
        $prevStats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Previous period topics count
        $stmt = $pdo->prepare("
            SELECT title FROM conversations
            WHERE user_id = ? AND created_at >= $prevDateStart AND created_at < $prevDateEnd
              AND title IS NOT NULL
            LIMIT 200
        ");
        $stmt->execute([$user_id]);
        $prevTitles      = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $prevTopicCounts = [];
        $stopWords       = ['help', 'me', 'with', 'the', 'a', 'an', 'how', 'to', 'what', 'is', 'can', 'i', 'about', 'explain', 'understand', 'why', 'does', 'do', 'my', 'for', 'in', 'on'];
        foreach ($prevTitles as $t) {
            $words = array_filter(explode(' ', strtolower($t)), fn($w) => strlen($w) > 2 && !in_array($w, $stopWords));
            $key   = ucwords(implode(' ', array_slice(array_values($words), 0, 2))) ?: 'General';
            $prevTopicCounts[$key] = ($prevTopicCounts[$key] ?? 0) + 1;
        }

        $trends = [
            'prevTotalSessions' => (int)$prevStats['total_sessions'],
            'prevActiveDays'    => (int)$prevStats['active_days'],
            'prevAvgProgress'   => round($prevStats['avg_progress'] ?? 0),
            'prevTopicsStudied' => count($prevTopicCounts),
        ];
    }

    // -------------------------------------------------------------------------
    // 9. Activity heatmap (last 365 days, all time scope)
    // -------------------------------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM conversations
        WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)
        GROUP BY DATE(created_at)
    ");
    $stmt->execute([$user_id]);
    $heatmapRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $heatmap    = [];
    foreach ($heatmapRaw as $row) {
        $heatmap[$row['date']] = (int)$row['count'];
    }

    // -------------------------------------------------------------------------
    // 10. Quiz stats
    // -------------------------------------------------------------------------
    $quizStats = ['overallAvg' => 0, 'totalAnswered' => 0, 'scoreOverTime' => [], 'byType' => [], 'recentQuizzes' => []];

    $stmt = $pdo->prepare("
        SELECT AVG(score) as avg_score, COUNT(*) as total
        FROM recall_quizzes
        WHERE user_id = ? AND answered_at IS NOT NULL AND created_at >= $dateFilter
    ");
    $stmt->execute([$user_id]);
    $qOverall = $stmt->fetch(PDO::FETCH_ASSOC);
    $quizStats['overallAvg']    = round(($qOverall['avg_score'] ?? 0) * 100);
    $quizStats['totalAnswered'] = (int)($qOverall['total'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT DATE(answered_at) as date, AVG(score) as avg_score, COUNT(*) as count
        FROM recall_quizzes
        WHERE user_id = ? AND answered_at IS NOT NULL AND created_at >= $dateFilter
        GROUP BY DATE(answered_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$user_id]);
    $quizStats['scoreOverTime'] = array_map(function ($r) {
        return ['date' => $r['date'], 'avg_score' => round($r['avg_score'] * 100), 'count' => (int)$r['count']];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));

    $stmt = $pdo->prepare("
        SELECT question_type, AVG(score) as avg_score, COUNT(*) as count
        FROM recall_quizzes
        WHERE user_id = ? AND answered_at IS NOT NULL AND created_at >= $dateFilter
        GROUP BY question_type
        ORDER BY avg_score DESC
    ");
    $stmt->execute([$user_id]);
    $quizStats['byType'] = array_map(function ($r) {
        return ['type' => $r['question_type'], 'avg_score' => round($r['avg_score'] * 100), 'count' => (int)$r['count']];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));

    $stmt = $pdo->prepare("
        SELECT question, score, question_type, answered_at
        FROM recall_quizzes
        WHERE user_id = ? AND answered_at IS NOT NULL
        ORDER BY answered_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $quizStats['recentQuizzes'] = array_map(function ($r) {
        return [
            'question'      => $r['question'],
            'score'         => round($r['score'] * 100),
            'question_type' => $r['question_type'],
            'answered_at'   => $r['answered_at'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));

    // -------------------------------------------------------------------------
    // 11. Pomodoro / focus stats
    // -------------------------------------------------------------------------
    $pomodoroStats = ['totalMinutes' => 0, 'totalSessions' => 0, 'completedSessions' => 0, 'completionRate' => 0, 'modeDistribution' => [], 'focusOverTime' => []];

    $stmt = $pdo->prepare("
        SELECT
            SUM(duration_minutes) as total_minutes,
            COUNT(*) as total_sessions,
            SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed_sessions
        FROM pomodoro_sessions
        WHERE user_id = ? AND started_at >= $dateFilter
    ");
    $stmt->execute([$user_id]);
    $pTotals = $stmt->fetch(PDO::FETCH_ASSOC);
    $pomodoroStats['totalMinutes']      = (int)($pTotals['total_minutes'] ?? 0);
    $pomodoroStats['totalSessions']     = (int)($pTotals['total_sessions'] ?? 0);
    $pomodoroStats['completedSessions'] = (int)($pTotals['completed_sessions'] ?? 0);
    $pomodoroStats['completionRate']    = $pomodoroStats['totalSessions'] > 0
        ? round(($pomodoroStats['completedSessions'] / $pomodoroStats['totalSessions']) * 100)
        : 0;

    $stmt = $pdo->prepare("
        SELECT mode, COUNT(*) as count
        FROM pomodoro_sessions
        WHERE user_id = ? AND started_at >= $dateFilter
        GROUP BY mode
        ORDER BY count DESC
    ");
    $stmt->execute([$user_id]);
    $pomodoroStats['modeDistribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT DATE(started_at) as date, SUM(duration_minutes) as total_minutes
        FROM pomodoro_sessions
        WHERE user_id = ? AND completed = 1 AND started_at >= $dateFilter
        GROUP BY DATE(started_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$user_id]);
    $pomodoroStats['focusOverTime'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // -------------------------------------------------------------------------
    // Response
    // -------------------------------------------------------------------------
    echo json_encode([
        'success' => true,
        'period'  => $period,
        'stats'   => [
            'totalSessions'      => (int)$basicStats['total_sessions'],
            'activeDays'         => (int)$basicStats['active_days'],
            'avgProgress'        => round($basicStats['avg_progress'] ?? 0),
            'currentStreak'      => $currentStreak,
            'topicsStudied'      => count($topicCounts),
            'milestonesCompleted'=> $completedMilestones,
            'milestonesTotal'    => $totalMilestones,
        ],
        'trends'          => $trends,
        'topTopics'       => $topTopics,
        'progressOverTime'=> $progressOverTime,
        'goalDistribution'=> $goalDistribution,
        'recentSessions'  => $recentSessions,
        'heatmap'         => $heatmap,
        'subjectProgress' => $subjectProgress,
        'quizStats'       => $quizStats,
        'pomodoroStats'   => $pomodoroStats,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
