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

---

## Progress Log - April 20, 2026

### 1. AI Response Truncation Fix (Code Blocks Missing)
**Problem:** AI responses were cut short — full explanations with code examples never appeared. Code blocks were consistently absent from rendered output.
**Root Cause:** `server_mysql.php` only read `parts[0]` from the Gemini API response. Gemini 2.5 Flash prepends a hidden thinking/reasoning part at `parts[0]` (flagged `"thought": true`); the actual answer lives in `parts[1+]`.
**Fix (`server_mysql.php`):**
- Changed from reading `$responseData['candidates'][0]['content']['parts'][0]['text']` directly
- Now iterates all parts, skips any with `!empty($part['thought'])`, and concatenates remaining text parts
- Result confirmed working: full multi-paragraph responses with fenced code blocks returned correctly

### 2. Google Sign-In Fix (Redirect Showing Raw JSON)
**Problem:** Clicking "Sign in with Google" redirected to `auth_mysql.php` and displayed raw JSON instead of completing the login.
**Root Cause:** Missing `exit;` after all 5 `header("Location: ...")` calls in `auth_mysql.php`. PHP continued executing past the redirect, appending JSON output that confused the browser.
**Fix (`auth_mysql.php`):** Added `exit;` after every redirect in the Google OAuth flow (no-credential error, invalid token, existing user success, new user → onboarding, server error).
**Status:** ✅ Fixed and confirmed working on production.

### 3. Conversation History Fix
**Problem:** After logging in normally (username/password), the sidebar showed no conversation history.
**Status:** ✅ Fixed and confirmed working on production (root cause was related to the above fixes affecting session/redirect flow).

### 4. Server Hardening (`server_mysql.php`)
- **Fatal error handler:** Added `register_shutdown_function` at the top of `server_mysql.php` to catch PHP fatal errors (E_ERROR, E_PARSE, etc.) and return parseable JSON with a `_debug` field + `error_log` entry, instead of an empty 500 body.
- **Conditional vendor load:** Changed hard `require 'vendor/autoload.php'` to a `file_exists()` guard so simple endpoints (history, suggestions) survive if the vendor directory is missing on a deployment.
- **Parsedown fallback:** Guarded `new Parsedown()` with `class_exists()` — falls back to `nl2br` if composer packages are unavailable.

---

## Progress Log - April 21, 2026

### 1. Modularization: DocumentService extracted from `server_mysql.php`
**Objective:** Begin breaking up the ~2,900-line monolithic `server_mysql.php` into focused service files in `api/services/`.
**Approach:** Procedural `require_once` split (no OOP — keeps it simple and low-risk).

**Extracted functions** → `api/services/document_service.php` (new):
- `ocrImageBasedPdf()` — OCR fallback chain orchestrator
- `ocrWithGoogleCloudVision()` — Google Cloud Vision API
- `ocrWithOcrSpace()` — OCR.space API
- `ocrWithTesseract()` — Local Tesseract + Ghostscript fallback
- `prepareFileParts()` — File parsing for PDF, DOCX, PPTX, and image upload/compression

**Files changed:**
- **New**: `api/services/document_service.php`
- **Modified**: `server_mysql.php` — removed ~520 lines of function definitions; added `require_once __DIR__ . '/api/services/document_service.php'` after `check_auth.php`

**Result:** `server_mysql.php` reduced from ~2,938 → ~2,420 lines. Syntax verified (`php -l`) on both files.

**Next in sequence:** `api/services/ai_service.php` — extract `callGeminiAPI`, `callGroqAPI`, `callDeepSeekAPI`, `generateImageWithImagen`.

### 2. Modularization: AiService extracted from `server_mysql.php`
**Extracted functions** → `api/services/ai_service.php` (new):
- `callGroqAPI()` — Groq/llama-3.3-70b-versatile, OpenAI-compatible format
- `callDeepSeekAPI()` — DeepSeek fallback, OpenAI-compatible format
- `callGeminiAPI()` — Primary Gemini 2.5 Flash with retry logic, rate limit handling, and image generation function-calling
- `generateImageWithImagen()` — Imagen 4 Ultra direct endpoint

**Files changed:**
- **New**: `api/services/ai_service.php`
- **Modified**: `server_mysql.php` — removed ~420 lines; added `require_once __DIR__ . '/api/services/ai_service.php'`

**Result:** `server_mysql.php` reduced to ~1,996 lines. Syntax verified (`php -l`) on both files.

**Next in sequence:** `api/services/response_formatter.php` — extract `formatResponse`.

### 3. Modularization: ResponseFormatter extracted from `server_mysql.php`
**Extracted functions** → `api/services/response_formatter.php` (new):
- `formatResponse()` — Converts raw AI markdown to safe HTML; protects code blocks and LaTeX from Parsedown interference, restores with proper `<pre><code>` tags and MathJax-compatible delimiters

**Files changed:**
- **New**: `api/services/response_formatter.php`
- **Modified**: `server_mysql.php` — removed ~91 lines; added `require_once __DIR__ . '/api/services/response_formatter.php'`

**Result:** `server_mysql.php` reduced to ~1,905 lines. Syntax verified (`php -l`) on both files.

**Next in sequence:** `api/services/comprehension_service.php` — extract `analyzeComprehension`, `aiComprehensionScore`, `calculateHybridProgress`.

### 4. Modularization: ComprehensionService extracted from `server_mysql.php`
**Extracted functions** → `api/services/comprehension_service.php` (new):
- `analyzeComprehension()` — Two-layer comprehension detector: regex patterns (fast, free) + AI fallback for ambiguous short messages
- `aiComprehensionScore()` — Calls Gemini 2.5 Flash with a 5s timeout to classify ambiguous replies as understood/confused/neutral
- `calculateHybridProgress()` — Weighted progress score: 70% milestones + 20% comprehension + 10% engagement

**Files changed:**
- **New**: `api/services/comprehension_service.php`
- **Modified**: `server_mysql.php` — removed ~181 lines; added `require_once __DIR__ . '/api/services/comprehension_service.php'`

**Result:** `server_mysql.php` reduced to ~1,725 lines. Syntax verified (`php -l`) on both files.

**Next in sequence:** `api/services/tutor_service.php` — extract system prompt (~250 lines) + `generateLearningOutline`, `detectMilestoneCompletion`. Highest risk — do last.

