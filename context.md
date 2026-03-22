# TutorMind Development Context

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
