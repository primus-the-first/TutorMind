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
    $intervalDays = 30; // default
    if ($period === '7days') $intervalDays = 7;
    elseif ($period === '90days') $intervalDays = 90;
    elseif ($period === 'all') $intervalDays = null;
    
    $startDate = '1970-01-01 00:00:00';
    $prevStartDate = null;
    $prevEndDate = null;
    
    if ($intervalDays !== null) {
        $d = new DateTime();
        $d->modify("-$intervalDays days");
        $startDate = $d->format('Y-m-d H:i:s');
        
        $prevEndDate = $startDate;
        $d2 = new DateTime();
        $d2->modify("-" . ($intervalDays * 2) . " days");
        $prevStartDate = $d2->format('Y-m-d H:i:s');
    }

    // -------------------------------------------------------------------------
    // 1-4, 6, 7. Combine Sessions Data
    // -------------------------------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT title, progress, session_goal, created_at, context_data
        FROM conversations
        WHERE user_id = ? AND created_at >= ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id, $startDate]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalSessions = count($sessions);
    $totalProgress = 0;
    $activeDaysSet = [];
    $topicCounts = [];
    $progressMap = [];
    $goalMap = [];
    $subjectMap = [];
    $totalMilestones = 0;
    $completedMilestones = 0;
    
    $stopWords = ['help', 'me', 'with', 'the', 'a', 'an', 'how', 'to', 'what', 'is', 'can', 'i', 'about', 'explain', 'understand', 'why', 'does', 'do', 'my', 'for', 'in', 'on'];

    foreach ($sessions as $s) {
        $date = substr($s['created_at'], 0, 10);
        $activeDaysSet[$date] = true;
        $prog = (float)$s['progress'];
        $totalProgress += $prog;
        
        // Topics (donut chart)
        if (!empty($s['title'])) {
            $words = array_filter(
                explode(' ', strtolower($s['title'])),
                fn($w) => strlen($w) > 2 && !in_array($w, $stopWords)
            );
            $topic = implode(' ', array_slice(array_values($words), 0, 2));
            if (empty($topic)) $topic = 'General';
            $topic = ucwords($topic);
            $topicCounts[$topic] = ($topicCounts[$topic] ?? 0) + 1;
        }

        // Progress over time
        if (!isset($progressMap[$date])) {
            $progressMap[$date] = ['total_progress' => 0, 'session_count' => 0];
        }
        $progressMap[$date]['total_progress'] += $prog;
        $progressMap[$date]['session_count']++;

        // Goal distribution
        $goal = !empty($s['session_goal']) ? $s['session_goal'] : 'general';
        $goalMap[$goal] = ($goalMap[$goal] ?? 0) + 1;

        // Milestones & Subjects
        if (!empty($s['context_data'])) {
            $cd = json_decode($s['context_data'], true);
            if ($cd) {
                $milestones = $cd['outline']['milestones'] ?? [];
                $totalMilestones += count($milestones);
                $mc = count(array_filter($milestones, fn($m) => $m['completed'] ?? false));
                $completedMilestones += $mc;

                $subjectKey = isset($cd['topic']) && trim($cd['topic']) !== '' ? ucwords(trim($cd['topic'])) : null;
                if ($subjectKey) {
                    if (!isset($subjectMap[$subjectKey])) {
                        $subjectMap[$subjectKey] = [
                            'topic' => $subjectKey, 'sessions' => 0, 'totalProgress' => 0,
                            'milestonesCompleted' => 0, 'milestonesTotal' => 0
                        ];
                    }
                    $subjectMap[$subjectKey]['sessions']++;
                    $subjectMap[$subjectKey]['totalProgress'] += $prog;
                    $subjectMap[$subjectKey]['milestonesCompleted'] += $mc;
                    $subjectMap[$subjectKey]['milestonesTotal'] += count($milestones);
                }
            }
        }
    }

    $avgProgress = $totalSessions > 0 ? $totalProgress / $totalSessions : 0;
    
    arsort($topicCounts);
    $topTopics = array_slice($topicCounts, 0, 8, true);

    $progressOverTime = [];
    foreach ($progressMap as $date => $data) {
        $progressOverTime[] = [
            'date' => $date,
            'avg_progress' => $data['total_progress'] / $data['session_count'],
            'session_count' => $data['session_count']
        ];
    }
    usort($progressOverTime, fn($a, $b) => strcmp($a['date'], $b['date']));

    $goalDistribution = [];
    foreach ($goalMap as $goal => $count) {
        $goalDistribution[] = ['goal' => $goal, 'count' => $count];
    }
    usort($goalDistribution, fn($a, $b) => $b['count'] - $a['count']);

    $subjectProgress = [];
    foreach ($subjectMap as $entry) {
        $ap = $entry['sessions'] > 0 ? round($entry['totalProgress'] / $entry['sessions']) : 0;
        $completionPct = $entry['milestonesTotal'] > 0
            ? round(($entry['milestonesCompleted'] / $entry['milestonesTotal']) * 100)
            : $ap;
        $subjectProgress[] = [
            'topic' => $entry['topic'], 'sessions' => $entry['sessions'],
            'avgProgress' => $ap, 'milestonesCompleted' => $entry['milestonesCompleted'],
            'milestonesTotal' => $entry['milestonesTotal'], 'completionPct' => $completionPct,
        ];
    }
    usort($subjectProgress, fn($a, $b) => $b['completionPct'] - $a['completionPct']);
    $subjectProgress = array_slice($subjectProgress, 0, 10);

    $recentSessions = array_map(function ($s) {
        return [
            'title' => $s['title'], 'progress' => round($s['progress'] ?? 0),
            'goal' => $s['session_goal'], 'date' => $s['created_at'],
        ];
    }, array_slice($sessions, 0, 10));

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
    $studyDates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $currentStreak = 0;
    $today = new DateTime('today');
    foreach ($studyDates as $dateStr) {
        $studyDate = new DateTime($dateStr);
        $diff = $today->diff($studyDate)->days;
        if ($diff === 0 || $diff === $currentStreak) {
            if ($diff > 0) $currentStreak++;
        } else break;
    }

    // -------------------------------------------------------------------------
    // 8. Trend indicators (previous period vs current)
    // -------------------------------------------------------------------------
    $trends = null;
    if ($prevStartDate && $prevEndDate) {
        $stmt = $pdo->prepare("
            SELECT title, progress, created_at
            FROM conversations
            WHERE user_id = ? AND created_at >= ? AND created_at < ?
        ");
        $stmt->execute([$user_id, $prevStartDate, $prevEndDate]);
        $prevSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pTotalSessions = count($prevSessions);
        $pTotalProgress = 0;
        $pActiveDaysSet = [];
        $prevTopicCounts = [];

        foreach ($prevSessions as $ps) {
            $date = substr($ps['created_at'], 0, 10);
            $pActiveDaysSet[$date] = true;
            $pTotalProgress += (float)$ps['progress'];

            if (!empty($ps['title'])) {
                $words = array_filter(explode(' ', strtolower($ps['title'])), fn($w) => strlen($w) > 2 && !in_array($w, $stopWords));
                $key = ucwords(implode(' ', array_slice(array_values($words), 0, 2))) ?: 'General';
                $prevTopicCounts[$key] = ($prevTopicCounts[$key] ?? 0) + 1;
            }
        }

        $trends = [
            'prevTotalSessions' => $pTotalSessions,
            'prevActiveDays'    => count($pActiveDaysSet),
            'prevAvgProgress'   => $pTotalSessions > 0 ? round($pTotalProgress / $pTotalSessions) : 0,
            'prevTopicsStudied' => count($prevTopicCounts),
        ];
    }

    // -------------------------------------------------------------------------
    // 9. Activity heatmap (last 365 days)
    // -------------------------------------------------------------------------
    $d365 = new DateTime();
    $d365->modify('-365 days');
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM conversations
        WHERE user_id = ? AND created_at >= ?
        GROUP BY DATE(created_at)
    ");
    $stmt->execute([$user_id, $d365->format('Y-m-d H:i:s')]);
    $heatmapRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $heatmap = [];
    foreach ($heatmapRaw as $row) {
        $heatmap[$row['date']] = (int)$row['count'];
    }

    // -------------------------------------------------------------------------
    // 10. Combine Quiz Stats
    // -------------------------------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT score, question_type, answered_at, question
        FROM recall_quizzes
        WHERE user_id = ? AND answered_at IS NOT NULL AND created_at >= ?
        ORDER BY answered_at DESC
    ");
    $stmt->execute([$user_id, $startDate]);
    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $qTotalScore = 0;
    $qTotalCount = count($quizzes);
    $qOverTimeMap = [];
    $qByTypeMap = [];
    
    foreach ($quizzes as $q) {
        $qTotalScore += (float)$q['score'];
        $date = substr($q['answered_at'], 0, 10);
        $type = $q['question_type'];
        
        if (!isset($qOverTimeMap[$date])) $qOverTimeMap[$date] = ['total_score' => 0, 'count' => 0];
        $qOverTimeMap[$date]['total_score'] += (float)$q['score'];
        $qOverTimeMap[$date]['count']++;
        
        if (!isset($qByTypeMap[$type])) $qByTypeMap[$type] = ['total_score' => 0, 'count' => 0];
        $qByTypeMap[$type]['total_score'] += (float)$q['score'];
        $qByTypeMap[$type]['count']++;
    }

    $qOverTime = [];
    foreach ($qOverTimeMap as $date => $qd) {
        $qOverTime[] = ['date' => $date, 'avg_score' => round(($qd['total_score'] / $qd['count']) * 100), 'count' => $qd['count']];
    }
    usort($qOverTime, fn($a, $b) => strcmp($a['date'], $b['date']));

    $qByType = [];
    foreach ($qByTypeMap as $type => $qd) {
        $qByType[] = ['type' => $type, 'avg_score' => round(($qd['total_score'] / $qd['count']) * 100), 'count' => $qd['count']];
    }
    usort($qByType, fn($a, $b) => $b['avg_score'] - $a['avg_score']);

    $recentQuizzes = array_map(function ($q) {
        return ['question' => $q['question'], 'score' => round($q['score'] * 100), 'question_type' => $q['question_type'], 'answered_at' => $q['answered_at']];
    }, array_slice($quizzes, 0, 10));

    $quizStats = [
        'overallAvg' => $qTotalCount > 0 ? round(($qTotalScore / $qTotalCount) * 100) : 0,
        'totalAnswered' => $qTotalCount,
        'scoreOverTime' => $qOverTime,
        'byType' => $qByType,
        'recentQuizzes' => $recentQuizzes
    ];

    // -------------------------------------------------------------------------
    // 11. Combine Pomodoro Stats
    // -------------------------------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT duration_minutes, completed, mode, started_at
        FROM pomodoro_sessions
        WHERE user_id = ? AND started_at >= ?
    ");
    $stmt->execute([$user_id, $startDate]);
    $pomodoros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pTotalMinutes = 0;
    $pTotalSessions = count($pomodoros);
    $pCompleted = 0;
    $pModeMap = [];
    $pOverTimeMap = [];

    foreach ($pomodoros as $p) {
        $pTotalMinutes += (int)$p['duration_minutes'];
        if ((int)$p['completed'] === 1) {
            $pCompleted++;
            $date = substr($p['started_at'], 0, 10);
            $pOverTimeMap[$date] = ($pOverTimeMap[$date] ?? 0) + (int)$p['duration_minutes'];
        }
        $mode = $p['mode'];
        $pModeMap[$mode] = ($pModeMap[$mode] ?? 0) + 1;
    }

    $pModeDist = [];
    foreach ($pModeMap as $mode => $count) {
        $pModeDist[] = ['mode' => $mode, 'count' => $count];
    }
    usort($pModeDist, fn($a, $b) => $b['count'] - $a['count']);

    $pOverTime = [];
    foreach ($pOverTimeMap as $date => $mins) {
        $pOverTime[] = ['date' => $date, 'total_minutes' => $mins];
    }
    usort($pOverTime, fn($a, $b) => strcmp($a['date'], $b['date']));

    $pomodoroStats = [
        'totalMinutes' => $pTotalMinutes,
        'totalSessions' => $pTotalSessions,
        'completedSessions' => $pCompleted,
        'completionRate' => $pTotalSessions > 0 ? round(($pCompleted / $pTotalSessions) * 100) : 0,
        'modeDistribution' => $pModeDist,
        'focusOverTime' => $pOverTime
    ];

    // -------------------------------------------------------------------------
    // Response
    // -------------------------------------------------------------------------
    echo json_encode([
        'success' => true,
        'period'  => $period,
        'stats'   => [
            'totalSessions'      => $totalSessions,
            'activeDays'         => count($activeDaysSet),
            'avgProgress'        => round($avgProgress),
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