### 5. Modularization: TutorService extracted from `server_mysql.php`
**Extracted functions** → `api/services/tutor_service.php` (new):
- `generateLearningOutline()` — Calls Gemini to produce a structured JSON milestone outline for a topic
- `detectMilestoneCompletion()` — Scans AI response text for milestone title/keyword coverage; marks completed
- `buildSystemPrompt($learningLevel, $personalization_context)` — Wraps the ~250-line adaptive tutor heredoc; injects runtime variables as parameters

**Files changed:**
- **New**: `api/services/tutor_service.php`
- **Modified**: `server_mysql.php` — removed ~400 lines of functions + system prompt heredoc; replaced with `$system_prompt = buildSystemPrompt($learningLevel, $personalization_context);`; added `require_once __DIR__ . '/api/services/tutor_service.php'`

**Result:** `server_mysql.php` reduced to ~1,324 lines (from original ~2,938 — **55% reduction**). Syntax verified (`php -l`) on both files.

**Modularization complete.** All 5 services extracted:
- `api/services/document_service.php` — OCR + file parsing
- `api/services/ai_service.php` — Gemini / Groq / DeepSeek / Imagen
- `api/services/response_formatter.php` — Markdown → HTML with LaTeX + code protection
- `api/services/comprehension_service.php` — Comprehension scoring + hybrid progress
- `api/services/tutor_service.php` — Learning outline, milestone detection, system prompt

### 6. Post-Modularization Bug Fixes

**Bug 1: Undefined PHP variables in `buildSystemPrompt` heredoc**
- **Cause:** The LaTeX math examples in the system prompt heredoc (e.g. `$$a^2 + b^2 = c^2$$`) reference `$a`, `$b`, `$c`, `$x` as PHP variables. In the original inline code these were pre-declared as `null`. Inside the extracted `buildSystemPrompt()` function they were not in scope, producing PHP 8 warnings.
- **Fix (`api/services/tutor_service.php`):** Added `$a = $b = $c = $x = null;` at the top of `buildSystemPrompt()`.

**Bug 2: `MALFORMED_FUNCTION_CALL` → "AI returned an empty response"**
- **Cause:** `callGeminiAPI` injects a `generate_image` tool declaration into every Gemini request. On conversations involving code (e.g. Python `cmath` examples), Gemini 2.5 Flash occasionally misfires the tool — passing the code block as the image prompt argument. Gemini returns `finishReason: MALFORMED_FUNCTION_CALL` with no content, which hits the "empty response" error path.
- **Fix (`api/services/ai_service.php`):** Added a `MALFORMED_FUNCTION_CALL` check immediately after decoding the Gemini response. When detected, unsets `tools` from the payload and retries once — Gemini then responds normally as plain text.
- **Files changed:** `api/services/ai_service.php`, `api/services/tutor_service.php`

---

## Progress Log - April 22, 2026

### 1. Directory Structure Cleanup & API Modernization
**Objective:** Finalized the TutorMind architectural modernization by separating logic, assets, and entry points.
- **Actions:**
  - Migrated all core backend logic and configuration to the `includes/` directory.
  - Organized frontend assets into structured `assets/css/` and `assets/js/` folders.
  - Moved API services to `api/services/` for better separation of concerns.

### 2. Path Resolution & AJAX Endpoint Fixes
**Problem:** Moving files caused fatal 500 errors (broken `require_once` chains) and AJAX 404 errors (JavaScript looking in the wrong place for endpoints).
**Fixes:**
- **Backend:** Updated all `require_once` paths to use absolute resolution via `__DIR__` (e.g., `__DIR__ . '/../api/services/ai_service.php'`).
- **Frontend:** Replaced hardcoded path-splitting logic in `tutor_mysql.js` with a robust `getBasePath()` helper function to ensure API calls always resolve to the correct root-relative URL.
- **Files changed:** `includes/server_mysql.php`, `includes/db_mysql.php`, `assets/js/tutor_mysql.js`.

### 3. Production Deployment & Asset Loading Fixes
**Problem:** The production server failed to load styles and scripts (`MIME type ('text/html')` error) and crashed on 3D animations (`THREE is not defined`).
**Fixes:**
- **Git Sync:** Committed and pushed the updated HTML/PHP files which had stale root-level asset references.
- **Three.js Dependency:** Added the missing Three.js CDN import to `index.html` to fix the landing page particle animation.
- **Config Recovery:** Manually restored the gitignored `config.ini` on the Namecheap server to reconnect the database.

### 4. UI Resilience & Formatter Improvements
- **Avatar Fallback:** Implemented an `onerror` handler for Google profile pictures to show user initials if the external image service is rate-limited (HTTP 429).
- **Code Block Protection:** Fixed a bug where Parsedown mangled code block placeholders (`@@PROTECT_N@@`) during voice mode. Changed tokens to a safe `XPROTECTX0XPROTECTX` format and patched the duplicate formatter in `chat_area_bundle/server_mysql.php`.

**Status:** ✅ Architecture is now modular, paths are stable across local/prod, and critical rendering bugs are resolved.

### 5. Analytics Dashboard Revamp — April 22, 2026
**Objective:** Revamp `dashboard.php` from a minimal stat/chart page into a full analytics dashboard with a sidebar layout, activity heatmap, per-subject progress tracking, quiz performance, and focus time sections.

**Files changed:**
- **Modified**: `api/analytics.php` — Extended to return 5 new top-level keys alongside existing ones
- **Modified**: `dashboard.php` — Complete rewrite (layout, CSS, HTML, JS)

#### `api/analytics.php` — New data keys
| Key | Source | Description |
|---|---|---|
| `trends` | `conversations` (prev period) | Previous-period stats (`prevTotalSessions`, `prevActiveDays`, `prevAvgProgress`, `prevTopicsStudied`) for ↑↓ KPI badges; `null` when period = "all" |
| `heatmap` | `conversations` (last 365 days) | `{ "YYYY-MM-DD": count }` map for the activity calendar |
| `subjectProgress` | `conversations.context_data` (PHP-side) | Per-topic `completionPct` derived from `outline.milestones[]` completed/total; falls back to avg session `progress`; sorted by completionPct desc, top 10 |
| `quizStats` | `recall_quizzes` | `overallAvg` (%), `totalAnswered`, `scoreOverTime[]`, `byType[]` (avg score per question type), `recentQuizzes[]` |
| `pomodoroStats` | `pomodoro_sessions` | `totalMinutes`, `completedSessions`, `completionRate`, `modeDistribution[]`, `focusOverTime[]` |

Session fetch limit raised from 50 → 200 to support subject aggregation.

#### `dashboard.php` — New layout & sections
**Layout:** Fixed 240px sidebar (collapses to hamburger slide-in drawer on mobile ≤900px) + scrollable main area. IntersectionObserver highlights the active sidebar nav item as user scrolls.

