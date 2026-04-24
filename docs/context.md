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
