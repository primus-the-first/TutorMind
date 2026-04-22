<?php
/**
 * Active Recall Quiz API
 *
 * Actions:
 *   generate     — Analyse recent conversation messages and produce a quiz question
 *   grade        — Grade the student's answer and persist the result
 *   save_session — Record a completed pomodoro session (called by the timer)
 */

require_once __DIR__ . '/../includes/check_auth.php';
require_once __DIR__ . '/../includes/db_mysql.php';

header('Content-Type: application/json');

// --------------------------------------------------------------------------
// Config + DB
// --------------------------------------------------------------------------
$configFiles = [__DIR__ . '/../includes/config-sql.ini', __DIR__ . '/../includes/config.ini'];
$config = null;
foreach ($configFiles as $f) {
    if (file_exists($f)) {
        $parsed = parse_ini_file($f);
        if ($parsed !== false) { $config = $parsed; break; }
    }
}

$GEMINI_KEY = $config['QUIZ_API_KEY'] ?? $config['GEMINI_API_KEY'] ?? null;

try {
    $pdo     = getDbConnection();
    $user_id = (int) $_SESSION['user_id'];
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}

// --------------------------------------------------------------------------
// Router
// --------------------------------------------------------------------------
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'generate':     handleGenerate($pdo, $user_id, $body, $GEMINI_KEY); break;
    case 'grade':        handleGrade($pdo, $user_id, $body, $GEMINI_KEY);    break;
    case 'save_session': handleSaveSession($pdo, $user_id, $body);           break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}