**Sections (5, single-page with smooth-scroll anchors):**
1. **Overview** — 8 KPI cards with ↑↓ trend badges vs previous period (Study Sessions, Topics, Avg Progress, Streak, Active Days, Milestones, Focus Time, Quiz Avg) + GitHub-style 52-week activity heatmap with hover tooltips and month labels
2. **Learning** — Progress over time (line), Topics breakdown (doughnut), Study Goals (bar), Recent Sessions list with goal badges and progress bars
3. **Subjects** — Per-topic progress cards: colored bar (red <40%, amber 40–70%, green >70%), `sessions · X/Y milestones` sub-line, sorted by completion
4. **Quizzes** — Avg score / total answered / best type mini-stats; score over time (line); score by question type (bar); recent quizzes list with score badges (color-coded) and type tags
5. **Focus Time** — Total focus time / completed sessions / completion rate mini-stats; daily focus minutes (bar); mode distribution doughnut (gentle/standard/challenge)

All sections have graceful empty states with "Start a Session" CTAs. Charts rebuild on dark mode toggle via `MutationObserver`. No new JS libraries — Chart.js (already loaded) + vanilla CSS/JS for the heatmap.

---

### 13. Neobrutalist UI Refinement & Custom Components — April 22, 2026
**Objective:** Finalize the visual identity with high-fidelity neobrutalist tokens and custom UI components to move away from "default" or "AI-generated" aesthetics.

**Files changed:**
- **Modified**: `dashboard.php` — Custom dropdown, updated SVG icon library, refined chart styles, and sidebar typography.
- **Modified**: `assets/css/ui-overhaul.css` — Standardized neobrutalist tokens (borders, shadows, fonts).

#### Design System Updates
| Element | New Specification | Rationale |
|---|---|---|
| **Headings** | `Funnel Display` (Bold/Extrabold) | Strong geometric presence, high-impact branding. |
| **Body** | `Outfit` (Regular/Medium) | Clean, geometric legibility for data-heavy views. |
| **Borders** | `2px solid #1a1a2e` | "Ink" borders provide a physical, hand-drawn structure. |
| **Shadows** | `3px 3px 0 #1a1a2e` | Hard, non-blurred offset shadows for depth without fluff. |
| **Corners** | `6px` radius | Sharper than pills, softer than 0; balances "brutal" with "modern." |

#### Key Technical Achievements
1. **Bespoke Icon Library (`ICO`)**:
   - Replaced all "AI-ish" icons with a custom geometric SVG library.
   - **Rules**: `stroke-width: 2`, `square linecaps`, `miter joins`, and purely primitive shapes (`<rect>`, `<line>`, `<polygon>`). 
   - Zero gradients or decorative opacity fills in the icons themselves.
2. **Custom Dropdown System**:
   - Replaced native `<select>` with a bespoke HTML/CSS/JS dropdown component.
   - Solves the styling limitations of browser defaults while remaining sync'd with the original data-fetching logic.
   - Features "lift-on-hover" and "pop-out" animations consistent with the card system.
3. **"Living" Data Visualizations**:
   - Refined `Chart.js` configs with high-tension curves (`0.4`), staggered loading animations, and semantic gradients.
   - Replaced muddy 3D shadows with high-contrast "ink" borders for a cleaner neobrutalist aesthetic on bar and pie charts.
4. **Sidebar Branding**:
   - Applied `Funnel Display` to navigation items and section labels.
   - Unified interactive states across the dashboard (transform shifts + shadow depth changes).

---
*Status: Visual identity finalized. Dashboard components are now highly bespoke and deviate significantly from generic UI patterns.*

---

### 14. Performance Optimization & Query Combination — April 23, 2026
**Objective:** Dramatically reduce database load for the analytics dashboard and improve text asset loading speeds.

**Files changed:**
- **Modified**: `api/analytics.php` — Completely refactored to replace 11 independent database queries with 6 unified data-fetching queries, moving aggregation logic (GROUP BY, SUM, averages) to PHP arrays. Also added strict parameter binding (`?`) for dynamically calculated date intervals.
- **Modified**: `.htaccess` — Added a `<IfModule mod_deflate.c>` block configuring Gzip compression (`AddOutputFilterByType DEFLATE`) for HTML, CSS, JS, XML, JSON, and web fonts to significantly decrease payload sizes and improve frontend load times.

---

### 15. Theme Synchronization & FOUC Prevention — April 24, 2026
**Objective:** Eliminate the Flash of Unstyled Content (FOUC) across all authenticated pages and implement robust, bi-directional theme synchronization between the user's device (`localStorage`) and the database.

**Files changed:**
- **Modified**: `tutor_mysql.php`, `dashboard.php`, `admin/feedback.php`, `onboarding.php`, `onboarding-new.php`, `chat-new.php` — Implemented server-side rendering (SSR) of the `dark-mode` class on the `<body>` tag by fetching the `dark_mode` preference from the database, eliminating visual flashing on page load.
- **Modified**: `login.php`, `register.php`, `auth_mysql.php` — Implemented bi-directional theme sync. `register.php` now passes the local device theme to the backend to set the initial account preference. `login.php` retrieves the database preference upon authentication and instantly syncs it to `localStorage`, overriding legacy keys (`darkMode` or `theme`) and strictly enforcing the unified `tutormind-theme` key.

---

## Progress Log - April 27, 2026

### 16. Dashboard Chart & Card Responsiveness
**Objective:** Make all chart containers and the Recent Sessions card fluid across all screen sizes, eliminating fixed pixel heights and horizontal overflow on mobile.

**Files changed:**
- **Modified**: `dashboard.php`

#### Changes

**Chart container heights — replaced fixed px + media queries with `clamp()`:**

- Removed three discrete size tiers (`220px` default, `200px` at ≤900px, `170px` at ≤600px for `.chart-container`; `280px`/`240px`/`200px` for `.chart-container.tall`) and two `@media` blocks.
- Replaced with continuous fluid sizing:
  - `.chart-container` → `height: clamp(160px, calc(15vw + 70px), 220px)`
  - `.chart-container.tall` (holds `#progressChart`) → `height: clamp(200px, calc(20vw + 80px), 280px)`
- Minimum floors match or exceed old mobile breakpoint values; Chart.js `maintainAspectRatio: false` ensures the canvas always fills the container.

