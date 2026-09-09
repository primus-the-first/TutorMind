<?php
/**
 * DEV-ONLY test harness for the widget library (see
 * api/services/widget_library_service.php).
 *
 * Renders a hand-authored tm-ref block through the REAL formatResponse() +
 * resolveWidgetLibraryRefs() + tm-widgets.js pipeline, confirming a reference
 * resolves to the same rendered widget as the fully-inline version in
 * tm_widget_test.php.
 *
 * Open at: http://localhost/TutorMind/tm_widget_library_test.php
 * Safe to delete — reads widget_library only, writes nothing but usage_count.
 */
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/db_mysql.php';
require_once __DIR__ . '/api/services/response_formatter.php';
require_once __DIR__ . '/api/services/widget_library_service.php';

$pdo = getDbConnection();

// A hand-authored AI response that chose to reuse the seeded Dijkstra entry
// instead of authoring the graph/steps JSON itself.
$refResponse = <<<'MD'
We've covered this exact graph before, so let's reuse it.

```tm-ref
{"key": "dijkstra-basic-graph-a-to-e"}
```
MD;

// A second block referencing an unknown key, to confirm fail-soft behaviour
// (dropped silently, logged server-side, page still renders fine).
$badRefResponse = <<<'MD'
This one references a key that doesn't exist:

```tm-ref
{"key": "not-a-real-topic"}
```

The rest of this message should still render normally.
MD;

$resolvedHtml = resolveWidgetLibraryRefs(formatResponse($refResponse), $pdo);
$badResolvedHtml = resolveWidgetLibraryRefs(formatResponse($badRefResponse), $pdo);

$countStmt = $pdo->query("SELECT topic_key, usage_count FROM widget_library ORDER BY topic_key");
$counts = $countStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Widget Library Test</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Source+Sans+Pro:wght@400;600;700&family=Funnel+Display:wght@400;700&display=swap" rel="stylesheet">
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
        .harness-bar table { margin-top: 0.6rem; font-size: 0.8rem; color: var(--text-primary); border-collapse: collapse; }
        .harness-bar td { padding: 0.2rem 0.8rem 0.2rem 0; }
        .message { display: flex; gap: 0.8rem; align-items: flex-start; margin-bottom: 1rem; }
        .message-avatar {
            flex: none; width: 38px; height: 38px; display: grid; place-items: center;
            font-size: 1.15rem; border-radius: 10px; background: var(--bg-card); border: 1.5px solid var(--border);
        }
        .message-content {
            background: var(--bg-card); border: 1.5px solid var(--border); border-radius: 14px;
            padding: 1rem 1.2rem; color: var(--text-primary); line-height: 1.55; flex: 1; min-width: 0;
        }
        .message-content p { margin: 0 0 0.7rem; }
    </style>
</head>
<body>
    <div class="harness-bar">
        <h1>🧪 Widget library test</h1>
        <div class="note">Real <code>resolveWidgetLibraryRefs(formatResponse(...), $pdo)</code> + real <code>tm-widgets.js</code>. First bubble resolves a valid key; second bubble references an unknown key and should fail soft (block disappears, rest of the message renders).</div>
        <table>
            <?php foreach ($counts as $row): ?>
            <tr><td><?= htmlspecialchars($row['topic_key']) ?></td><td>usage_count = <?= (int)$row['usage_count'] ?></td></tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div id="chat">
        <div class="message ai">
            <div class="message-avatar">🤖</div>
            <div class="message-content" id="refBubble"><?= $resolvedHtml ?></div>
        </div>
        <div class="message ai">
            <div class="message-avatar">🤖</div>
            <div class="message-content" id="badRefBubble"><?= $badResolvedHtml ?></div>
        </div>
    </div>

    <script src="assets/js/tm-widgets.js?v=<?= filemtime('assets/js/tm-widgets.js') ?>"></script>
    <script>
        TMWidgets.init({
            onReply: function () {},
            onReveal: function (el) {
                if (window.MathJax && MathJax.typesetPromise) MathJax.typesetPromise([el]).catch(function(){});
            }
        });
        TMWidgets.render(document.getElementById('refBubble'));
        TMWidgets.render(document.getElementById('badRefBubble'));
    </script>
</body>
</html>
