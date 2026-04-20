# TutorMind Development Context

## Progress Log - April 18, 2026

### 1. Code Block Rendering Fix (System Prompt)
**Problem:** AI responses mentioned code ("Here's an example...") but never showed it. Gemini was treating code as a "direct answer" it should withhold per the pedagogical prompt.
**Root cause confirmed via Apache error log:** `has_codefence=0` on all responses; short responses (`len=533`) ending right before code with `finishReason=STOP`.
**Fixes (`server_mysql.php` + `chat_area_bundle/server_mysql.php`):**
- Added explicit carve-out in "When They Ask for Direct Answer": code examples are teaching tools, not direct answers — never withhold them.
- Added to IF PROGRAMMING section: always provide real code examples; always use triple-backtick fenced blocks with language identifier (`python`, `javascript`, etc.).

### 2. Groq Fallback Activated
**Problem:** Gemini free tier limit is only 20 RPD — practically unusable. DeepSeek fallback was hitting HTTP 402 (insufficient balance). Groq was configured in `config-sql.ini` but with an empty key so it was never activated.
**Fix:** Added Groq API key to `config-sql.ini`. Groq free tier gives 14,400 RPD (llama-3.3-70b-versatile), 720× more than Gemini free tier.
- **Files changed:** `config-sql.ini`

### 3. Syntax Highlighting Fix
**Problem:** Code blocks appeared "only red / blaring" due to two issues:
1. Theme `vs2015.min.css` has reddish-orange string tokens that dominate simple JS examples.
2. `addCopyButtonsToCodeBlocks()` called `window.syntaxHighlighter.highlight()` but `syntax-highlighter.js` was never loaded in `tutor_mysql.php`, so dynamic messages were never highlighted.
**Fixes:**
- Swapped theme from `vs2015` → `github-dark` (softer palette, blue keywords, green strings).
- Fixed per-message highlighting in `tutor_mysql.js` to call `hljs.highlightElement()` directly instead of the missing `window.syntaxHighlighter`.
- **Files changed:** `tutor_mysql.php`, `tutor_mysql.js`

### 4. Debug Logging Removed
Removed temporary `FORMAT_DEBUG` error_log statements from both `server_mysql.php` files that were added during the code block investigation.

### 5. Quiz API Rate Limit & Groq Fallback
**Problem:** The Active Recall Quiz would occasionally return `"AI service unavailable"`. This was diagnosed as a combination of Gemini free tier rate limits and model identifier mismatches (`gemini-2.5-flash` syntax).
**Fix (`api/quiz.php`):**
- Restored `gemini-2.5-flash` as the primary model with corrected `response_mime_type` casing.
- Implemented a robust **Groq Fallback** using `llama-3.3-70b-versatile`.
- The system now tries Gemini first and instantly falls back to Groq if the primary service is unavailable or rate-limited.
**Result:** Quiz generation and grading are now highly resilient and work as intended.

---

## Progress Log - April 19, 2026

### 1. Production 404 Redirection Fix
**Problem:** On Namecheap (LiteSpeed), the custom 404 page was not triggering for missing URLs/resources unless "/404" was manually typed. The server was either returning a generic blank page or the host's default parking page.
**Root Cause:** Host-level 404 handlers were intercepting requests before the `.htaccess` fallback rules could execute. Conditional `ErrorDocument` blocks were also failing to trigger consistently on production.
**Fixes (`.htaccess`):**
- **Priority Placement**: Moved `ErrorDocument` declarations to the absolute top of the file (before any other directives).
- **Quoted Paths**: Changed to `ErrorDocument 404 "/404.html"` to improve LiteSpeed's path resolution.
- **Index Protection**: Added `Options -Indexes` to prevent directory listings from being shown in place of 404s.
- **Forceful Fallback Rule**: Added a final "Last Resort" rewrite rule at the end of the chain: `RewriteRule ^.*$ 404.html [L,NC]`. This ensures that any request not matching a file, directory, or custom route is internally rewritten to the custom 404 page.
- **Environment Detection**: Refined the `localhost` and `127.0.0.1` checks to ensure local development continues to work without manual configuration changes.
**Result:** Custom 404 page now serves reliably across all environments.