**Session list fluid height:**
- Added `max-height: clamp(200px, calc(20vw + 80px), 280px)` to `.session-list` (matches `.chart-container.tall` range so the card stays visually aligned with adjacent charts).
- Added `overflow-y: auto; overflow-x: hidden` — vertical scroll when content exceeds height; explicit `overflow-x: hidden` prevents the implicit horizontal scrollbar that `overflow-y: auto` would otherwise enable (CSS spec side-effect).

**Overflow clipping on cards:**
- Added `overflow: hidden` to `.chart-card` — clips any canvas that briefly renders wider than its grid cell during Chart.js initialisation.
- Added `overflow: hidden` to `.sessions-card` — clips session items that would otherwise bleed past the card boundary on narrow viewports.

---

## Progress Log - May 5, 2026

### 1. Production 403 Fix — Imunify360 WAF Blocking Code Snippets

**Problem:** `POST /includes/server_mysql.php` returned 403 on production when users sent code snippets (e.g. R functions with `{}`, `function()`, `return()`). Plain text messages worked fine. After adding a ModSecurity `DetectionOnly` rule, the 403 disappeared but content was being replaced with `XPROTECTX2XPROTECTX`.

**Root Cause:** The production server (tutormind.app on Namecheap/cPanel) runs **Imunify360 WAF** at the server level — not configurable from cPanel (only ImunifyAV is visible in the user panel). Imunify360 pattern-matched code syntax in POST bodies as injection attacks. In block mode → 403. After `.htaccess` ModSecurity change → sanitization mode, replacing flagged content with placeholder.

**Fix (commit e95d217):**
- **`assets/js/tutor_mysql.js`**: Base64-encode the `question` field before sending — `btoa(Array.from(new TextEncoder().encode(question), b => String.fromCharCode(b)).join(''))`. WAF sees an opaque base64 string, not code patterns.
- **`includes/server_mysql.php`**: Decode on arrival — `base64_decode($_POST['question'] ?? '', true) ?: ''`. The `true` flag rejects malformed base64 gracefully.
- **`.htaccess`**: Added `SecRuleEngine DetectionOnly` inside `<IfModule mod_security2.c>` scoped to `/includes/server_mysql.php` — handles the standard ModSecurity layer (separate from Imunify360).

**Result:** All code languages (R, Python, SQL, JS, etc.) now pass through cleanly. Confirmed working on production.

**Note:** If Imunify360 is ever updated or reconfigured by the host to decode base64 before scanning, this workaround would break. The permanent fix is to ask hosting support to whitelist `/includes/server_mysql.php` in Imunify360.

---

## Progress Log - June 3, 2026

### 1. Chat Scroll UX Overhaul — Gemini/ChatGPT Style

**Objective:** Match the scroll behavior, auto-scroll logic, and bottom fade-out effect of Gemini/ChatGPT.

**Files changed:** `assets/js/tutor_mysql.js`, `assets/css/ui-overhaul.css`, `assets/css/mobile.css`, `assets/css/chat-interface.css`

#### Scroll Behavior (`tutor_mysql.js`)
- **Smart scroll**: tracks `userHasScrolledUp` flag — auto-scroll pauses when user scrolls up, resumes when they return to the bottom
- **Smooth scrolling**: replaced `scrollTop = scrollHeight` with `scrollTo({ behavior: 'smooth' })` throughout; initial page load keeps instant scroll
- **Scroll-to-bottom button**: floating circle button fades in (via `.visible` class + CSS transition) when user scrolls up; clicking smoothly scrolls back and resumes auto-scroll

#### Bottom Fade Effect (`ui-overhaul.css`, `mobile.css`)
The Gemini-style fade where chat content disappears before the input field.

**Final solution (after several failed attempts):**
```css
/* On .chat-content */
-webkit-mask-image: linear-gradient(to bottom, black calc(100% - 80px), transparent 100%);
mask-image: linear-gradient(to bottom, black calc(100% - 80px), transparent 100%);
```
`mask-image` on a scroll container applies to the element's **visible viewport**, not total scroll content — so `100%` always means the bottom of what's currently visible. The fade is always anchored at the bottom edge regardless of scroll position.

**Layout fix (content going behind input):**
The `.input-bar-area` is `position: fixed !important` on both desktop AND mobile (see `ui-overhaul.css` line ~6064 — "Gemini/ChatGPT-style centered input" block). This means the input always overlays chat content unless explicitly constrained.

Fix: `padding-bottom: 120px` on `.main-chat-wrapper` (desktop) and `padding-bottom: var(--mobile-chat-bottom-clearance)` (mobile). With `height: 100vh; box-sizing: border-box`, the inner content area physically ends above the fixed input. The gap between content and input shows the wrapper's background (`#FFFEF9` light / `#1a1625` dark) which matches `--chat-fade-color` — seamless transition.

Also required: `min-height: 0` on `.chat-content` to fix the flex overflow bug (`min-height: auto` default allows flex children to overflow their container).

**Mobile-specific (`mobile.css`):**
- `--mobile-input-bar-height` bumped from `72px` → `130px` (actual combined input bar height)
- `padding-bottom: var(--mobile-chat-bottom-clearance)` moved to `.main-chat-wrapper`, not `.chat-content`

