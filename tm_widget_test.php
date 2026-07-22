<?php
/**
 * DEV-ONLY test harness for the interactive widgets.
 *
 * Renders a full Dijkstra's-algorithm tutor response through the REAL
 * formatResponse() pipeline and the REAL tm-widgets.js module + CSS, so the
 * widgets can be exercised without depending on the live AI model emitting them.
 *
 * Open at: http://localhost/TutorMind/tm_widget_test.php
 * Safe to delete — it touches no database, session, or secrets.
 */
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/api/services/response_formatter.php';

// A hand-authored AI response exactly as the model is instructed to produce it:
// prose interleaved with tm-* fenced blocks. This is the contract under test.
$aiResponse = <<<'MD'
Great choice — Dijkstra's algorithm is the backbone of every GPS route and network router. Let's build it up together.

The one-line idea: **from the start, always expand the closest unvisited node, and keep improving your best-known distance to everything else.**

```tm-chips
{"q": "Before we dig in — what does 'always take the cheapest route so far' remind you of?", "options": ["A GPS finding the fastest way home", "Water always flowing downhill", "Not sure — show me"]}
```

Here's the graph we'll work with (numbers are edge costs):

```
A --4-- B      A--1--C      C--2--B
C --5-- D      B--1--D      D--3--E
```

First, the single most important rule of the algorithm:

```tm-check
{"q": "At each step, Dijkstra expands which node next?", "options": ["The node added most recently", "The unvisited node with the smallest known distance", "The node with the most edges"], "answer": 1, "explain": ["That would be depth-first search, not Dijkstra — it ignores cost.", "Correct — greedily taking the closest unvisited node is exactly why Dijkstra finds the true shortest path.", "Edge count does not matter; total path cost does."]}
```

Now let's actually run it from A. Try to predict each update before you reveal it:

```tm-steps
{"q": "Shortest path from A to E", "steps": ["Start: dist(A)=0, everything else infinity. Visit A, relax its edges: C becomes 1, B becomes 4.", "Smallest unvisited is C (1). Relax from C: B via C is 1+2=3 (better than 4, so B becomes 3); D via C is 1+5=6.", "Smallest unvisited is B (3). Relax from B: D via B is 3+1=4 (better than 6, so D becomes 4).", "Smallest unvisited is D (4). Relax from D: E is 4+3=7.", "Visit E (7). Done — shortest A to E is 7, via A - C - B - D - E."], "predict": true}
```

Notice the key moment in step 2 and 3:

```tm-check
{"q": "We first reached B with distance 4. Then we found a path A to C to B costing 3. What does Dijkstra do?", "options": ["Keep 4, since B was already reached", "Update B's distance to 3", "Store both 4 and 3"], "answer": 1, "explain": ["Dijkstra never keeps a worse distance once a better one appears — that is the whole point of relaxation.", "Correct — this is 'relaxation': whenever a cheaper path appears, overwrite the old distance.", "Only the best-known distance is kept, never both."]}
```

Let's make sure the shape of the algorithm has stuck:

```tm-order
{"q": "Put these Dijkstra steps in the order the algorithm performs them.", "steps": ["Set the start node's distance to 0 and every other node to infinity.", "Pick the unvisited node with the smallest known distance.", "Relax that node's neighbours, improving any distance you can beat.", "Mark the node visited, and repeat until every node is visited."], "explain": "Every round is identical: pick the closest unvisited node, relax its neighbours, mark it done."}
```

Here's the inner loop in code — two pieces are missing:

```tm-cloze
{"q": "Complete the relaxation step.", "code": ["for neighbour, weight in graph[current]:", "    candidate = dist[current] {{1}} weight", "    if candidate {{2}} dist[neighbour]:", "        dist[neighbour] = candidate"], "blanks": [{"options": ["+", "-", "*"], "answer": 0}, {"options": ["<", ">", "=="], "answer": 0}], "explain": "You add the edge weight to the current distance, and only keep it when it beats the best distance found so far."}
```

Your turn to cement it:

```tm-task
{"q": "Using the same graph, what is the shortest distance from A to D, and which path achieves it? Walk through your reasoning.", "hints": ["Start by writing A's direct neighbours and their costs.", "Remember B's distance improves once you go through C.", "Compare reaching D directly from C (cost 5 from C) against reaching it through B."]}
```

Nail that and you've got the core of Dijkstra locked in. Want to try what changes if an edge had a negative weight?
MD;

