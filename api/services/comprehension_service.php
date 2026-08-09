<?php
/**
 * Comprehension Service
 * Analyzes user messages for comprehension signals and calculates hybrid progress.
 */

function analyzeComprehension($userMessage)
{
    $lowerMessage = strtolower($userMessage);

    // --- LAYER 1: Explicit regex signals (fast, zero API cost) ---
    $positivePatterns = [
        '/\bi (get|got|understand|see)\b/'    => 0.10,
        '/\bmakes sense\b/'                    => 0.12,
        '/\bah+\b.*\bok\b/'                    => 0.08,
        '/\bthat\'s? (clear|helpful)\b/'       => 0.10,
        '/\bthank(s| you)\b/'                  => 0.05,
        '/\bnow i (know|understand)\b/'        => 0.15,
        '/\bperfect\b/'                        => 0.08,
        '/\bi can (see|follow)\b/'             => 0.10,
        '/\bgot it\b/'                         => 0.12,
        '/\bthat helps\b/'                     => 0.10,
    ];

    $negativePatterns = [
        '/\bi (don\'t|do not) (get|understand)\b/' => -0.12,
        '/\bconfus(ed|ing)\b/'                      => -0.10,
        '/\bwhat (do you mean|does that mean)\b/'   => -0.05,
        '/\bcan you explain (again|more)\b/'        => -0.08,
        '/\bi\'m (lost|stuck)\b/'                   => -0.10,
        '/\bstill (don\'t|unclear)\b/'              => -0.12,
        '/\bhuh\??\b/'                              => -0.05,
        '/\bwait,? what\b/'                         => -0.08,
        '/\bnot (following|sure)\b/'                => -0.08,
        '/\bcan you (repeat|rephrase|simplify)\b/'  => -0.10,
    ];

    $delta = 0.0;
    $hasExplicitSignal = false;

    foreach ($positivePatterns as $pattern => $score) {
        if (preg_match($pattern, $lowerMessage)) {
            $delta += $score;
            $hasExplicitSignal = true;
        }
    }

    foreach ($negativePatterns as $pattern => $score) {
        if (preg_match($pattern, $lowerMessage)) {
            $delta += $score;
            $hasExplicitSignal = true;
        }
    }

    // --- LAYER 2: AI-assisted detection for ambiguous messages ---
    // Only call AI when:
    // 1. No explicit signal was detected by regex
    // 2. Message is short (1-15 words) — short replies are often ambiguous
    // 3. Message doesn't look like a new question (no question mark at end)
    $wordCount = str_word_count($lowerMessage);
    $isAmbiguous = !$hasExplicitSignal
        && $wordCount <= 15
        && substr(trim($lowerMessage), -1) !== '?';

    if ($isAmbiguous) {
        try {
            $delta += aiComprehensionScore($userMessage);
        } catch (Exception $e) {
            error_log("AI comprehension scoring failed: " . $e->getMessage());
            // Fail silently — regex result (0.0) stands
        }
    }

    return max(-0.15, min(0.15, $delta));
}

/**
 * Use AI to detect subtle comprehension signals in ambiguous short messages.
 * Called only when regex finds no explicit signal.
 *
 * @param string $message The user's message
 * @return float Score between -0.15 and 0.15
 */
function aiComprehensionScore($message) {
    // Load API key
    $config = null;
    foreach ([__DIR__ . '/../../includes/config-sql.ini', __DIR__ . '/../../includes/config.ini'] as $configFile) {
        if (file_exists($configFile)) {
            $config = parse_ini_file($configFile);
            if ($config !== false) break;
        }
    }
    if (!$config || empty($config['GEMINI_API_KEY'])) {
        return 0.0;
    }

    $prompt = <<<EOT
A student just sent this short reply to their AI tutor: "{$message}"

Does this reply suggest the student understood the explanation, is confused, or is neutral/unclear?

Respond ONLY with a JSON object in this exact format:
{"signal": "understood"|"confused"|"neutral", "confidence": 0.0-1.0, "reasoning": "one sentence"}

Examples:
- "okay..." → {"signal": "confused", "confidence": 0.7, "reasoning": "Trailing ellipsis suggests passive disengagement or uncertainty"}
- "lol okay" → {"signal": "neutral", "confidence": 0.5, "reasoning": "Casual acknowledgment without clear comprehension signal"}
- "ohhhh" → {"signal": "understood", "confidence": 0.8, "reasoning": "Elongated expression of realization"}
- "right" → {"signal": "neutral", "confidence": 0.4, "reasoning": "Ambiguous acknowledgment, could mean understood or just heard"}
EOT;

    $payload = json_encode([
        "contents" => [["parts" => [["text" => $prompt]]]],
        "generationConfig" => [
            "responseMimeType" => "application/json",
            "temperature" => 0.1,
            "maxOutputTokens" => 100
        ]
    ]);

    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key="
        . $config['GEMINI_API_KEY'];

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 5  // Hard 5-second timeout — don't slow down response
    ]);

    $response = curl_exec($ch);
    $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpStatus !== 200) return 0.0;

    $data = json_decode($response, true);
    $jsonText = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if (!$jsonText) return 0.0;

    $result = json_decode($jsonText, true);
    if (!$result || !isset($result['signal'], $result['confidence'])) return 0.0;

    // Convert signal + confidence into a delta score
    $confidence = (float) $result['confidence'];
    switch ($result['signal']) {
        case 'understood': return  0.10 * $confidence;
        case 'confused':   return -0.10 * $confidence;
        default:           return  0.0;
    }
}

