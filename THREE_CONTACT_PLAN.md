# Three-Contact Rule — Implementation Plan

## What This Is

A neuroscience-backed learning architecture embedded invisibly into TutorMind's
existing AI tutor. Students continue to experience normal tutoring sessions. Underneath,
the system tracks whether three critical contacts have been made per concept, and routes
the AI's behavior accordingly until all three are complete.

**The Three Contacts:**
- **Analogy** — student generates their own connection to prior knowledge (schema activation)
- **Build** — student produces something: pseudocode, a scenario, steps, a paragraph (enactment encoding)
- **Predict** — student reasons through a novel scenario before advancing (generation effect + LTP trigger)

Students come for answers. They leave having genuinely encoded the concept.

---

## Files Changed (3 files, no new tables, no frontend changes)

```
api/services/comprehension_service.php   ← Add detectContactState(), update calculateHybridProgress()
api/services/tutor_service.php           ← Upgrade buildSystemPrompt() with Three-Contact Protocol
includes/server_mysql.php                ← Wire detect → prompt → store in context_data
```

---

## Step 1 — `detectContactState()` in comprehension_service.php

**Add this function** alongside `analyzeComprehension()`.

Scans all student messages in the conversation and returns which contacts have been made.

```php
function detectContactState(array $messages): array
{
    $state = ['analogy' => false, 'build' => false, 'predict' => false];

    // Collect all student message text
    $combinedText = '';
    foreach ($messages as $message) {
        if (($message['role'] ?? '') !== 'user') continue;
        foreach ($message['parts'] ?? [] as $part) {
            if (isset($part['text'])) {
                $combinedText .= ' ' . $part['text'];
            }
        }
    }

    $text = strtolower($combinedText);

    // --- Contact 1: Analogy ---
    // Student made a connection to something they already know
    $analogyPatterns = [
        '/it\'?s (like|similar to|basically|kind of like)/',
        '/reminds? me of/',
        '/think of it as/',
        '/analogous to/',
        '/just like/',
        '/same as when/',
        '/so (basically|essentially) it\'?s/',
        '/compared to/',
    ];
    foreach ($analogyPatterns as $pattern) {
        if (preg_match($pattern, $text)) {
            $state['analogy'] = true;
            break;
        }
    }

    // --- Contact 2: Build ---
    // Student produced something: code, steps, structured output, an attempt
    $buildPatterns = [
        '/i (tried|built|made|wrote|created|implemented|coded|developed)/',
        '/here\'?s (my|the) (solution|attempt|code|answer|approach)/',
        '/let me try/',
        '/my (solution|attempt|approach|code|answer|implementation)/',
        '/```/',                               // code block present
        '/step \d/',                           // numbered steps
        '/first[,.]?\s+(then|next|after)/',   // structured sequence
        '/pseudocode/',
        '/the (steps|process|algorithm) (is|would be|are)/',
    ];
    foreach ($buildPatterns as $pattern) {
        if (preg_match($pattern, $text)) {
            $state['build'] = true;
            break;
        }
    }

    // --- Contact 3: Predict ---
    // Student reasoned through a novel scenario — forward-looking, not recall
    $predictPatterns = [
        '/i (think|predict|believe|expect|guess) (it|this|that) (would|will|should|might|could)/',
        '/so (then|it|this) (would|should|means|could)/',
        '/that (means|would mean|implies|suggests)/',
        '/therefore/',
        '/if .{5,80} then/',
        '/(would|should|will) (happen|result|cause|lead|mean)/',
        '/i\'?d expect/',
        '/the result (would|should|will)/',
        '/this (would|could|should) (cause|lead to|result in)/',
    ];
    foreach ($predictPatterns as $pattern) {
        if (preg_match($pattern, $text)) {
            $state['predict'] = true;
            break;
        }
    }

    $state['missing'] = array_keys(array_filter($state, fn($v) => $v === false));

    return $state;
}
```

---

## Step 2 — `buildSystemPrompt()` in tutor_service.php

**Change the function signature** to accept an optional contact state:

```php
// BEFORE
function buildSystemPrompt($learningLevel, $personalization_context)