// (Live code also runs resolveImageMarkers() here; this test has no image
// markers, so formatResponse alone is the exact contract under test.)
$formattedHtml = formatResponse($aiResponse);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Widget Test — Dijkstra's Algorithm</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Source+Sans+Pro:wght@400;600;700&family=Funnel+Display:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script>
        MathJax = { tex: { inlineMath: [['$', '$'], ['\\(', '\\)']], displayMath: [['$$', '$$'], ['\\[', '\\]']] } };
    </script>
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>

    <!-- The REAL styles under test -->
    <link rel="stylesheet" href="assets/css/ui-overhaul.css?v=<?= filemtime('assets/css/ui-overhaul.css') ?>">
    <link rel="stylesheet" href="assets/css/tm-widgets.css?v=<?= filemtime('assets/css/tm-widgets.css') ?>">

    <style>
        body { max-width: 820px; margin: 0 auto; padding: 2rem 1rem 5rem; background: var(--bg-main); }
        .harness-bar {
            display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
            margin-bottom: 1.5rem; padding: 0.9rem 1.1rem;
            border: 1.5px dashed var(--border); border-radius: 12px; background: var(--bg-card);
        }
        .harness-bar h1 { font-family: "Funnel Display", sans-serif; font-size: 1.1rem; margin: 0; color: var(--text-primary); }
        .harness-bar .note { font-size: 0.8rem; color: var(--text-secondary); }
        .harness-bar button {
            margin-left: auto; font: inherit; font-size: 0.85rem; padding: 0.45rem 1rem;
            border: 1.5px solid var(--primary); border-radius: 8px; background: transparent;
            color: var(--primary); font-weight: 600; cursor: pointer;
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
        .message.user { flex-direction: row-reverse; }
        .message.user .message-content { background: var(--primary); color: #fff; border-color: var(--primary-dark); }
        .message-content pre { background: #1e1e2e; color: #e6e1ff; padding: 0.8rem 1rem; border-radius: 8px; overflow-x: auto; font-size: 0.85rem; }
        .message-content p { margin: 0 0 0.7rem; }
    </style>
</head>
<body>
    <div class="harness-bar">
        <div>
            <h1>🧪 Widget test — Dijkstra's algorithm</h1>
            <div class="note">Real <code>formatResponse()</code> + real <code>tm-widgets.js</code> + real CSS. Chips/Task simulate a reply as a new bubble.</div>
        </div>
        <div class="tm-contact-chip" id="contactChip" tabindex="0" role="img" aria-label="Learning progress: 0 of 3 contacts made">
            <span class="tm-contact-dots">
                <span class="tm-contact-dot" data-contact="analogy"></span>
                <span class="tm-contact-dot" data-contact="build"></span>
                <span class="tm-contact-dot" data-contact="predict"></span>
            </span>
            <span class="tm-contact-label" id="contactChipLabel">0 / 3</span>
            <span class="tm-contact-tip" id="contactChipTip"></span>
        </div>
        <button id="themeToggle" type="button">Toggle dark mode</button>
    </div>

    <div id="chat">
        <div class="message ai">
            <div class="message-avatar">🤖</div>
            <div class="message-content" id="aiBubble"><?= $formattedHtml ?></div>
        </div>
    </div>

    <!-- The REAL module under test -->
    <script src="assets/js/tm-widgets.js?v=<?= filemtime('assets/js/tm-widgets.js') ?>"></script>
    <script>
        const chat = document.getElementById('chat');

        // Wire the module the same way tutor_mysql.js does, but onReply appends a
        // local user bubble instead of firing a network send (this is a static test).
        // Mirror of updateContactChip() in tutor_mysql.js, so the header chip can
        // be previewed here. In the live app the state comes from the server.
        const contactState = { analogy: false, build: false, predict: false };
        function updateContactChip(state) {
            const chip = document.getElementById('contactChip');
            let made = 0;
            ['analogy', 'build', 'predict'].forEach(function (name) {
                const dot = chip.querySelector('.tm-contact-dot[data-contact="' + name + '"]');
                if (state[name]) { dot.classList.add('tm-filled'); made++; }
                else { dot.classList.remove('tm-filled'); }
            });
            chip.classList.toggle('tm-complete', made === 3);
            chip.setAttribute('aria-label', 'Learning progress: ' + made + ' of 3 contacts made');
            document.getElementById('contactChipLabel').textContent = made === 3 ? 'Encoded' : made + ' / 3';
            document.getElementById('contactChipTip').textContent = made === 3
                ? 'All three contacts made — you connected this to something you already knew, built with it, and predicted with it. That combination is what moves a concept into long-term memory.'
                : 'Concepts stick after three kinds of contact: connecting them to something you know, building something with them, and predicting with them. ' + made + ' of 3 so far this session.';
        }
        updateContactChip(contactState);

        TMWidgets.init({
            onReply: function (text) {
                const msg = document.createElement('div');
                msg.className = 'message user';
                msg.innerHTML = '<div class="message-avatar">👤</div><div class="message-content"></div>';
                msg.querySelector('.message-content').textContent = text;
                chat.appendChild(msg);
                msg.scrollIntoView({ behavior: 'smooth', block: 'center' });

                // Preview only: fill the next contact so the chip can be seen animating.
                const next = ['analogy', 'build', 'predict'].find(function (n) { return !contactState[n]; });
                if (next) { contactState[next] = true; updateContactChip(contactState); }
            },
            onReveal: function (el) {
                if (window.MathJax && MathJax.typesetPromise) MathJax.typesetPromise([el]).catch(function(){});
            }
        });

        // This is the exact call finalizeMessage() makes in the live app.
        TMWidgets.render(document.getElementById('aiBubble'));

        // Highlight the plain (non-widget) code block that remains.
        if (window.hljs) document.querySelectorAll('#aiBubble pre code').forEach(function (b) { hljs.highlightElement(b); });

        document.getElementById('themeToggle').addEventListener('click', function () {
            document.body.classList.toggle('dark-mode');
        });
    </script>
</body>
</html>