**Failed approaches (don't retry):**
- `::before` overlay on `.input-bar-area` — unreliable z-index/stacking against `position: fixed` parent
- `position: sticky; bottom: 0` `::after` inside flex scroll container — appeared mid-content, not at bottom edge

---

## Design Decision Log - May 18, 2026

### Quiz Gate: Skipping Assessment When Student Hasn't Understood

**Edge case:** When a Pomodoro timer expires and the student still doesn't understand the concept, firing a quiz is counterproductive — it tests recall of material that was never encoded.

**Trigger chain for reference:**
`PomodoroManager._notifyComplete()` → `pomodoro.onComplete` (tutor_mysql.js:3231) → `QuizManager.start(mode)` → `POST api/quiz.php?action=generate` → `handleGenerate()`

**Decision: gate on contact state only, not comprehension score.**

The comprehension score was considered as a threshold signal (e.g. skip if `< 0.4`) but rejected:
- It starts at 0.5 (neutral) and only drifts on explicit signals — a silent confused student never moves it
- Ambiguous short replies ("ok", "right") can score positive when the student is actually lost
- It's cumulative across the whole session, so resolved early confusion still drags the score down at quiz time

Contact state is a stronger and more direct signal. If a student hasn't made a Build or Predict contact, they haven't actively engaged with the material — there's nothing meaningful to test recall of. This is grounded in the same learning-science logic as the Three-Contact Rule itself.

**Gate rule (implemented):**
`handleGenerate()` in `api/quiz.php` returns `not_ready: true` when two or more of the three contacts are missing (`contactState` from `context_data`). `assets/js/tutor_mysql.js` intercepts this flag and displays an encouraging nudge toast instead of opening the quiz modal.

---

> **Gap note:** the logbook wasn't kept up between this entry and August 11 — the sections below (June 7 – August 4) were reconstructed after the fact from git history rather than written contemporaneously, so they're lighter on the "why" than the surrounding entries. Reconstructed on 2026-08-11.

## Progress Log - June 7, 2026

### 1. Document Extraction Overhaul: MarkItDown + InnoDB Lock Timeout Fix
**Objective:** Replace the PHP-native document parsers (`smalot/pdfparser`, PhpWord, PhpPresentation) with MarkItDown, a Python CLI tool, for faster extraction and to stop large PPTX uploads from spiking PHP's memory limit.

**Changes:**
- New thin CLI wrapper (`markitdown_extract.py`) shells out to MarkItDown; OCR fallback retained for scanned/image-based PDFs where MarkItDown returns nothing.
- PPTX upload limit raised 10 MB → 50 MB — the old ceiling was PhpPresentation's memory constraint, which no longer applies once extraction moved to a separate Python process.
- YouTube URL detection added to the chat flow: transcripts fetched via MarkItDown and injected as context ahead of the user's question (this pipeline was replaced twice more the same day — see §4 below).
- **DB reliability**: added `pdo_retry()` (3 attempts, 150/300ms backoff) for MySQL 1205/1213 lock-timeout errors, applied to the conversation `UPDATE` in `server_mysql.php` and `session_context.php`; set `innodb_lock_wait_timeout = 30` per session so genuine deadlocks fail fast instead of hanging on the MySQL default.

**Files changed:** `api/services/document_service.php`, `includes/server_mysql.php`, `api/session_context.php`, new `markitdown_extract.py`

### 2. AI Image Fetching + Landing Page Parallax/Three.js Overhaul
**Objective:** Let the AI embed fetched images in responses, and give the marketing landing page a Three.js particle background with parallax.

**Changes:**
- `[FETCH_IMAGE:...]` markers: protected in `response_formatter.php` so Parsedown doesn't mangle them as link references before `resolveImageMarkers()` runs; usage instructions added to the AI system prompt (`tutor_service.php`).
- `.ai-fetched-image` figure styles added (light + dark mode) — `ui-overhaul.css`.
- Landing page: Three.js canvas container, hero reveal animations, `data-depth` parallax attributes on hero elements; `landing.js` rewritten with shared mouse-state tracking, lerp smoothing, and a theme-aware particle color scheme.
- Fixed a `chat_area_bundle/server_mysql.php` bug where `prepareFileParts()`'s return value wasn't iterated as an array, breaking multi-part (image-slide) responses.

**Files changed:** `api/services/response_formatter.php`, `api/services/tutor_service.php`, `ui-overhaul.css`, `landing.css`, `landing.js`, `index.html`, `chat_area_bundle/server_mysql.php`

### 3. Same-Day Production Hotfixes
Two fast-follow fixes after the above landed on production:
- **`pdo_retry()` fatal on PHP 7.x**: the `: mixed` return type hint is PHP 8.0+ only; production runs PHP 7.x, so every `server_mysql.php` endpoint 500'd. Removed the hint.
- **Missing `image_service.php`**: present locally but never committed, so every `require_once` for it fatal'd on production. Added to the repo.

### 4. YouTube Transcript Extraction: Four Iterations in One Day
**Problem:** MarkItDown's generic URL scraper (from §1) returned YouTube nav-link boilerplate instead of the actual transcript. Fixing this took several attempts against the constraints of shared hosting (Namecheap/cPanel, `exec()` often disabled or Python missing from PATH):

1. **Pure-PHP fetcher** (no exec/Python at all): `fetchYoutubeTranscript()` parses `ytInitialPlayerResponse` out of the YouTube page HTML via regex, then fetches the caption track over cURL. `server_mysql.php` tries this first, falling back to MarkItDown/Python only on localhost.
2. **Brace-counting JSON parser**: the initial regex over the full `ytInitialPlayerResponse` JSON blob was fragile against YouTube's page structure changing. Replaced with a brace-counting extractor that finds the opening `{` and walks to its matching close.
3. **cPanel Python path**: added `/opt/alt/python311/bin/python3` (where CloudLinux/Namecheap installs Python) to the front of the MarkItDown interpreter candidate list, so the exec() fallback could actually find an interpreter on production.
4. **Switched to `youtube-transcript-api`**: replaced MarkItDown's scraper entirely with this library, which hits YouTube's `timedtext` API directly and bypasses bot detection; also set the `HOME` env var in the PHP `exec()` call so `pip --user`-installed packages resolve on Python's path.

**Files changed:** `includes/server_mysql.php`, `api/services/document_service.php`

---

## Progress Log - June 9–11, 2026

### 1. PDF Parsing Fix — June 9
**Problem:** PDF uploads were failing to extract usable text for the AI.
**Fix (`api/services/document_service.php`):** Added `extractPdfWithParser()` using `smalot/pdfparser` (pure PHP, no exec) as the **first** attempt for PDFs, falling back to MarkItDown, then OCR, in that order — inverting the previous MarkItDown-first order. Pure-PHP-first avoids depending on a working Python exec() path for the common case.

### 2. Image Upload 400 (mime-type) Fix — June 11
**Problem:** Uploading a raw image (not a document) returned an API 400 error.
**Root cause:** `prepareFileParts()` returned a single associative array (`['inline_data' => [...]]`) for images instead of an array-of-parts (`[['inline_data' => [...]]]`). Every caller iterates the return value as a *list* of parts (established by the multi-part fix in §2 of June 7) — the bare associative shape broke that contract specifically for plain image uploads, producing a malformed `parts` array that Gemini rejected.
**Fix:** Wrapped the return value in an array in both `api/services/document_service.php` and the (then still-synced) `chat_area_bundle/server_mysql.php` copy.

---

## Progress Log - July 6, 2026

### 1. Onboarding: Interests Collection Wired Into the Tutor Prompt
**Objective:** Start collecting a `interests` field during onboarding and feed it into the system prompt, laying the groundwork for interests-driven personalization (used heavily by the Interactive Widgets work later this month).

**Files changed:**
- **New**: `migrations/011_add_interests.php` — adds the `interests` column
- **Modified**: `api/user_onboarding.php` (+76 lines) — collects and persists interests
- **Modified**: `assets/js/onboarding-bundle.js` (+35 lines) — new onboarding step UI
- **Modified**: `includes/server_mysql.php`, `api/services/tutor_service.php` — interests read into the prompt-building context

---

## Progress Log - July 22, 2026

### 1. Interactive Widgets Phase 1 (Brilliant-style tappable widgets)
**Objective:** Replace plain-text Socratic questions with tappable in-chat widgets, and add a Three-Contact progress indicator to the chat header.

**What shipped:** The AI can embed `tm-check` / `tm-chips` / `tm-hints` / `tm-steps` / `tm-task` / `tm-order` / `tm-cloze` fenced blocks in responses, rendered as interactive components instead of prose. A Three-Contact progress chip was added to the chat header. Interests and mental-model context (from the July 6 work) were wired into widget content generation. Prompt adherence took real iteration — few-shot examples beat described rules for getting the model to consistently choose widgets over prose, avoid supplying its own analogy before the learner's, never name the widget machinery to the student, and avoid bleeding example wording into unrelated topics.

**Files changed:** `assets/js/tm-widgets.js` (new), `assets/css/tm-widgets.css` (new), `assets/js/tutor_mysql.js`, `api/services/tutor_service.php`, `tutor_mysql.php`

*(See `docs/CLAUDE.md` memory notes / prior session context for the detailed prompt-iteration failure modes found during this build — not fully re-derivable from the commit diff alone.)*

### 2. Onboarding Update-Mode: Backfill Interests for Existing Users
**Problem:** Users who completed onboarding before the `interests` field existed (July 6 work) had no way to supply it — the tutor's interests-driven personalization couldn't reach them.
**Fix:** `onboarding.php` now detects "completed onboarding, but `interests` still NULL" and routes those users into an update-mode: just the interests-collection step, progress preloaded, a single "Save & Finish" action instead of the full wizard.

---

## Progress Log - July 27, 2026

### 1. Onboarding Bug-Fix Trio
Three issues found and fixed together on the preferences/welcome screens:

- **Silently-dead Continue button**: two independent robustness gaps could each explain "tap Continue, nothing happens, no error." (a) `showScreen()`/`updateProgressBar()` called `gsap.fromTo()`/`gsap.to()` without checking GSAP had actually loaded, unlike sibling calls in the same file — a slow/blocked CDN script throws there and silently aborts the rest of the screen transition. (b) The Continue button's "disabled" state was purely cosmetic (no `pointer-events` block), while the validation error message was never toggled by JS at all — so an incomplete attempt gave no real signal either way. **Fix:** guarded the GSAP calls with a non-animated fallback, and moved the actual validity check into the click handler so an incomplete attempt now surfaces the error message.
- **Unstyled hero-icons row**: `.hero-icons`/`.hero-icon` had zero CSS anywhere in the codebase, so the four emoji fell back to default block-level stacking — one full-width line per icon. Barely visible on wide viewports, but a wall of empty space on mobile. Fixed with a compact flex row.
- **Broken step-counter**: `wizard-progress-text`'s flex row had no width constraint on the `h2` title, so on narrow viewports the "N / 9" counter got squeezed and wrapped internally. Removed rather than fixed — it was redundant with the progress bar directly below it.

**Files changed:** onboarding JS/CSS (screen transition logic, hero-icons styling, progress-text markup)

### 2. CI/CD Pipeline: Lint + Smoke Checks, SSH Auto-Deploy on Push to `dev`
**Objective:** Catch the exact bug class from the Continue-button incident above (and similar) before they reach production, and automate deploys.

**What it does:** Triggers on push to `dev` (confirmed with the user as the actual production branch — `main` is stale/unused for deploys). Checks job runs `php -l` on every PHP file, `node --check` on every JS file, then a custom DOM-id cross-check (`scripts/ci-check-dom-ids.js`) that catches a JS file referencing a `getElementById()` id that doesn't exist yet in its paired PHP page — the exact failure mode plain syntax linting can't see, since both files are individually valid. Deploy job (gated on checks passing) SSHes in and does a hard reset to `origin/dev` rather than a plain pull, so the live tree can't be left in a partial/desynced state the way a manual pull could.

**Requires manual one-time setup outside the repo** (not achievable from this environment): GitHub Actions secrets `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`, `DEPLOY_PATH`, `DEPLOY_PORT`, and a deploy-specific SSH key added to the server's `authorized_keys`.

**Files changed:** new GitHub Actions workflow, `scripts/ci-check-dom-ids.js` (new)

---

## Progress Log - August 2–4, 2026

### 1. Scope Guardrail Restored + Analogy Text Capture — August 2
**Problem:** The SCOPE BOUNDARIES prompt section (decline non-educational requests) existed only in the old pre-refactor `chat_area_bundle` copy and never made it into the live prompt builder after the codebase was split into `api/services/*.php`.
**Fix (`tutor_service.php`):** Restored verbatim, plus an addendum for ambiguous topics (relationships, health, money, career) that have both an academic angle and a personal-advice angle — the model should engage the former and redirect away from the latter regardless of how the request is framed. Verified live: both a personal-relationship request and an unrelated harmful request are now correctly declined.
**Also (`comprehension_service.php`):** `detectContactState()` now captures the learner's analogy text verbatim (`analogy_text`) instead of just a boolean, broadens the analogy-detection regex, and keeps the *most recent* analogy rather than only the first. Backs the ACTIVE MENTAL MODEL prompt section.

### 2. AI Provider Fallback Widened — August 4
**Problem:** Gemini failures only cascaded to Groq/DeepSeek on rate-limit-style errors (429/500/503) — an invalid or expired `GEMINI_API_KEY` (400) surfaced as a hard failure instead of falling back.
**Fix (`api/services/ai_service.php`):** Widened the fallback trigger to cover auth/invalid-key errors too. Also folded in prior in-progress edits: interests-based personalization context, and review-mode pacing guidance for `test_prep` sessions.

### 3. Construction-Ownership Guardrail — August 4
**Objective:** Based on Justin Sung/HUDLE cognitive-science principles — the AI must not auto-generate mind maps, study guides, summaries, or relationship comparisons *on the student's behalf*. Manual construction is where encoding happens; handing over a finished artifact skips that step. The guardrail declines and redirects to the student building it first, offering to check their logic once they have a draft.

**Two real gaps closed via live testing:**
- The trigger only matched artifact nouns ("mind map", "study guide"), missing the identical request phrased conversationally ("how do X and Y relate," "compare X and Y") — broadened to cover the underlying *act* of organizing relationships, not just a named deliverable.
- The redirect sometimes rendered as bare prose with no follow-up ask, because the rule and its own worked example sat ~30KB apart in the prompt, letting the rhetorically-similar SCOPE BOUNDARIES decline pattern (prose-only, full stop) win out instead. Fixed by placing a compact `tm-task` template directly next to the rule and explicitly distinguishing it from a SCOPE BOUNDARIES decline: this is a handoff into engagement, not a hard stop. *(Same lesson as the widget-adherence work in July: co-locate a rule with its worked example, or a nearby rhetorically-similar rule wins.)*

**Also added:** an explicit "when direct answers ARE appropriate" carve-out (facts, confirming attempts, grading, review, time-pressure) so the guardrail doesn't over-apply; a `tm-steps` bound to one case when a mental model is active, followed by a required `tm-task` handoff; a `test_prep`-specific addendum (smaller, faster-paced construction asks, since test prep is review, not first-pass learning).

**Files changed:** `api/services/tutor_service.php`; new `tm_guardrail_test.php` dev harness simulating both scenarios through the real `formatResponse()` + `tm-widgets.js` pipeline.

---

## Progress Log - August 11, 2026

### 1. tm-check Widget: Auto-Continue, Silent Echo, Persistence Across Reload
**Problem:** Tapping the correct answer on a `tm-check` widget left the lesson stalled — the AI never continued until the student typed something. Once fixed to auto-continue, the confirmation ping showed up as a redundant duplicate chat bubble (the widget already displays the verdict). Once *that* was fixed, reloading the conversation reset every solved widget back to a fresh, tappable state and re-showed the duplicate bubble anyway, since history replay doesn't know a widget was ever silently answered.

**Fixes:**
- **`assets/js/tm-widgets.js`**: `buildCheck()` now always calls `reply()` on a correct tap (was previously opt-in via an undocumented `followup` flag the model never used) with `{ silent: true }`, so the confirmation posts through the pipeline without a visible bubble.
- **`assets/js/tutor_mysql.js`**: `onReply(text, opts)` now takes an `opts.silent` flag; the submit handler skips `addMessage('user', ...)` when set. New `extractSolvedCheckQuestion()` regex recognizes the silent echo text (`For the check "…", I chose "…" — the correct answer.`) so both `loadConversation()` (AJAX) and history replay can (a) hide the echo bubble and (b) pre-mark the matching widget as solved via a new `data.__solved` flag in `buildCheck()`.
- **`tutor_mysql.php`** (SSR): mirrors the same echo-detection in PHP, skips rendering the echo message, and injects `window.__ssrSolvedChecks` for `hydrateMessages()` to consume on initial page load.

**Result:** Correct answers now silently continue the lesson with no duplicate bubble, and re-visiting a conversation shows every previously-solved check already locked in its correct state instead of resetting.

### 2. Three-Contact Protocol: Analogy/Build/Predict Regex Was Too Narrow
**Problem:** `detectContactState()` in `api/services/comprehension_service.php` kept marking contacts as "missing" even after the student clearly made them, causing the AI to loop back and re-ask an already-satisfied task (observed live: a student answered a "recommend an algorithm for two scenarios" prompt, and the very next AI turn re-asked essentially the same question).

**Root cause:** The original patterns only matched canned phrasing — `it's like`, `reminds me of` for analogy; `i tried`, `i built` for build; `i think it would`, `that means` for predict. Real answers routinely skip those exact phrases (e.g. *"The closest would be how traffic wardens control traffic…"* for analogy; *"I'd use round robin… I'd recommend least connection…"* for build; *"…the algorithm will now reroute requests…"* for predict).

**Fix:** Added ~10 broader patterns per contact (`closest … would be/is`, `analogous to`, `it's/that's basically`, `i'd use/recommend/choose`, `would/could/might cause/lead to/result in`, etc.), verified against the actual failing transcripts. One candidate pattern (`compared to`) was tested and dropped after it false-positived on plain technical comparisons unrelated to analogy-making.

**Known limitation (not fixed, discussed but out of scope):** This is fundamentally a lexical-matching ceiling — the regex can't infer intent, only recognize phrasing. A more robust fix would have the AI tag which contact a widget is fulfilling (`"contact": "build"` in the widget JSON) and detect satisfaction structurally (assistant turn tagged X → next student reply → X satisfied) rather than re-deriving intent from freeform text, unioned with the regex as a fallback. Scoped as a larger follow-up, not attempted this session.

### 3. Toast/Error UX Overhaul
**Files changed:** `assets/js/tutor_mysql.js`

- **Queue instead of clobber**: `showCopyToast()` used a single shared `#copy-toast` element — concurrent calls silently overwrote each other before the first was ever read (e.g. multiple milestone-completion toasts firing in one response only ever showed the last one). Now queued; each message gets its own full display window.
- **Fixed 6 call sites missing a `type` argument** (defaulted to neutral `info` instead of the correct success/error/warning styling): rate-limit warning during edit-resubmit, voice-not-supported error, max-files warning, feedback thank-you/error (×2).
- **Silent failure paths got toasts**: `deleteConversation()` and `loadConversation()` previously failed with only a `console.error` — now show a generic error toast.
- **Non-429 HTTP statuses were being swallowed**: both chat-send fetches (`main submit` and `handleEditSubmit`) did `if (!response.ok) throw new Error('HTTP error! status: ' + status)` *before* reading the response body — discarding the server's actual `{success:false, error:'…'}` message (401 session-expired, 413 file-too-large, etc.) in favor of a generic "couldn't connect" bubble. Only 429 was ever special-cased. Removed the early throw so the existing `result.error` branch runs for every status, not just 200s.

### 4. Server-Side Error Sanitization + Debug Logging Hardening
**Problem:** Audited every endpoint that echoes `'error' => $e->getMessage()` (or similar) straight into a client JSON response — raw PHP/PDO exception text was reaching the browser across ~9 endpoints.

**Fixed (generic client message + `error_log()` server-side, per file):**
- `includes/server_mysql.php` — fatal-error shutdown handler (`_debug` field with file:line + raw message, unconditional, sent for *any* fatal error on *any* endpoint), `get_conversation`'s unconditional `debug` field, the main chat handler's top-level catch (shown as the AI's reply bubble), file-upload error text embedded into conversation content, and `rename_conversation` — which had **no try/catch at all**.
- `auth_mysql.php` — Google login leaked `$e->getMessage()` into **both** the JSON response and the `login?error=` redirect URL (higher-sensitivity since OAuth client errors can be more revealing than DB errors).
- `api/image.php`, `api/analytics.php` (no server log existed either), `api/session_context.php`, `api/user_settings.php` (×2), `api/user_onboarding.php` (×3).
- `assets/js/tutor_mysql.js` — `handleEditSubmit()`'s catch was displaying a raw JS `error.message` in a chat bubble; now generic.

**Audited, left alone:** `api/quiz.php`, `api/tts.php`, `api/clear_history.php`, `api/delete_account.php`, and register/login/change-password in `auth_mysql.php` — all already used safe pre-written strings. `settings.js`'s six `error.message` displays are safe *because* they only ever receive the now-sanitized server strings above (not independently hardened — inherits safety from the server-side fix). `migrations/*.php` left untouched (CLI-run admin scripts, not user-facing).

**Debug log exposure (found during the audit, more severe than the above):** `DEBUG_MODE` was hardcoded `true` in `includes/server_mysql.php`, writing `$_FILES` arrays and raw student message text to `includes/debug_log.txt` on every file-upload error. That file existed — **104KB / 3322 lines, going back months** — at a guessable, previously-unprotected URL (`.htaccess` had no `.log`/`.txt` deny rule). Set `DEBUG_MODE` to `false`, deleted the accumulated file, and added `<FilesMatch "\.(log|txt)$"> Require all denied </FilesMatch>` to `.htaccess` as a backstop.

### 5. Scroll-to-Bottom Button / Input Dimming / 404 & 403 CSS 404
**Files changed:** `assets/css/ui-overhaul.css`, `404.html`, `403.html`

- **Scroll-to-bottom button invisible**: `.scroll-to-bottom-btn` (`bottom: 20px; z-index: 50`) rendered *underneath* the fixed input bar, which sits at `z-index: 100` (desktop) / `3000` (mobile) over the same screen region. Moved to `bottom: 140px; z-index: 3001`.
- **Input field had no visual "busy" state**: it was already correctly `disabled` during every request (typed or widget-triggered), just with no styling. Added `.main-text-input:disabled { opacity: 0.5; cursor: not-allowed; }`, matching the existing submit-button disabled treatment.
- **404.html / 403.html rendered completely unstyled**: both requested their stylesheet from site-root `/landing.css`, but it lives at `assets/css/landing.css` — the CSS itself 404'd. Fixed both paths.

### 6. Chat Redesign: Flat, ChatGPT-Style Bubbles + Input Bar
**Objective:** Simplify the chat UI, which had grown visually heavy (bordered/sticker-shadow message cards, a cluttered multi-row input bar with a Tools dropdown and a Bloom's-taxonomy level picker) into something closer to ChatGPT's flat, minimal layout — reviewed first as a standalone Artifact mockup (light/dark + desktop/mobile toggle) before touching the live app.

**Files changed:** `tutor_mysql.php`, `assets/css/ui-overhaul.css`, `assets/js/tutor_mysql.js`

**Message bubbles:**
- Dropped the 2px border + sticker box-shadow card look. AI turns are now plain text on the page (no background/border), user turns are a simple filled `var(--primary)` pill. Per-message avatars hidden (`.message-avatar { display: none; }`) — cheaper than touching the three separate render paths (live typewriter, history load, SSR) that all emit avatar markup.
- Had to override a pre-existing `!important` "neo-brutalist" layer (`~5480`, `~5829`) that was still forcing a thick black/amber border and hard offset shadow onto the send button (`#ai-submit-btn`) and the voice-mode button regardless of the new flat styling, in both light and dark mode — same specificity/`!important`, later in source, to win the cascade.

**Input bar:** Collapsed the separate Tools menu (goal picker: homework help / test prep / explore / practice) and the desktop dropdown + mobile drawer Bloom's-level picker into a single **combined "+" panel** — "Add photos & files" up top, Quick Start goals below a divider. Learning Level was dropped from the panel entirely; it already has a home in Settings → Appearance (`#settings-learning-level`, synced to the same hidden `<select id="learningLevel">` used for form submission), so a second per-message picker was redundant — the AI already treats the stored level as "a starting point, adapt from the actual message" per the system prompt, not a hard directive.
- The bar is now a borderless flowing pill: `+` (files/quick-start) → text → mic + voice-mode, which fade to a send button the moment there's text (`.input-pill-row.has-text`). A `syncPillHasText()` helper is called at every place `questionInput.value` is set programmatically (mic dictation, quick-start insertion, suggestion pills, clearing after send) since none of those fire a native `input` event.
- Mobile: the panel becomes a full-width bottom sheet with a backdrop (matching the app's existing `.level-drawer` pattern) instead of a small anchored popover, with quick-start compressed into a 2×2 grid.
- Reused the exact same `promptTemplates` + `sessionContextManager.create(goal)` logic from the old Tools menu for the new quick-start buttons — only the trigger/menu chrome around it changed.

**Bugs found and fixed during implementation:**
- Double padding: `.combined-input-bar` still carried its own old outer padding (up to `8px 12px` on small phones) *underneath* the new `.input-pill-row`'s padding, making the bar look oversized on mobile. Zeroed the outer container down to `padding: 3px` — the pill row is now the only real padding source.
- `.trailing-actions` (wraps the mic/voice-mode cluster + send button) had no `display: flex` of its own, so its children fell back to inline baseline alignment instead of lining up with each other.

**Known, deliberately unresolved finding:** while chasing the `!important` submit-button conflict, found that `.main-chat-wrapper` also has an unconditional `!important` rule (`~5466`) forcing a cream (`#FFFEF9`) background with a dotted pattern in **light mode only** — a second, separate "neo-brutalist" design layer coexisting with the purple-gradient/glass system the rest of this redesign (and the `:root` tokens) assumes. Dark mode has its own consistent override so it isn't affected. Not fixed — out of scope for a chat-bubble/input-bar redesign, and unclear whether it's an intentional current design or abandoned dead code from an earlier design pass. Worth a dedicated look if light-mode visuals ever come up again.

### 7. Dead "Personalization" Menu Link
**Problem:** The "Personalization" item in the user account dropdown (`tutor_mysql.php`) was a bare `href="#"` with no `id` and no JS handler at all — unlike its siblings "Settings" and "Send Feedback," which have `id`s wired to real click handlers. Clicking it did nothing meaningful.
**Fix:** Gave it `id="open-personalization-btn"`; new handler in `tutor_mysql.js` opens the Settings modal and immediately calls `switchTab('appearance')` — Font Size, Text Legibility, and Density live there, which is what "Personalization" actually refers to in this app (confirmed with the user before landing on this vs. an initial wrong guess that it meant the onboarding/profile flow).

---