/**
 * Detect which of the three learning contacts have been made across student messages.
 *
 * Contact 1 – Analogy : student maps new concept onto something familiar
 * Contact 2 – Build   : student attempts or constructs something (code, steps, example)
 * Contact 3 – Predict : student reasons about a novel scenario or consequence
 *
 * When an analogy is detected, the student's message is captured verbatim in
 * 'analogy_text' so the tutor can anchor on the learner's own mental model.
 * The most recent analogy wins — a learner offering a new model replaces the old one.
 *
 * @param array $messages Gemini-format chat history: [['role'=>..., 'parts'=>[['text'=>...]]], ...]
 * @return array ['analogy'=>bool, 'build'=>bool, 'predict'=>bool, 'missing'=>string[], 'analogy_text'=>?string]
 */
function detectContactState($messages)
{
    $analogyPatterns = [
        "/\bit'?s like\b/",
        '/\breminds me of\b/',
        '/\bsimilar to\b/',
        '/\bso basically\b/',
        '/\bthink of it as\b/',
        '/\bkind of like\b/',
        '/\bjust like\b/',
        '/\bsame as when\b/',
        '/\bas an analogy\b/',
        "/\b(i'?ll|i will|let me) use .{2,80} as (an |the |my )?(example|analogy)\b/",
        // Broader natural phrasings that don't use a canned "like"/"reminds me" cue —
        // e.g. "The closest would be how traffic wardens control traffic when..."
        '/\bclosest (?:\w+ )?(would be|is|comes? to)\b/',
        '/\banalogous to\b/',
        '/\breminiscent of\b/',
        '/\bsounds like\b/',
        '/\bmakes? me think of\b/',
        '/\b(a bit|a lot|somewhat) like\b/',
        '/\bakin to\b/',
        '/\bin the same way (as|that)\b/',
        "/\bit'?s (basically|essentially|pretty much|sort of|kind of)\b/",
        "/\bthat'?s (basically|essentially|pretty much|like)\b/",
    ];
    $buildPatterns = [
        '/\bi tried\b/',
        '/\bi built\b/',
        '/\bi made\b/',
        '/\bi wrote\b/',
        "/\bhere'?s my\b/",
        '/\blet me try\b/',
    ];
    $predictPatterns = [
        '/\bi think it would\b/',
        '/\bi predict\b/',
        '/\bso then it should\b/',
        '/\bthat means\b/',
        '/\btherefore\b/',
    ];

    $analogy = false;
    $build   = false;
    $predict = false;
    $analogyText = null;

    foreach ($messages as $message) {
        if (($message['role'] ?? '') !== 'user') {
            continue;
        }

        $text = '';
        foreach ($message['parts'] ?? [] as $part) {
            if (isset($part['text'])) {
                $text .= ' ' . $part['text'];
            }
        }
        $lower = strtolower($text);

        // Keep scanning even after the first hit so the latest analogy wins
        foreach ($analogyPatterns as $p) {
            if (preg_match($p, $lower)) {
                $analogy = true;
                $snippet = trim($text);
                if (mb_strlen($snippet) > 400) {
                    $snippet = mb_substr($snippet, 0, 400) . '…';
                }
                $analogyText = $snippet;
                break;
            }
        }
        if (!$build) {
            // Code block presence counts as a build contact
            if (strpos($text, '```') !== false) {
                $build = true;
            } else {
                foreach ($buildPatterns as $p) {
                    if (preg_match($p, $lower)) { $build = true; break; }
                }
            }
        }
        if (!$predict) {
            foreach ($predictPatterns as $p) {
                if (preg_match($p, $lower)) { $predict = true; break; }
            }
        }
    }

    $missing = [];
    if (!$analogy) $missing[] = 'analogy';
    if (!$build)   $missing[] = 'build';
    if (!$predict) $missing[] = 'predict';

    return [
        'analogy'      => $analogy,
        'build'        => $build,
        'predict'      => $predict,
        'missing'      => $missing,
        'analogy_text' => $analogyText,
    ];
}

/**
 * Calculate overall progress from milestones, comprehension, engagement, and contact completion.
 *
 * @param array $contextData The session context data
 * @return int Progress percentage (0-100)
 */
function calculateHybridProgress($contextData)
{
    $milestones = $contextData['outline']['milestones'] ?? [];
    $comprehensionScore = $contextData['comprehensionScore'] ?? 0.5;
    $messageCount = $contextData['messageCount'] ?? 0;

    // If no milestones, use message-based progress
    if (empty($milestones)) {
        return min(100, intval(($messageCount / 10) * 100));
    }

    // Calculate milestone completion
    $completedCount = count(array_filter($milestones, fn($m) => $m['completed'] ?? false));
    $totalCount = count($milestones);
    $milestoneProgress = ($completedCount / $totalCount) * 100;

    // Calculate engagement score (capped at 1.0)
    $engagementScore = min(1.0, $messageCount / 10);

    // Calculate three-contact completion (0.0–1.0)
    $contactState = $contextData['contactState'] ?? null;
    $contactCompletion = 0.0;
    if ($contactState !== null) {
        $contactsMade = (int)($contactState['analogy'] ?? false)
                      + (int)($contactState['build']   ?? false)
                      + (int)($contactState['predict']  ?? false);
        $contactCompletion = $contactsMade / 3;
    }

    // Weighted combination: 55% milestones + 15% comprehension + 10% engagement + 20% contact
    $hybridProgress =
        ($milestoneProgress   * 0.55) +
        ($comprehensionScore  * 100 * 0.15) +
        ($engagementScore     * 100 * 0.10) +
        ($contactCompletion   * 100 * 0.20);

    return min(100, max(0, intval($hybridProgress)));
}
