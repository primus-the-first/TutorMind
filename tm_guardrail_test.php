<?php
/**
 * DEV-ONLY simulation harness for the proposed "protect the student's own
 * construction work" guardrail (Justin Sung / HUDLE-derived principle #2)
 * and how it interacts with the existing ACTIVE MENTAL MODEL mechanism.
 *
 * NOT yet wired into tutor_service.php — this is a design-validation tool.
 * The two "AI" turns below are hand-authored to show what COMPLIANT output
 * would look like under the drafted rule, run through the REAL
 * formatResponse() + tm-widgets.js pipeline so the widgets you see are
 * genuine, not a mockup.
 *
 * Reuses the cultivation/Dijkstra thread from an earlier real session
 * tonight for continuity — same student, same active mental model.
 *
 * Open at: http://localhost/TutorMind/tm_guardrail_test.php
 * Safe to delete — touches no database, session, or secrets.
 */

// PRODUCTION GUARD — deny access in any environment that is not explicitly
// marked as 'development'. This file must not be reachable on the live server.
if (getenv('APP_ENV') !== 'development') {
    http_response_code(403);
    exit('Forbidden');
}


require_once __DIR__ . '/api/services/response_formatter.php';

// --- The drafted guardrail text under test (not yet in tutor_service.php) ---
$draftGuardrail = <<<'TXT'
## PROTECT THE STUDENT'S OWN CONSTRUCTION WORK

If the student asks you to build them a mind map, a study guide, a set of notes, or a summary of the material as something to save and review later, do not do it. Decline and redirect them to build it themselves — offer to check the logic of what they propose once they have a draft, rather than producing the artifact yourself. This applies even when the request is reasonable-sounding ("just this once", "to save time", "I'm behind") — building the map is where the actual encoding happens; handing them a finished one skips that step entirely.

This does not restrict your normal teaching: explaining a concept, connecting ideas in your prose, and extending the student's own ACTIVE MENTAL MODEL remain fine — the restriction is specifically on producing a standalone, save-able study artifact that substitutes for the student's own organizing work.

**tm-steps inside an active mental model**: a worked example demonstrates ONE case — it is not a substitute for the student mapping the rest of the topic themselves. After a tm-steps reveal inside an active mental model, follow up with a tm-task that asks the student to extend the model to a new facet in their own words, rather than walking through further cases yourself.
TXT;

// --- Scenario A: the new guardrail declining a study-artifact request ---
$scenarioA_student = "This is a lot to take in. Can you just make me a full mind map of Dijkstra's algorithm so I can review it before my test?";
$scenarioA_ai = <<<'MD'
I get the instinct — a finished map feels like the fast way to be ready for a test. But I'm not going to build that one for you, and here's the honest reason why: the part where you decide which ideas connect to which, and why, is the actual studying. If I hand you a finished map, you get something to *read*, but not the thing that makes it stick.

So let's do it the way that actually works: you sketch your own map — on paper, in your notes, however you like — and bring it back here. I'll go through it with you and tell you where the connections are solid and where something's off. That's a much better use of both of us.

Want to start with just the handful of ideas from today's session? What connects, in your view?
MD;

// --- Scenario B: mental model active, tm-steps bounded to one case, then a required build task ---
$scenarioB_student = "Can you show me how it handles a graph where two paths to the same realm tie in cost?";
$scenarioB_ai = <<<'MD'
Good question — ties are exactly where people's intuition about Dijkstra gets shaky. Let's watch it happen once in your world, then you're going to work out a different case yourself.

Say two different elixir routes both get you to Realm D in exactly 7 units of cultivation time.

```tm-steps
{"q": "Two tied routes reach Realm D, both costing 7 units", "steps": ["Route 1 finishes relaxing first and sets Realm D's distance to 7, recording its path.", "Route 2 is checked next. Its cost is also 7 - not strictly less than the recorded 7, so relaxation does NOT overwrite it.", "Realm D keeps whichever route got there first in processing order. Dijkstra doesn't need to prefer one tie over another to stay correct - either shortest path is valid."], "predict": true}
```

That's one tie, handled one way. Ties don't all look the same, though.

```tm-task
{"q": "Now build a different one yourself: describe a tie between THREE routes reaching the same realm, all at equal cost, and walk through what Dijkstra actually keeps. Don't just restate my example - use your own numbers and your own realms.", "hints": ["Start by picking three different paths from A to your realm, and give each one a total cultivation cost - make at least two of them equal.", "Only the actual comparison at relaxation time decides what gets kept - not the order you listed them in."]}
```
MD;