---

### 2. Custom 403 Forbidden Page — April 19, 2026
**Objective:** Create a branded 403 page consistent with the 404 page, replacing the generic server error with a thematic "forbidden/locked" experience.
**Files changed/created:**
- **New**: `403.html` — Full 403 page based on `404.html` structure
- **Modified**: `.htaccess` — `ErrorDocument 403` updated from `/404.html` → `/403.html` (both global and localhost overrides)

**Design changes from 404:**
- **Color theme**: Crimson/red (`#DC2626`, `#EF4444`) replaces purple/gold
- **Robot**: Crimson dot-matrix eyes; all chest bars fully lit red with `LOCKED` label; red ear/antenna LEDs; hanging padlock-on-chain replaces the disconnected plug
- **Left arm**: Animates into a STOP gesture (raised palm wave) instead of reaching/searching
- **Mouth cycling**: `ERR:403` → `DENIED` → `LOCKED` → `NO ACCESS` → `403 …` → `FORBIDDEN`
- **Copy**: Title "A Virtual Guardrail"; subtitle explains missing permissions
- **Actions**: "Return Home" (primary) + "Login" (secondary) — covers the common case of unauthenticated access

---

## Progress Log - March 22, 2026

### 1. Sidebar Layout & Scrolling Fix
**Problem:** The sidebar was growing beyond the viewport height (100vh) when many conversations were present, pushing the profile menu off-screen and preventing the chat history from scrolling.
**Fixes:**
- Modified `ui-overhaul.css` to enforce `height: 100vh` and `display: flex` on the sidebar.
- Applied `flex: 1`, `min-height: 0`, and `overflow-y: auto` to `.chat-history`.
- Anchored the profile menu to the bottom using `margin-top: auto` and `flex-shrink: 0`.
- Added high-specificity CSS at the end of `ui-overhaul.css` to prevent the "Modern Scholar" theme from overriding layout mechanics.

### 2. JavaScript Runtime Error Fix
**Problem:** Browser console was reporting `Uncaught (in promise) TypeError: can't access property "addEventListener", overlay is null` in `tutor_mysql.js`.
**Fixes:**
- Updated element selection logic to look for both `sidebar-overlay` and `mobile-sidebar-overlay`.
- Added safety null checks before all `addEventListener` and `classList` operations on the overlay element.
- Fixed a discrepancy between the HTML ID (`mobile-sidebar-overlay`) and the JS expectations.

### 3. CSS Conflict Resolution
**Problem:** A "Neo-Brutalist" design overhaul at the end of the stylesheet was using `!important` flags that broke standard layout rules.
**Fixes:**
- Appended a "Bulletproof Scroll Fix" section to the very end of `ui-overhaul.css` to ensure structural integrity regardless of the theme.


---
*Status: Sidebar issues resolved, JS errors fixed, Profile menu visible.*

### 4. Chat Area Architecture & Files
**Objective:** Documented the key files and structure of the chat area for development context.
**Key Files:**
- **Main Interface**: `tutor_mysql.php`
- **Core Logic (JS)**: `tutor_mysql.js`, `chat-interface.js`, `quick-start.js`, `session-context.js`
- **Backend (PHP)**: `server_mysql.php`, `db_mysql.php`, `check_auth.php`
- **API Endpoints**: `api/session_context.php`, `api/tts.php`, `api/image.php`, `api/clear_history.php`
- **Styling (CSS)**: `ui-overhaul.css`, `chat-interface.css`, `onboarding-wizard.css`, `settings.css`, `logo.css`

**Backup Bundle**: All core files have been copied to `/chat_area_bundle` for quick reference.

---

