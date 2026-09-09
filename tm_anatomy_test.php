<?php
/**
 * DEV-ONLY live test: sends a real human-anatomy question through the REAL
 * system prompt (buildSystemPrompt(), incl. the new Science discipline
 * guidance and the reusable widget library list) to the REAL Gemini API, then
 * runs the raw response through the REAL formatResponse() +
 * resolveWidgetLibraryRefs() + tm-widgets.js pipeline.
 *
 * Unlike tm_widget_test.php (a hand-authored response used to test rendering),
 * this is unscripted — it tests whether the prompt guidance actually gets the
 * model to reach for tm-graph / tm-steps visuals on its own for Science
 * content, not just Math/Programming.
 *
 * Open at: http://localhost/TutorMind/tm_anatomy_test.php
 * Safe to delete — makes one live Gemini call, writes nothing to the DB
 * except widget_library.usage_count if the model references the library.
 */
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/db_mysql.php';
require_once __DIR__ . '/api/services/tutor_service.php';
require_once __DIR__ . '/api/services/ai_service.php';
require_once __DIR__ . '/api/services/response_formatter.php';
require_once __DIR__ . '/api/services/image_service.php';
require_once __DIR__ . '/api/services/widget_library_service.php';

$pdo = getDbConnection();

$configFiles = [__DIR__ . '/includes/config-sql.ini', __DIR__ . '/includes/config.ini'];
$config = null;
foreach ($configFiles as $f) {
    if (file_exists($f)) {
        $parsed = parse_ini_file($f);
        if ($parsed !== false) { $config = $parsed; break; }
    }
}
$apiKey = $config['GEMINI_API_KEY'] ?? null;
if (!$apiKey) {
    die('No GEMINI_API_KEY found in config-sql.ini or config.ini.');
}

$question = $_GET['q'] ?? "Can you explain how blood flows through the heart and lungs?";

$widgetLibraryEntries = [];
try {
    $widgetLibraryEntries = $pdo->query("SELECT topic_key, title FROM widget_library")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log("widget_library: entry list fetch failed: " . $e->getMessage());
}

// No prior contact state — a fresh question, same as a brand-new conversation.
$systemPrompt = buildSystemPrompt('Understand', '', null, $widgetLibraryEntries);

$payload = json_encode([
    "contents" => [
        ["role" => "user", "parts" => [["text" => $question]]]
    ],
    "system_instruction" => [
        "role" => "system",
        "parts" => [["text" => $systemPrompt]]
    ],
    "generationConfig" => [
        "maxOutputTokens" => 8192,
        "temperature" => 0.7,
        "topP" => 0.95,
        "topK" => 40
    ]
]);