$htmlA = formatResponse($scenarioA_ai);
$htmlB = formatResponse($scenarioB_ai);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guardrail Simulation — Study Artifacts vs. Mental Model</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Source+Sans+Pro:wght@400;600;700&family=Funnel+Display:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>

    <link rel="stylesheet" href="assets/css/ui-overhaul.css?v=<?= filemtime('assets/css/ui-overhaul.css') ?>">
    <link rel="stylesheet" href="assets/css/tm-widgets.css?v=<?= filemtime('assets/css/tm-widgets.css') ?>">

    <style>
        body { max-width: 820px; margin: 0 auto; padding: 2rem 1rem 5rem; background: var(--bg-main); }
        .harness-bar {
            padding: 0.9rem 1.1rem; margin-bottom: 1.5rem;
            border: 1.5px dashed var(--border); border-radius: 12px; background: var(--bg-card);
        }
        .harness-bar h1 { font-family: "Funnel Display", sans-serif; font-size: 1.1rem; margin: 0 0 0.3rem; color: var(--text-primary); }
        .harness-bar .note { font-size: 0.8rem; color: var(--text-secondary); line-height: 1.5; }

        .draft-box {
            background: var(--bg-card); border: 1.5px solid var(--border); border-radius: 12px;
            padding: 1rem 1.2rem; margin-bottom: 2rem; font-size: 0.85rem; color: var(--text-primary);
            white-space: pre-wrap; line-height: 1.6;
        }
        .draft-box summary { cursor: pointer; font-weight: 700; font-family: "Funnel Display", sans-serif; color: var(--primary); }

        .scenario-label {
            font-family: "Funnel Display", sans-serif; font-size: 0.95rem; font-weight: 700;
            color: var(--text-primary); margin: 2.5rem 0 1rem; padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border);
        }
        .scenario-label .tag {
            display: inline-block; font-family: "Source Sans Pro", sans-serif; font-size: 0.65rem;
            font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: var(--cta);
            background: rgba(245, 158, 11, 0.12); padding: 0.2rem 0.6rem; border-radius: 999px;
            margin-left: 0.6rem; vertical-align: middle;
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
        <h1>🧪 Guardrail simulation — not yet implemented</h1>
        <div class="note">Both AI turns below are hand-authored to show COMPLIANT output under the drafted rule (shown below, collapsed). Rendered through the real formatResponse() + tm-widgets.js — the widgets are genuine, not a mockup. Nothing here is wired into the live prompt.</div>
    </div>

    <details class="draft-box">
        <summary>View the drafted guardrail text</summary>
        <?= htmlspecialchars($draftGuardrail) ?>
    </details>

    <div class="scenario-label">Scenario A — declining a study-artifact request <span class="tag">new guardrail</span></div>
    <div id="chatA">
        <div class="message user">
            <div class="message-avatar">👤</div>
            <div class="message-content"><?= htmlspecialchars($scenarioA_student) ?></div>
        </div>
        <div class="message ai">
            <div class="message-avatar">🤖</div>
            <div class="message-content" id="aiBubbleA"><?= $htmlA ?></div>
        </div>
    </div>

    <div class="scenario-label">Scenario B — mental model active, tm-steps bounded to one case <span class="tag">reconciled behavior</span></div>
    <div id="chatB">
        <div class="message user">
            <div class="message-avatar">👤</div>
            <div class="message-content"><?= htmlspecialchars($scenarioB_student) ?></div>
        </div>
        <div class="message ai">
            <div class="message-avatar">🤖</div>
            <div class="message-content" id="aiBubbleB"><?= $htmlB ?></div>
        </div>
    </div>

    <script src="assets/js/tm-widgets.js?v=<?= filemtime('assets/js/tm-widgets.js') ?>"></script>
    <script>
        TMWidgets.init({
            onReply: function (text) {
                const chat = document.getElementById('chatB');
                const msg = document.createElement('div');
                msg.className = 'message user';
                msg.innerHTML = '<div class="message-avatar">👤</div><div class="message-content"></div>';
                msg.querySelector('.message-content').textContent = text;
                chat.appendChild(msg);
                msg.scrollIntoView({ behavior: 'smooth', block: 'center' });
            },
            onReveal: function () {}
        });
        TMWidgets.render(document.getElementById('aiBubbleA'));
        TMWidgets.render(document.getElementById('aiBubbleB'));
        if (window.hljs) {
            document.querySelectorAll('#aiBubbleA pre code, #aiBubbleB pre code').forEach(function (b) { hljs.highlightElement(b); });
        }
    </script>
</body>
</html>