// AFTER
function buildSystemPrompt($learningLevel, $personalization_context, $contact_state = null)
```

**Build the Three-Contact Protocol section** just before the `return <<<PROMPT` line:

```php
// --- Three-Contact Protocol ---
$threeContactSection = '';
if ($contact_state !== null) {
    $missing = $contact_state['missing'] ?? [];

    $instructions = [];

    if (in_array('analogy', $missing)) {
        $instructions[] = <<<EOT
**ANALOGY CONTACT (not yet made):** Before or during your explanation, ask the learner
what this concept reminds them of from their own experience. Do NOT provide your own
analogy first — let them reach for one. Ask: "Before I explain, what does [concept]
remind you of?" or "Have you encountered anything like this in real life?" Only provide
your own analogy if they genuinely cannot make a connection. Their analogy anchors the
concept to existing memory. Yours is a fallback.
EOT;
    }

    if (in_array('build', $missing)) {
        $instructions[] = <<<EOT
**BUILD CONTACT (not yet made):** End your response with a micro-task the learner must
complete. For technical topics: write pseudocode, design a schema, trace through an
example, identify where a concept applies in a code snippet. For conceptual topics:
apply the concept to a specific scenario they haven't seen, write a one-paragraph
analysis, compare two cases. Keep it small — 2 to 5 minutes of effort. Frame it as a
natural next step, not a test: "Now try...", "Have a go at...", "Your turn:".
EOT;
    }

    if (in_array('predict', $missing)) {
        $instructions[] = <<<EOT
**PREDICT CONTACT (not yet made):** Before closing this topic, pose a novel scenario the
learner has NOT encountered yet and ask them to predict the outcome using what they now
know. The scenario must differ from your examples. This is not recall — it requires
reasoning from first principles. Good predict questions start with: "What do you think
happens if...", "Predict what would occur when...", "A system does X — what breaks and
why?". After they predict, confirm or correct, then advance.
EOT;
    }

    if (!empty($instructions)) {
        $threeContactSection = "\n\n## THREE-CONTACT PROTOCOL\n\n"
            . "The following contacts have NOT been made yet in this session. Complete them "
            . "naturally — they must feel like good teaching, not a checklist. Do not name "
            . "them to the student. Do not advance to a new concept until all three are made.\n\n"
            . implode("\n\n", $instructions);
    } else {
        $threeContactSection = "\n\n## THREE-CONTACT PROTOCOL\n\n"
            . "All three contacts are complete for this topic (analogy ✓, build ✓, predict ✓). "
            . "You may advance to new concepts naturally.\n";
    }
}
```

**Inject `$threeContactSection`** into the heredoc between Phase 2 and Phase 3:

```
---

## PHASE 2: SELECT STRATEGY
...existing content...

{$threeContactSection}

---

## PHASE 3: CRAFT YOUR RESPONSE
...existing content...
```

---

## Step 3 — Wire in server_mysql.php

**Find the line** (~line 965):
```php
$system_prompt = buildSystemPrompt($learningLevel, $personalization_context);
```

**Replace with:**
```php
// Detect which Three-Contact contacts have been made in this conversation
$contact_state = detectContactState($chat_history);

// Store contact state in context_data for progress tracking and frontend access
$contextData['contactState'] = [
    'analogy' => $contact_state['analogy'],
    'build'   => $contact_state['build'],
    'predict' => $contact_state['predict'],
];

$system_prompt = buildSystemPrompt($learningLevel, $personalization_context, $contact_state);
```

> Note: `$contextData` is already declared above this point in server_mysql.php.
> The contact state is stored in the existing `context_data` JSON column — no migration needed.

---

## Step 4 — Update `calculateHybridProgress()` in comprehension_service.php

**Find the weighting block:**
```php
// Weighted combination: 70% milestones, 20% comprehension, 10% engagement
$hybridProgress =
    ($milestoneProgress * 0.70) +
    ($comprehensionScore * 100 * 0.20) +
    ($engagementScore * 100 * 0.10);
```

**Replace with:**
```php
// Three-Contact completion score
$contactState   = $contextData['contactState'] ?? [];
$contactsMade   = (int)($contactState['analogy'] ?? false)
                + (int)($contactState['build']   ?? false)
                + (int)($contactState['predict'] ?? false);
$contactProgress = ($contactsMade / 3) * 100;

// Weighted combination: 55% milestones + 15% comprehension + 10% engagement + 20% contacts
$hybridProgress =
    ($milestoneProgress  * 0.55) +
    ($comprehensionScore * 100 * 0.15) +
    ($engagementScore    * 100 * 0.10) +
    ($contactProgress          * 0.20);
```

---

## Implementation Order

| # | File | Change | Risk |
|---|---|---|---|
| 1 | `comprehension_service.php` | Add `detectContactState()` | Zero — new function, nothing calls it yet |
| 2 | `tutor_service.php` | Upgrade `buildSystemPrompt()` | Low — backward compatible, param is optional |
| 3 | `server_mysql.php` | Wire detect → prompt → store | Medium — touches main chat flow, test after |
| 4 | `comprehension_service.php` | Update `calculateHybridProgress()` | Low — only affects progress score |

---

## What the Student Experiences

Nothing different on the surface. Same clean chat interface. Same fast responses.

What changes is the shape of every interaction:
- The AI asks before it explains
- The AI assigns before it closes
- The AI challenges before it advances

Over repeated sessions, the student's brain encodes the pattern.
They start doing it themselves — reaching for analogies, wanting to build, predicting
outcomes — without the platform prompting them.

That is the goal. The platform installs a thinking architecture and then becomes optional.
