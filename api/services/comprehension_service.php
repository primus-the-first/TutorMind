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
    foreach (['config-sql.ini', 'config.ini'] as $configFile) {
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
 * Calculate overall progress from milestones and comprehension.
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

    // Weighted combination: 70% milestones, 20% comprehension, 10% engagement
    $hybridProgress =
        ($milestoneProgress * 0.70) +
        ($comprehensionScore * 100 * 0.20) +
        ($engagementScore * 100 * 0.10);

    return min(100, max(0, intval($hybridProgress)));
}