### 5. Chat Endpoint Rate Limiting — March 22, 2026
**Objective:** Prevent abuse by limiting users to 15 messages per 60-second window.
**Files changed:**
- **Created**: `migrations/003_add_chat_rate_limits.sql` — `chat_rate_limits` table with UNIQUE key on `user_id`
- **Modified**: `server_mysql.php` — Added `checkChatRateLimit($pdo, $user_id)` function after auth require; called immediately after the POST method check (~line 534) returning HTTP 429 if exceeded
- **Modified**: `tutor_mysql.js` — Added a `response.status === 429` guard before the generic error throw, shows a friendly in-chat warning message and re-enables the send button

**Migration run:** ✅ Table created in `tutodtoo_tutordb`

---

### 6. AI-Assisted Comprehension Detection — March 22, 2026
**Objective:** Improve comprehension tracking accuracy on short, ambiguous student replies (e.g. "okay...", "lol ok", "ohhhh").
**Files changed:**
- **Modified**: `server_mysql.php` — Replaced `analyzeComprehension()` with a 2-layer hybrid:
  - **Layer 1 (Regex):** 10 positive + 10 negative patterns run instantly at zero cost. Sets `$hasExplicitSignal = true` when matched.
  - **Layer 2 (AI):** `aiComprehensionScore()` calls Gemini 2.5 Flash only when: no explicit regex signal found AND message is ≤15 words AND doesn't end with `?`. Returns a JSON `{signal, confidence, reasoning}` object; score = `±0.10 * confidence`.
  - Hard 5-second cURL timeout prevents AI call from slowing down chat responses.
  - Fail-silent: if AI call throws, regex delta (0.0) is used unmodified.

---

### 7. Frontend Improvements — March 22, 2026
**Files changed:** `tutor_mysql.js`, `ui-overhaul.css`

| # | Item | Change |
|---|---|---|
| 1 | **DEBUG flag** | `const DEBUG = false` added at top; all `console.log` calls wrapped in `if (DEBUG)` |
| 2 | **429 in `handleEditSubmit()`** | Added 429 check before generic throw; uses `showCopyToast()` + re-enables form |
| 3 | **Feedback buttons (new messages)** | Added thumbs up/down `feedback-btns` div to `messageContent` template in main submit handler |
| 4 | **Feedback buttons (after edit-regenerate)** | Same fix applied to `handleEditSubmit()` response template |
| 5 | **Styled error messages** | All `<p style="color:red;">` replaced with `<div class="error-message">` component; CSS added to `ui-overhaul.css` |
| 6 | **429 in main submit** | Upgraded to use `showCopyToast()` instead of inline error message in chat |
| 7 | **Voice `alert()` → toast** | `alert()` for mic denied, network error, and unsupported browser replaced with `showCopyToast()` |
| 8 | **File upload `alert()` → toast** | `alert()` for max file count replaced with `showCopyToast()` |
| 9 | **Milestone completion toast** | `result.progress.recentlyCompleted` now fires `showCopyToast()` per milestone |

---

### 8. CSS / UI Improvements — March 22, 2026
**Files changed:** `chat-interface.css`, `ui-overhaul.css`, `tutor_mysql.js`

| # | Priority | Change |
|---|---|---|
| 1 | 🚨 Critical | **CSS variable consolidation** — `chat-interface.css` now uses `var(--bg-card, white)` throughout instead of competing variable names |
| 2 | 🚨 Critical | **Hardcoded `white` fixes** — `.sidebar-toggle`, `.icon-btn`, `.conversation-item`, `.suggestion-chip`, `.quick-action-card` all migrated to `var(--bg-card, white)` |
| 3 | ⚠️ Mobile | **Safe-area padding** — `.chat-content` mobile `padding-bottom` updated to `calc(160px + env(safe-area-inset-bottom, 0px))` |
| 4 | ⚠️ UX | **Skeleton loader** — `loadChatHistory()` now shows 4 shimmer skeleton bars while waiting for the server; cleared on success/error |
| 5 | ⚠️ Readability | **Message max-width** — `.message.ai .message-content` capped at `min(720px, 85%)`, `.message.user` at `min(600px, 75%)` |
| 6 | ⚠️ Accessibility | **`:focus-visible` states** — Added for `.history-item a`, `.new-chat-btn`, `.edit-save-btn`, `#ai-submit-btn`, `.action-pill-btn` |
| 7 | 💅 Polish | **Suggestion chip truncation** — `max-width: 200px; text-overflow: ellipsis` added |
| 8 | 💅 Polish | **Dark mode dotted pattern fix** — `body.dark-mode .chat-main::before` override uses white rgba dots at 0.8 opacity |
| 9 | 💅 Polish | **`.scroll-to-bottom-btn` CSS** — Full styled class added with hover lift + purple glow shadow |
| 10 | 💅 Polish | **Skeleton `@keyframes shimmer`** — Animated gradient shimmer for history skeleton items |
  