// ==========================================================================
// GENERATE — produce a quiz question from recent conversation messages
// ==========================================================================
function handleGenerate($pdo, $user_id, $data, $geminiKey) {
    $conversation_id = isset($data['conversation_id']) ? (int)$data['conversation_id'] : 0;
    $mode            = in_array($data['mode'] ?? '', ['gentle','standard','challenge']) ? $data['mode'] : 'standard';
    $session_id      = isset($data['session_id']) ? (int)$data['session_id'] : null;

    if (!$conversation_id) {
        echo json_encode(['success' => false, 'skip' => true, 'reason' => 'no_conversation']);
        return;
    }

    // Verify ownership
    $stmt = $pdo->prepare("SELECT id FROM conversations WHERE id = ? AND user_id = ?");
    $stmt->execute([$conversation_id, $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        return;
    }

    // Fetch the last 8 AI messages (model role)
    $stmt = $pdo->prepare("
        SELECT content FROM messages
        WHERE conversation_id = ? AND role = 'model'
        ORDER BY created_at DESC
        LIMIT 8
    ");
    $stmt->execute([$conversation_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($rows) < 2) {
        echo json_encode(['success' => false, 'skip' => true, 'reason' => 'not_enough_messages']);
        return;
    }

    // Parse JSON message content and extract plain text
    $aiTexts = [];
    foreach (array_reverse($rows) as $raw) {
        $parts = json_decode($raw, true);
        if (is_array($parts)) {
            foreach ($parts as $part) {
                $text = $part['text'] ?? '';
                if ($text) { $aiTexts[] = strip_tags($text); }
            }
        } elseif (is_string($raw)) {
            $aiTexts[] = strip_tags($raw);
        }
    }
    $context = implode("\n\n---\n\n", array_slice($aiTexts, -6));

    // Fetch user profile for adaptive difficulty
    $stmt = $pdo->prepare("SELECT knowledge_level, education_level, field_of_study FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?? [];
    $knowledgeLevel  = $profile['knowledge_level']  ?? 'intermediate';
    $educationLevel  = $profile['education_level']  ?? 'not specified';
    $fieldOfStudy    = $profile['field_of_study']   ?? 'general';

    // Determine allowed question types per mode
    switch ($mode) {
        case 'gentle':
            $typeInstruction = 'Create a RECOGNITION question (multiple choice with 4 options, exactly 1 correct). Set question_type to "recognition".';
            break;
        case 'challenge':
            $typeInstruction = 'Create a FREE_RECALL or APPLICATION question requiring a written explanation or applying the concept to a new scenario. Set question_type to "free_recall" or "application" depending on which fits better.';
            break;
        default:
            $typeInstruction = 'Create a CUED RECALL question (e.g. "Name the three types of…", "What are the steps for…"). Set question_type to "cued".';
            break;
    }

    $prompt = <<<PROMPT
You are creating a single active-recall quiz question for a student who just completed a focused study session.

## What the student studied (recent AI tutor responses):
{$context}

## Student profile:
- Knowledge level: {$knowledgeLevel}
- Education level: {$educationLevel}
- Field of study:  {$fieldOfStudy}

## Task:
{$typeInstruction}

Rules:
1. Focus on the MOST IMPORTANT concept from the study context.
2. Keep the question conversational and encouraging — no exam-room formality.
3. For "recognition": supply exactly 4 options (1 correct, 3 plausible distractors). Place the correct answer randomly in the list.
4. For "cued": provide the ideal short answer in correct_answer.
5. For "free_recall"/"application": leave correct_answer null; put 3–5 key points expected in a good answer inside key_points.
6. context_snippet: 2–3 sentences summarising what was covered (shown to student after answering).

Return ONLY valid JSON — no markdown, no commentary:
{
  "question_type": "recognition|cued|free_recall|application",
  "question": "...",
  "options": ["A", "B", "C", "D"],
  "correct_answer": "...",
  "key_points": ["point 1", "point 2"],
  "context_snippet": "..."
}
PROMPT;

    $result = callGemini($geminiKey, $prompt, 12);

    if (!$result) {
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'AI service unavailable']);
        return;
    }

    // Parse AI response
    $aiData = json_decode($result, true);
    if (!$aiData || empty($aiData['question'])) {
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'Invalid AI response']);
        return;
    }

    $qType          = $aiData['question_type']  ?? 'cued';
    $question       = $aiData['question']       ?? '';
    $options        = $aiData['options']        ?? null;
    $correctAnswer  = $aiData['correct_answer'] ?? null;
    $keyPoints      = $aiData['key_points']     ?? null;
    $contextSnippet = $aiData['context_snippet'] ?? '';

    // Persist
    $stmt = $pdo->prepare("
        INSERT INTO recall_quizzes
            (session_id, user_id, conversation_id, question_type, question, options, correct_answer, key_points, context_snippet)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $session_id,
        $user_id,
        $conversation_id,
        $qType,
        $question,
        $options    ? json_encode($options)    : null,
        $correctAnswer,
        $keyPoints  ? json_encode($keyPoints)  : null,
        $contextSnippet,
    ]);
    $quizId = $pdo->lastInsertId();

    // Return — never expose correct_answer for non-recognition types
    $responseOptions = ($qType === 'recognition' && $options) ? $options : null;
    $responseCorrect = ($qType === 'recognition') ? $correctAnswer : null;

    echo json_encode([
        'success'       => true,
        'quiz_id'       => (int)$quizId,
        'question_type' => $qType,
        'question'      => $question,
        'options'       => $responseOptions,
        'correct_answer'=> $responseCorrect,
        'context_snippet' => $contextSnippet,
    ]);
}

// ==========================================================================
// GRADE — evaluate student's answer and persist
// ==========================================================================
function handleGrade($pdo, $user_id, $data, $geminiKey) {
    $quizId     = isset($data['quiz_id']) ? (int)$data['quiz_id'] : 0;
    $userAnswer = trim($data['user_answer'] ?? '');

    if (!$quizId || $userAnswer === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing quiz_id or user_answer']);
        return;
    }

    // Fetch quiz — verify ownership
    $stmt = $pdo->prepare("SELECT * FROM recall_quizzes WHERE id = ? AND user_id = ?");
    $stmt->execute([$quizId, $user_id]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quiz) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Quiz not found']);
        return;
    }

    $qType         = $quiz['question_type'];
    $question      = $quiz['question'];
    $correctAnswer = $quiz['correct_answer'];
    $keyPoints     = $quiz['key_points'] ? json_decode($quiz['key_points'], true) : [];
    $contextSnippet = $quiz['context_snippet'];

    // ---------- Fast-path for recognition (exact match) ----------
    if ($qType === 'recognition') {
        $isCorrect = (strtolower(trim($userAnswer)) === strtolower(trim($correctAnswer ?? '')));
        $score     = $isCorrect ? 1.0 : 0.0;
        $feedback  = $isCorrect
            ? 'Correct! Well done.'
            : "Not quite — the correct answer was: {$correctAnswer}.";

        updateQuizResult($pdo, $quizId, $userAnswer, $feedback, $score);

        echo json_encode([
            'success'        => true,
            'score'          => $score,
            'feedback'       => $feedback,
            'correct_answer' => $correctAnswer,
            'context_snippet'=> $contextSnippet,
        ]);
        return;
    }

    // ---------- AI grading for open-ended types ----------
    $keyPointsText = $keyPoints ? implode("\n- ", $keyPoints) : $correctAnswer ?? '(see context)';

    $prompt = <<<PROMPT
