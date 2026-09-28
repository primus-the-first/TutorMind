<?php
/**
 * Analytics API — data for dashboard.php ("Your learning").
 *
 * GET ?period=7days|30days|90days|all  (default 30days)
 *
 * The period only scopes `summary`. Everything else describes "right now":
 * where to pick up, what to review, the streak, subjects and recall.
 *
 * Honesty notes:
 * - Topic is context_data.topic (the tutor's detected topic), never title words.
 * - Milestones are marked done when the tutor's reply covers them
 *   (detectMilestoneCompletion), so they mean "covered", not "mastered".
 * - Quiz scores are the only direct evidence of learning; conversations.progress
 *   (message count ÷ a target) is deliberately not reported.
 * - Every date is taken from MySQL's clock so it matches the stored timestamps
 *   (PHP and MySQL time zones differ).
 */

require_once __DIR__ . '/../includes/check_auth.php';
require_once __DIR__ . '/../includes/db_mysql.php';

header('Content-Type: application/json');

try {
    $pdo = getDbConnection();
    $user_id = $_SESSION['user_id'];

    [$dbToday, $dbNow] = $pdo->query("SELECT CURDATE(), NOW()")->fetch(PDO::FETCH_NUM);

    $periods = ['7days' => 7, '30days' => 30, '90days' => 90, 'all' => null];
    $period = array_key_exists($_GET['period'] ?? '', $periods) ? $_GET['period'] : '30days';
    $intervalDays = $periods[$period];   // null = all time (no ?? here: it would turn null into a default)
    $startDate = '1970-01-01 00:00:00';
    $prevStartDate = null;
    if ($intervalDays !== null) {
        $startDate = (new DateTime($dbNow))->modify("-$intervalDays days")->format('Y-m-d H:i:s');
        $prevStartDate = (new DateTime($dbNow))->modify('-' . ($intervalDays * 2) . ' days')->format('Y-m-d H:i:s');
    }

    // -------------------------------------------------------------------------
    // Summary for the period (+ the period before, for "6 more than before")
    // -------------------------------------------------------------------------
    $countSessions = $pdo->prepare("
        SELECT COUNT(*) AS sessions, COUNT(DISTINCT DATE(created_at)) AS days
        FROM conversations
        WHERE user_id = ? AND created_at >= ? AND created_at < ?
    ");
    $countSessions->execute([$user_id, $startDate, '9999-12-31']);
    $cur = $countSessions->fetch(PDO::FETCH_ASSOC);

    $prev = null;
    if ($prevStartDate) {
        $countSessions->execute([$user_id, $prevStartDate, $startDate]);
        $p = $countSessions->fetch(PDO::FETCH_ASSOC);
        $prev = ['sessions' => (int)$p['sessions'], 'activeDays' => (int)$p['days']];
    }

    // Focus time counts finished Pomodoros only (an abandoned one's duration is its plan, not time spent).
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(duration_minutes), 0) AS minutes, COUNT(*) AS done
        FROM pomodoro_sessions
        WHERE user_id = ? AND completed = 1 AND started_at >= ?
    ");
    $stmt->execute([$user_id, $startDate]);
    $focus = $stmt->fetch(PDO::FETCH_ASSOC);

    $summary = [
        'sessions'      => (int)$cur['sessions'],
        'activeDays'    => (int)$cur['days'],
        'focusMinutes'  => (int)$focus['minutes'],
        'pomodorosDone' => (int)$focus['done'],
        'prev'          => $prev,
    ];

    // -------------------------------------------------------------------------
    // Streak: consecutive study days ending today — or yesterday, since a
    // streak survives until today is over.
    // -------------------------------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT DISTINCT DATE(created_at) AS study_date
        FROM conversations
        WHERE user_id = ?
        ORDER BY study_date DESC
        LIMIT 365
    ");
    $stmt->execute([$user_id]);
    $studyDates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $streakDays = 0;
    $expected = new DateTime($dbToday);
    if (!empty($studyDates) && $studyDates[0] !== $expected->format('Y-m-d')) {
        $expected->modify('-1 day');
    }
    foreach ($studyDates as $dateStr) {
        if ($dateStr !== $expected->format('Y-m-d')) break;
        $streakDays++;
        $expected->modify('-1 day');
    }

    // Last 7 days, oldest first; today is null until studied (it is still open).
    $studied = array_flip($studyDates);
    $last7 = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = (new DateTime($dbToday))->modify("-$i day")->format('Y-m-d');
        $last7[] = isset($studied[$d]) ? true : ($i === 0 ? null : false);
    }

    // Sessions per day for the 12-week grid (13 columns incl. this week).
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) AS d, COUNT(*) AS n
        FROM conversations
        WHERE user_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 97 DAY)
        GROUP BY DATE(created_at)
    ");
    $stmt->execute([$user_id]);
    $heatmap = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $heatmap[$row['d']] = (int)$row['n'];
    }

    // -------------------------------------------------------------------------
    // Sessions: continue, recent, subjects
    // -------------------------------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT id, title, context_data, COALESCE(updated_at, created_at) AS updated_at
        FROM conversations
        WHERE user_id = ?
        ORDER BY updated_at DESC
        LIMIT 200
    ");
    $stmt->execute([$user_id]);
    $convs = array_map(function ($c) {
        $cd = !empty($c['context_data']) ? json_decode($c['context_data'], true) : null;
        $ms = is_array($cd['outline']['milestones'] ?? null) ? $cd['outline']['milestones'] : [];
        $next = null;
        foreach ($ms as $m) {
            if (empty($m['completed'])) { $next = $m['title'] ?? null; break; }
        }
        $topic = trim((string)($cd['topic'] ?? ''));
        return [
            'id'              => (int)$c['id'],
            'title'           => $c['title'] ?: 'Untitled session',
            'topic'           => $topic !== '' ? ucwords($topic) : null,
            'updatedAt'       => $c['updated_at'],
            'milestonesDone'  => count(array_filter($ms, fn($m) => !empty($m['completed']))),
            'milestonesTotal' => count($ms),
            'nextMilestone'   => $next,
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    $convById = array_column($convs, null, 'id');
    $unfinished = fn($c) => $c['milestonesTotal'] === 0 || $c['milestonesDone'] < $c['milestonesTotal'];

    // Pick up where you left off: the newest unfinished session among the last 10.
    $continue = null;
    foreach (array_slice($convs, 0, 10) as $c) {
        if ($unfinished($c)) { $continue = $c; break; }
    }
    $continue = $continue ?? ($convs[0] ?? null);
    $recent = array_slice(array_values(array_filter($convs, fn($c) => $c['id'] !== ($continue['id'] ?? null))), 0, 3);

    // Subjects, most recent first. The newest session on a topic carries its current outline.
    $subjects = [];
    foreach ($convs as $c) {
        if (!$c['topic']) continue;
        $key = strtolower($c['topic']);
        if (!isset($subjects[$key])) {
            $subjects[$key] = [
                'topic' => $c['topic'], 'conversationId' => $c['id'], 'sessions' => 0,
                'milestonesDone' => $c['milestonesDone'], 'milestonesTotal' => $c['milestonesTotal'],
                'lastStudied' => $c['updatedAt'],
            ];
        }
        $subjects[$key]['sessions']++;
    }
    $subjects = array_values($subjects);

    // -------------------------------------------------------------------------
    // Worth another look: missed quiz questions (last 14 days), then subjects
    // gone quiet (7+ days, unfinished). The client drops ones this browser
    // already retried and shows the first three.
    // -------------------------------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT id, conversation_id, question, score, answered_at
        FROM recall_quizzes
        WHERE user_id = ? AND answered_at IS NOT NULL AND score < 0.7
          AND answered_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
        ORDER BY answered_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $review = [];
    $seenQuestions = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $q) {
        $k = strtolower(trim($q['question']));
        if (isset($seenQuestions[$k])) continue;
        $seenQuestions[$k] = true;
        // Null when its conversation is gone (deleted; no FK) — the client then opens a fresh chat.
        $conv = $convById[(int)$q['conversation_id']] ?? null;
        $review[] = [
            'kind' => 'quiz', 'quizId' => (int)$q['id'],
            'conversationId' => $conv['id'] ?? null,
            'topic' => $conv['topic'] ?? null, 'question' => $q['question'],
            'score' => (int)round($q['score'] * 100), 'answeredAt' => $q['answered_at'],
        ];
        if (count($review) >= 5) break;
    }
    $quiet = array_filter($subjects, fn($s) =>
        (strtotime($dbNow) - strtotime($s['lastStudied'])) >= 7 * 86400
        && ($s['milestonesTotal'] === 0 || $s['milestonesDone'] < $s['milestonesTotal']));
    foreach (array_slice($quiet, 0, 2) as $s) {
        $review[] = ['kind' => 'quiet'] + $s;
    }

    // -------------------------------------------------------------------------
    // How well it sticks: quiz results by question type (last 90 days), weakest first
    // -------------------------------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT question_type, ROUND(AVG(score) * 100) AS avg_score, COUNT(*) AS n
        FROM recall_quizzes
        WHERE user_id = ? AND answered_at IS NOT NULL AND answered_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        GROUP BY question_type
        ORDER BY avg_score ASC
    ");
    $stmt->execute([$user_id]);
    $recallLabels = [
        'free_recall' => 'Explaining from memory', 'application' => 'Applying it to a new problem',
        'cued' => 'Recalling with a hint', 'recognition' => 'Picking the right answer',
    ];
    $recall = array_map(fn($r) => [
        'type' => $r['question_type'], 'label' => $recallLabels[$r['question_type']] ?? ucfirst($r['question_type']),
        'avg' => (int)$r['avg_score'], 'count' => (int)$r['n'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));

    echo json_encode([
        'success'  => true,
        'period'   => $period,
        'now'      => $dbNow,
        'today'    => $dbToday,
        'summary'  => $summary,
        'continue' => $continue,
        'recent'   => $recent,
        'review'   => $review,
        'streak'   => ['days' => $streakDays, 'studiedToday' => isset($studied[$dbToday]), 'last7' => $last7],
        'heatmap'  => $heatmap,
        'subjects' => array_slice($subjects, 0, 9),
        'recall'   => $recall,
    ]);

} catch (Exception $e) {
    error_log("Analytics API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not load analytics.']);
}