---  
  
### 9. Mobile Navigation UI & UX Overhaul -- March 23, 2026  
**Objective**: Improve accessibility, clarity, and safety of the mobile navigation experience. 
**Files changed**: ui-overhaul.css, chat-interface.js, tutor_mysql.php, chat-new.php 
| # | Item | Change | 
| 1 | Navigation Labels | Added text labels to mobile drawer icons via CSS ::after and data-name attributes. | 
| 2 | Logout Safety | Styled logout button red and used margin-left: auto to isolate it from other icons. | 
  
---  
  
### 9. Mobile Navigation UI & UX Overhaul -- March 23, 2026  
**Objective**: Improve accessibility, clarity, and safety of the mobile navigation experience. 
**Files changed**: ui-overhaul.css, chat-interface.js, tutor_mysql.php, chat-new.php 
| # | Item | Change | 
|---|---|---| 
| 1 | Navigation Labels | Added text labels to mobile drawer icons via CSS ::after and data-name attributes. | 
| 2 | Logout Safety | Styled logout button red and used margin-left: auto to isolate it from other icons. | 
| 3 | History Empty State | Added a PHP conditional to show a No conversations yet message when history is empty. | 
| 4 | Screen Resilience | Added max-width and flex-wrap to the nav drawer to prevent overflows on small phones. | 
| 5 | Smart Pulse Dot | Hidden the .nav-pulse attention dot for users who have already completed onboarding. | 
| 6 | Overlay Dimming | Integrated .sidebar-overlay.active backdrop when the mobile history tray is opened. | 
| 7 | Tap Feedback | Added scale and opacity transforms to the mobile logo link for tactile response. | 
| 8 | Parse Error Fixes | Resolved unintended PHP syntax errors from initial script rollout. | 

---

### 10. Dashboard & Layout Overhaul -- March 24, 2026
**Objective**: Fix mobile scrolling issues and completely revamp dashboard analytics so charts display meaningful session data.
**Files changed**: `ui-overhaul.css`, `api/analytics.php`, `server_mysql.php`, `dashboard.php`

#### Solved Problems:
- **Mobile Flexbox Scrolling**: iOS Safari wasn't allowing the mobile history tray to scroll because its `flex: 1` child wasn't explicitly bounded. Fixed by adding `max-height: 100%`, `position: relative`, and `padding-bottom: 30px` to `.history-tray-content`.
- **Database Progress Flatline (0%)**: `$hybridProgress` was being calculated logically but never actually saved to the database. We injected a PDO `UPDATE` query right before the response generation in `server_mysql.php` to permanently store `progress` and `context_data`.
- **Broken Streak Math**: Fast learners passing multiple sessions in the same day were accidentally killing their own streak loops (`$diff === 0`). We tweaked `analytics.php` to gracefully handle `diff === 0` without breaking the consecutive day counter.
- **Useless Subject Extraction**: We cleaned up the topic generator in `analytics.php` with a stop-word exclusion array so "help me with math" becomes "Math" instead of "Help Me With".
- **Dashboard UI Lag & Bugs**: The dashboard now supports seamless CSS variable theming (`var(--text-primary)`), features two new metrics cards ("Active Days" & "Milestones"), dynamic `Chart.js` rendering for the "How You Study" distribution, minimum-time loader promises (to stop flickering), and a smart `MutationObserver` that instantly syncs JS charts to Dark Mode toggles.