$error = null;
$rawText = '';
try {
    $responseData = callGeminiAPI($payload, $apiKey);
    $parts = $responseData['candidates'][0]['content']['parts'] ?? [];
    foreach ($parts as $part) {
        if (isset($part['text'])) $rawText .= $part['text'];
    }
    if ($rawText === '') $error = 'Empty response from Gemini — check the raw response data below.';
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$formattedHtml = $rawText !== '' ? resolveWidgetLibraryRefs(resolveImageMarkers(formatResponse($rawText)), $pdo) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anatomy Live Test</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Source+Sans+Pro:wght@400;600;700&family=Funnel+Display:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <link rel="stylesheet" href="assets/css/ui-overhaul.css?v=<?= filemtime('assets/css/ui-overhaul.css') ?>">
    <link rel="stylesheet" href="assets/css/tm-widgets.css?v=<?= filemtime('assets/css/tm-widgets.css') ?>">
    <style>
        /* ui-overhaul.css sets body { overflow: hidden } for the live chat app's
           fixed-viewport layout; this harness needs normal body scroll restored. */
        body { max-width: 820px; margin: 0 auto; padding: 2rem 1rem 5rem; background: var(--bg-main); overflow-y: auto; }
        .harness-bar {
            margin-bottom: 1.5rem; padding: 0.9rem 1.1rem;
            border: 1.5px dashed var(--border); border-radius: 12px; background: var(--bg-card);
        }
        .harness-bar h1 { font-family: "Funnel Display", sans-serif; font-size: 1.1rem; margin: 0 0 0.4rem; color: var(--text-primary); }
        .harness-bar .note { font-size: 0.8rem; color: var(--text-secondary); }
        .harness-bar form { margin-top: 0.7rem; display: flex; gap: 0.5rem; }
        .harness-bar input[type=text] { flex: 1; padding: 0.5rem 0.7rem; border: 1.5px solid var(--border); border-radius: 8px; font: inherit; }
        .harness-bar button {
            font: inherit; font-size: 0.85rem; padding: 0.5rem 1rem;
            border: 1.5px solid var(--primary); border-radius: 8px; background: var(--primary);
            color: #fff; font-weight: 600; cursor: pointer;
        }
        .message { display: flex; gap: 0.8rem; align-items: flex-start; margin-bottom: 1rem; }
        .message-avatar {
            flex: none; width: 38px; height: 38px; display: grid; place-items: center;
            font-size: 1.15rem; border-radius: 10px; background: var(--bg-card); border: 1.5px solid var(--border);
        }
        .message-content {
            background: var(--bg-card); border: 1.5px solid var(--border); border-radius: 14px;
            padding: 1rem 1.2rem; color: var(--text-primary); line-height: 1.55; flex: 1; min-width: 0;
        }
        .message-content pre { background: #1e1e2e; color: #e6e1ff; padding: 0.8rem 1rem; border-radius: 8px; overflow-x: auto; font-size: 0.85rem; }
        .message-content p { margin: 0 0 0.7rem; }
        .raw-toggle { font-size: 0.8rem; color: var(--text-secondary); cursor: pointer; margin: 0.5rem 0; }
        .raw-text { white-space: pre-wrap; font-family: monospace; font-size: 0.78rem; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 0.8rem; display: none; }
        .error-box { color: var(--tm-bad); background: var(--tm-bad-bg); border: 1.5px solid var(--tm-bad); border-radius: 10px; padding: 1rem; }
    </style>
</head>
<body>
    <div class="harness-bar">
        <h1>🧪 Live anatomy test — real Gemini call, real prompt, real widget pipeline</h1>
        <div class="note">Tests whether the Science discipline guidance actually gets the model to reach for <code>tm-graph</code> / <code>tm-steps</code> visuals unscripted — this response is NOT hand-authored.</div>
        <form method="get">
            <input type="text" name="q" value="<?= htmlspecialchars($question) ?>">
            <button type="submit">Ask</button>
        </form>
    </div>

    <div id="chat">
        <div class="message user">
            <div class="message-avatar">👤</div>
            <div class="message-content"><?= htmlspecialchars($question) ?></div>
        </div>
        <?php if ($error): ?>
        <div class="error-box"><strong>Error:</strong> <?= htmlspecialchars($error) ?></div>
        <?php else: ?>
        <div class="message ai">
            <div class="message-avatar">🤖</div>
            <div class="message-content" id="aiBubble"><?= $formattedHtml ?></div>
        </div>
        <div class="raw-toggle" onclick="document.getElementById('rawText').style.display = document.getElementById('rawText').style.display === 'block' ? 'none' : 'block';">▸ Show raw model output (before formatting)</div>
        <div class="raw-text" id="rawText"><?= htmlspecialchars($rawText) ?></div>
        <?php endif; ?>
    </div>

    <script src="assets/js/tm-widgets.js?v=<?= filemtime('assets/js/tm-widgets.js') ?>"></script>
    <script>
        TMWidgets.init({
            onReply: function () {},
            onReveal: function (el) {
                if (window.MathJax && MathJax.typesetPromise) MathJax.typesetPromise([el]).catch(function(){});
            }
        });
        var bubble = document.getElementById('aiBubble');
        if (bubble) {
            TMWidgets.render(bubble);
            if (window.hljs) bubble.querySelectorAll('pre code').forEach(function (b) { hljs.highlightElement(b); });
        }
    </script>
</body>
</html>