You are grading a student's active-recall answer.

QUESTION: {$question}
QUESTION TYPE: {$qType}
EXPECTED KEY POINTS:
- {$keyPointsText}

STUDENT'S ANSWER: {$userAnswer}

Grade on a 0.0–1.0 scale:
- 1.0 = Excellent — hits all key points accurately
- 0.75 = Good — covers most key points
- 0.5 = Partial — some relevant content, misses key points
- 0.25 = Weak — minimal relevant content
- 0.0 = Off-topic or blank

Return ONLY valid JSON:
{
  "score": 0.0,
  "feedback": "1–2 sentences of encouraging, specific feedback"
}
PROMPT;

    $result = callGemini($geminiKey, $prompt, 10);
    $gradeData = $result ? json_decode($result, true) : null;

    $score    = isset($gradeData['score'])    ? (float)$gradeData['score']    : 0.5;
    $feedback = isset($gradeData['feedback']) ? $gradeData['feedback']        : 'Answer recorded.';

    // Clamp score
    $score = max(0.0, min(1.0, $score));

    updateQuizResult($pdo, $quizId, $userAnswer, $feedback, $score);

    echo json_encode([
        'success'        => true,
        'score'          => $score,
        'feedback'       => $feedback,
        'correct_answer' => null,
        'context_snippet'=> $contextSnippet,
    ]);
}

// ==========================================================================
// SAVE SESSION — record a completed pomodoro session
// ==========================================================================
function handleSaveSession($pdo, $user_id, $data) {
    $conversation_id  = isset($data['conversation_id'])  ? (int)$data['conversation_id']  : null;
    $duration_minutes = isset($data['duration_minutes']) ? (int)$data['duration_minutes'] : 25;
    $mode             = in_array($data['mode'] ?? '', ['gentle','standard','challenge']) ? $data['mode'] : 'standard';
    $messages_count   = isset($data['messages_count'])   ? (int)$data['messages_count']   : 0;

    $stmt = $pdo->prepare("
        INSERT INTO pomodoro_sessions (user_id, conversation_id, duration_minutes, mode, completed, messages_count, ended_at)
        VALUES (?, ?, ?, ?, 1, ?, NOW())
    ");
    $stmt->execute([$user_id, $conversation_id ?: null, $duration_minutes, $mode, $messages_count]);
    $sessionId = $pdo->lastInsertId();

    echo json_encode(['success' => true, 'session_id' => (int)$sessionId]);
}

// ==========================================================================
// HELPERS
// ==========================================================================
function updateQuizResult($pdo, $quizId, $userAnswer, $feedback, $score) {
    $stmt = $pdo->prepare("
        UPDATE recall_quizzes
        SET user_answer = ?, ai_feedback = ?, score = ?, answered_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$userAnswer, $feedback, $score, $quizId]);
}

function callGemini($apiKey, $prompt, $timeoutSeconds = 10) {
    if (!$apiKey) return null;

    $url     = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}";
    $payload = json_encode([
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => [
            'responseMimeType' => 'application/json',
            'temperature'      => 0.6,
            'maxOutputTokens'  => 512,
        ],
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => $timeoutSeconds,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) return null;

    $decoded = json_decode($response, true);
    return $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
}
