# Dashboard rebuild — plan (2026-09-28)

**Status: Phases 1–4 done on dev, 2026-09-28, not pushed.** c18203d (bugs) · a31270e (API + chat hand-off) ·
678b0ed (redesign). Deviations from the plan below: the 12-week grid lives inside the streak card on desktop
(no separate card); period chips sit on the stats band they filter; period stats key is `summary`.
Follow-ups: durable "reviewed" state for missed questions (`recall_quizzes.reviewed_at` migration);
`chat_area_bundle/dashboard.php` is an old dead copy.

Source: UI + UX review of `dashboard.php` on 2026-09-28. Target layout: the "TutorMind Dashboard v2"
canvas (https://claude.ai/artifact/QeamSEhZb7c18QGuNtZ8LV) — desktop light/dark, mobile light/dark,
and the first-visit empty state.

**Order matters.** Phase 1 fixes things users see wrong today. Phase 2 makes the API return honest
data. Phase 3 redesigns on top of that data; doing 3 before 2 would polish numbers that don't mean
anything. Each phase ships on its own (dev auto-deploys, so keep each commit self-contained).

---

## Phase 1 — Bugs (small, ship first)

| # | Bug | Where | Fix |
|---|-----|-------|-----|
| 1.1 | **Streak is always 0.** The loop only increments when `diff === $currentStreak && diff > 0`, which can't happen from 0. | `api/analytics.php:173-181` | Walk the distinct study dates newest-first. Start at today if studied today, else yesterday (a streak survives until the day ends). Count while each date is exactly one day before the previous; stop at the first gap. |
| 1.2 | **Session titles, quiz questions, topics and error text go into `innerHTML` unescaped** (stored XSS). | `dashboard.php:1197, 1541, 1575, 1638` | Add an `esc()` helper and wrap every server string. |
| 1.3 | **Heatmap month labels drift right.** Spacing uses `CELL = 14`, but a column is 16px (14 + 2 gap), and inline label widths add up. | `dashboard.php:1322-1336` | Position each label absolutely at `weekIndex * 16px`. |
| 1.4 | **Opening the mobile sidebar rebuilds all 7 charts.** The observer fires on any `body` class change (`sidebar-open`). | `dashboard.php:1856` | Remember the last `dark-mode` state and re-render only when it flips. |
| 1.5 | **Dark-mode dropdown hover is unreadable.** Background is `--ink` (white at 15%), text is `--card-bg` (#1a1a2e). | `dashboard.php:309` | Hover = tint background with the normal text colour. |
| 1.6 | **Every visit takes at least ~4.7s.** The loader always runs 2 × 2200ms, then 250ms more, and the data request waits on `applyDarkMode()`'s fetch first. | `dashboard.php:856-866, 1103, 1185` | Hide the loader on the first render; drop the 250ms delay; don't wait on the settings request (the server already sets the theme class). |
| 1.7 | **Heatmap dates can shift a day.** Keys are built with `toISOString()` (UTC) from local midnight. | `dashboard.php:1314` | Build `YYYY-MM-DD` from local `getFullYear/getMonth/getDate`. |

Phase 3 replaces the dropdown and heatmap anyway, but the streak, XSS and loader fixes carry over.
Verify 1.1 with a unit-style PHP check: study dates today/yesterday/2 days ago → 3; yesterday/2/3 → 3;
today and 3 days ago → 1.

---

## Phase 2 — Make `api/analytics.php` return honest data

Only `dashboard.php` calls this endpoint. `scripts/send_study_reminders.php` just copies its
"last activity = MAX(conversations.created_at)" convention and doesn't need to change.

### What's wrong with the current metrics
- **"Progress"** is message count ÷ a fixed target (`session-context.js:172-190`), so it measures chattiness.
- **"Topics"** are the first two non-stop-words of the title (`analytics.php:70-78`), e.g. "Causes First".
- **Milestones** (`context_data.outline.milestones`, shape `{title, completed}`) are marked done when the
  tutor's reply mentions ≥50% of the milestone's title words (`tutor_service.php:330-355`). That's
  *coverage*, not mastery; label it "covered" and never "mastered".
- **Quiz scores** (`recall_quizzes.score`, 0–1) are the only real evidence of learning.

### New response shape (add these; drop the old keys once Phase 3 no longer reads them)
```jsonc
{
  "continue": {            // latest conversation with incomplete milestones (else latest overall)
    "id": 123, "title": "…", "topic": "Organic Chemistry", "updatedAt": "…",
    "milestonesDone": 3, "milestonesTotal": 5, "nextMilestone": "Draw the transition state"
  },
  "recent": [ { "id", "title", "topic", "updatedAt" } ],          // next 2–3 after `continue`
  "review": [                                                   // max 3, mixed, newest first
    { "kind": "quiz", "quizId", "conversationId", "topic", "question", "score", "answeredAt" },  // score < 0.7
    { "kind": "quiet", "topic", "conversationId", "lastStudied", "milestonesDone", "milestonesTotal" } // untouched ≥ 7 days, not finished
  ],
  "streak": { "days": 4, "studiedToday": false, "last7": [true,false,true,true,true,true,null] },
  "subjects": [ { "topic", "conversationId", "sessions", "milestonesDone", "milestonesTotal", "lastStudied" } ], // by recency
  "recall": [ { "type": "free_recall", "label": "Explaining from memory", "avg": 54, "count": 7 } ],          // weakest first
  "summary": { "sessions": 34, "activeDays": 17, "focusMinutes": 615, "pomodorosDone": 21,
               "prev": { "sessions": 28, "activeDays": 19 } },   // prev = null for "all" (named summary: old UI reads `stats`)
  "now": "YYYY-MM-DD HH:MM:SS",                                  // MySQL NOW(), so "2 days ago" uses the same clock as the timestamps
  "heatmap": { "YYYY-MM-DD": count }                              // last 91 days is enough now
}
```
- **Topic** comes from `context_data.topic` everywhere. Titles are only for display.
- **Dates:** `lastStudied` / `updatedAt` use `conversations.updated_at`, not `created_at`, so a resumed
  session counts as recent.
- **Period scope:** the selector filters `stats` only. `continue`, `review`, `streak` and `subjects` are
  always "now". Say so in the UI ("Numbers cover …") so the scope isn't a mystery.
- **Recall labels** are plain words: recognition → "Picking the right answer", cued → "Recalling with a
  hint", application → "Applying it to a new problem", free_recall → "Explaining from memory".
- **Performance:** the current code decodes `context_data` for every conversation in the period. For
  "all time" with heavy users, add `LIMIT` / aggregate in SQL or cache per user for 60s.

### Decided (2026-09-28): option A. Built as `assets/js/review-handoff.js`: the dashboard stores
`{conversationId, prompt}` in sessionStorage `tm_review_prompt`, then opens `/chat/{id}`; the chat pre-fills
the composer (never auto-sends). "Done" is per-browser for now (localStorage); a durable version needs a
`recall_quizzes.reviewed_at` column (migration). Missed questions also age out after 14 days.

### Options considered for "Try again" (no retry action exists in `api/quiz.php` today)
- **A (recommended, smallest):** deep-link to `/chat/{conversationId}?review={quizId}`. The chat loads
  that quiz row and the tutor asks it again in its own words, which fits construction ownership:
  the student re-explains.
- **B:** add `action=retry` to `api/quiz.php` that re-serves the stored question and reuses `grade`,
  shown in a dialog on the dashboard itself.

---

## Phase 3 — Redesign on the design system

Follow `.claude/skills/tutormind-design` and match the canvas.

- **Structure:** move the inline `<style>` → `assets/css/dashboard.css` and the inline `<script>` →
  `assets/js/dashboard.js`. The page loads `tm-tokens.css` + `tm-ds.css` (+ `tm-ds.js` if chips or
  buttons need it) and stops loading `ui-overhaul.css`.
- **Drop Chart.js.** The new layout has no charts; the milestone segments, recall bars, 7-day strip
  and 12-week grid are plain DOM. That saves ~200 KB and the theme-switch re-render problem goes away.
- **Sections, in order:**
  1. Greeting + period chips (`.ds-chip` with `aria-pressed`)
  2. 3-stat band (sessions, days studied, focused time), with trends in words
  3. **Pick up where you left off** (`--cta` Continue button + recent links)
  4. Streak card (bridge-arch mark + 7-day strip)
  5. **Worth another look**
  6. **How well it sticks**
  7. **Your subjects** (whole card is a link)
  8. Past 12 weeks: desktop only, grid ends at today

  On mobile: streak → continue → stats → review → recall → subjects, and no heatmap.
- **Removed:** topics donut, study-goals chart, focus-mode donut, Avg Progress, Progress Over Time, the
  8-KPI grid, the sidebar table of contents, and the 🔥 emoji.
- **Empty state:** a single "Start your first session" panel + three dashed "what shows up here"
  previews. No zeros, no empty axes.
- **Anti-pattern checklist (all must pass):**
  - one accent colour (no per-card hues)
  - no side stripes
  - no uppercase eyebrow labels
  - no gradients except the logo
  - radius scale only (8/10/14/20)
  - depth = token shadows + solid button edge
  - `--tm-good` / `--tm-bad` not used decoratively
- **Entry and exit:**
  - Remove `target="_blank"` from the Dashboard link (`tutor_mysql.php:434`).
  - "Back to chat" returns to the last conversation (`/chat/{id}`), not a blank chat.
  - Session and subject links go to `/chat/{id}`.
- **Accessibility:**
  - real `<a>` / `<button>`, with ≥44px touch targets on mobile
  - text alternatives for the strip and grid (`aria-label` per day, or a visually hidden summary)
  - keyboard-reachable chips
  - no hover-only information

---

## Phase 4 — Verify

1. **Mock-data harness.** Stub `api/analytics.php` through CDP `Fetch.requestPaused`; no DB seeding
   (pattern from the review session's `dash_shoot.mjs`).
   - Screenshot desktop 1440 and mobile 390 (`Emulation.setDeviceMetricsOverride`), light and dark.
   - Check `scrollWidth === clientWidth` at 390.
2. **Real data.** Use a `nobody_*` test account with ≤2 login attempts (rate limiter), then delete it.
   - Brand-new user → empty state.
   - Seed one conversation with `context_data.topic` + outline → Continue card and a subject card.
   - Seed a quiz row with score 0.33 → review item.
3. **Streak.** Seed conversations on the last 3 days → "3 days".
4. **Theme.** Toggle dark mode while the page is open: nothing re-animates; every surface follows the theme.
5. **Deploy.** Push to dev auto-deploys; no migration is needed for Phases 1–3.