---

### 11. RAG Automation & Topic Tagging -- March 24, 2026
**Objective**: Fix RAG so it works on the very first message for any topic, not just after a resource has already been mentioned and stored.
**Files changed**: `api/knowledge.php`, `server_mysql.php`, `api/learning_strategies.php`, `migrations/004_add_topic_to_knowledge_base.sql` (new)

#### Root Cause:
RAG only retrieved what was already stored. The first time a topic came up, the knowledge base was empty, so the AI got no context. Only subsequent conversations (after `processResourceMention()` had seeded content) benefited from retrieval.

#### Fixes:
- **Proactive seeding** (`server_mysql.php`): After `retrieveRelevant()` returns empty, automatically calls `searchAndStore()` to seed the KB from a live web search, then immediately retries retrieval — all within the same request.
- **`searchAndStore()` method** (`api/knowledge.php`): New method that runs a SerpAPI search, uses snippets first (falls back to scraping), chunks content, generates embeddings, and stores — with URL deduplication and 100ms rate limiting.
- **`topic` column** (`api/knowledge.php`, migration): Added `topic VARCHAR(100) DEFAULT 'general'` to `knowledge_base`. `storeKnowledge()` accepts an optional `$topic` param.
- **Topic boost in retrieval** (`api/knowledge.php`): `retrieveRelevant()` applies a 1.15× similarity boost to `learning_strategies` chunks so they surface more reliably.
- **Learning strategies seeder tagged** (`api/learning_strategies.php`): All `storeKnowledge()` calls now pass `'learning_strategies'` as the topic.

**Migration run:** ✅ `004_add_topic_to_knowledge_base.sql` applied.

---

### 12. Pomodoro Timer + Active Recall Quiz -- April 15, 2026
**Objective**: Build a Pomodoro study timer that auto-triggers an AI-graded active recall quiz when the session ends, adapting question type to difficulty mode.

**Files changed/created**:
- **New**: `migrations/005_add_pomodoro_recall_tables.sql` — `pomodoro_sessions` + `recall_quizzes` tables
- **New**: `api/quiz.php` — Three actions: `generate` (AI question from recent messages), `grade` (AI scoring), `save_session` (persist completed session)
- **Modified**: `tutor_mysql.php` — Pomodoro timer widget in header, Active Recall modal HTML, `window.TutorMindUser` JS data injection (knowledge_level, education_level, field_of_study)
- **Modified**: `ui-overhaul.css` — Timer pill + SVG ring panel + quiz modal styles (dark mode, mobile responsive)
- **Modified**: `tutor_mysql.js` — `PomodoroManager` class (countdown, SVG ring, start/pause/reset, fire-and-forget session save) + `QuizManager` class (modal lifecycle, question display, answer submission, score badge)

**All changes applied to both `tutor_mysql.php` (root) and `chat_area_bundle/`.**

#### How It Works:
1. Clock button in header shows live countdown; click to expand panel with Duration + Quiz Mode selectors
2. When timer hits 0 → `PomodoroManager.onComplete(mode)` fires → `QuizManager.start(mode)`
3. AI messages in chat **blur** while quiz is active
4. `api/quiz.php?action=generate` fetches last 6–8 AI messages, calls Gemini 2.5 Flash to produce a question adapted to mode:
   - **Gentle** → recognition (multiple choice)
   - **Standard** → cued recall (short answer)
   - **Challenge** → free recall or application
5. `api/quiz.php?action=grade` scores the answer (recognition = exact match; others = Gemini grading 0–1)
6. Score badge shown (green ≥75%, amber ≥40%, red <40%); messages unblur on dismiss
7. Console shortcut for testing: `window.quizManager.start('standard')`

**Prerequisite**: Active conversation with ≥2 AI responses. Migration `005` must be run before use.

**Migration run:** ✅ `005_add_pomodoro_recall_tables.sql` — applied.

