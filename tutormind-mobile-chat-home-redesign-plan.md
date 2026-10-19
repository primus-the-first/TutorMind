# TutorMind Mobile Chat Home — Redesign Implementation Plan

Source: `DESIGN-IS-2026-08-12/` Rams audit (10/30, verdict REDESIGN) + user-approved mockup artifact (dark/light before-after, https://claude.ai/code/artifact/3081ed9d-b880-4084-934b-a01ba013233c). This plan implements the approved mockup — it does not re-derive the design.

**Scope:** the idle `#welcome-screen` state in `tutor_mysql.php` (mobile viewport only) — top bar, greeting, input bar, bottom nav.
**Non-goals:** active-conversation chat view, desktop layout, any empty-state pattern beyond what the mockup shows.

---

## Phase 0 — Discovery findings (consolidated)

**Theming mechanism (do not invent a new one):**
- Dark mode = `.dark-mode` class on `<body>`, no `data-theme` attribute, no `prefers-color-scheme` media query anywhere in the codebase.
- SSR sets it from DB column `users.dark_mode` (`tutor_mysql.php:39-42` → `:288`). An inline FOUC-guard script (`tutor_mysql.php:290-298`) also reads `localStorage['tutormind-theme']` (falls back to legacy `darkMode`/`theme` keys) before any CSS/JS loads.
- Toggled by `settings.js:346-354` and `chat-interface.js:365-372`, both via `document.body.classList.toggle('dark-mode', value)`. Persisted to both localStorage and the DB column.
- Root tokens live in `ui-overhaul.css:6-25` (`--primary:#7C3AED`, `--primary-light:#A78BFA`, `--primary-dark:#5B21B6`, `--secondary:#ff6b35`, `--cta:#F59E0B`, `--bg-main`, `--bg-card`, `--text-primary`, `--text-secondary`, `--border`, etc.). Dark-mode override lives in `body.dark-mode { ... }` at `ui-overhaul.css:3201-3219`, which redefines `--bg-main`, `--bg-card`, `--text-primary`, `--text-secondary`, `--border` — **not** `--primary`/`--secondary`, which stay identical in both themes.
- `mobile.css` is **not** theme-agnostic — it already has "Section 10: Dark mode overrides" (`mobile.css:363-411`) with its own `body.dark-mode .bottom-nav-item` etc. The contrast-failing rule `.bottom-nav-item { color:#94a3b8 }` (`mobile.css:142`) already has a dark counterpart at `mobile.css:371-372`. **Any nav-color fix must land in both places, using the existing token pattern — not a new mechanism.**

**"Continue last session" data source (real, not invented):**
- `conversations` table has a genuine `progress` column (TINYINT 0-100), computed by `calculateHybridProgress()` (`api/services/comprehension_service.php:301-338`) and persisted on every message (`includes/server_mysql.php:1174-1220`). It exists but is **not currently selected** by either history query.
- Two equivalent history queries both do `SELECT id, title FROM conversations WHERE user_id=? ORDER BY updated_at DESC`: server-rendered at `tutor_mysql.php:222` (builds `$history`, used for `#mobile-history-tray` at `:691-706`) and an unused AJAX twin at `includes/server_mysql.php:100-112` (`case 'history':`). Neither selects `progress` or `context_data`.
- Topic lives inside `conversations.context_data` (JSON) as `contextData['topic']`, written at `includes/server_mysql.php:1151`, read back at `:1299`. Decode it the same way `contactState` is already decoded at `tutor_mysql.php:113-114`. `conversations.title` (AI-generated, 3-5 words, `includes/server_mysql.php:1178-1197`) is a plain column and the simpler fallback source for "Continue: `<title>`".
- `$history[0]` (already in scope at `tutor_mysql.php:224`, before `#welcome-screen` renders at `:470`) is the most-recent conversation — reachable with zero new queries once the `SELECT` is extended.

**Allowed APIs / patterns to copy, not invent:**
- CSS token pattern: extend `ui-overhaul.css:6-25` root tokens + `body.dark-mode` override block — same file, same pattern, do not add a parallel token system in `mobile.css`.
- JSON field decode: copy the `contactState` JSON-decode pattern from `tutor_mysql.php:113-114` / `includes/server_mysql.php:132-134` for `context_data`.
- Existing quick-start copy: `.goal-row` buttons already exist inside the attach panel — "Homework Help" (`tutor_mysql.php:521`), "Test Prep" (`:524`), "Explore Topic" (`:527`), "Practice" (`:530`). The home-screen quick-start grid promotes these to one tap instead of two; do not invent new copy or new categories.

**Icon requirement — custom inline SVG, not Font Awesome, for every icon this plan touches:**
- Confirmed current state: all four bottom-nav icons are Font Awesome glyphs — `<i class="fas fa-plus">` (`tutor_mysql.php:712`), `fa-history` (`:716`), `fa-cog` (`:723`), `fa-user` (`:728`) — loaded from the Font Awesome CDN stylesheet (`tutor_mysql.php:256`, per the original weight audit). The logo (`:720`) is already an SVG asset (`assets/logo-bridge.svg` via `<img>`), just not inline.
- Every icon touched by Phases 2 and 5 (bottom-nav icons, the mic/send icons, the quick-start grid's four icons, and the relocated top-bar logo) must be replaced with hand-authored inline `<svg>` markup — matching the simple stroke-based icon set already built for the approved mockup artifact — instead of a Font Awesome `<i class="fas fa-*">` class. Copy the mockup's SVGs directly rather than re-drawing from scratch.
- Font Awesome is used 43 times elsewhere in `tutor_mysql.php` (desktop sidebar, settings panel, Pomodoro controls, etc.) — outside this plan's scope. **Do not remove the Font Awesome CDN link itself** — only stop using it for the specific icons this redesign touches.

---

## Phase 1 — Delete dead weight

**Do not restyle these — delete them.** No behavior or visual change on any currently-working element; this phase is pure removal and independently shippable.

1. Delete `mobile.css:632-759` — the "Mobile Level Selector" block (self-commented as dead). Verify first: `grep -c "mobile-level-selector\|level-trigger-btn\|level-drawer\|level-option" tutor_mysql.php` must return 0 before deleting.
2. Delete the unused `.gradient-orb` / `@keyframes pulse-glow` block in `ui-overhaul.css` (~lines 616-638 per audit evidence). Verify: `grep -c "gradient-orb" tutor_mysql.php` must return 0.
3. Delete the unused `body.chat-empty .suggestion-pills-row` rule at `ui-overhaul.css:673-675` and any sibling `.suggestion-pill` rules. Verify: `grep -c "suggestion-pill" tutor_mysql.php` must return 0. (Phase 5 builds fresh, differently-structured markup for the quick-start grid — this old rule is not a base to build on.)

**Verification:** page still renders identically in the browser (nothing visible changes); `grep` confirms none of the three deleted selector families appear in `tutor_mysql.php`; `git diff --stat` shows only deletions in this phase.

---

## Phase 2 — Consolidate the bottom nav

1. In `tutor_mysql.php`, move the logo mark currently sitting in the dead `.bottom-nav-brand` div (`:719-721`) into the top bar (near `:394-398`, the `.header-center`/`.header-right` area — currently zero CSS rules, purely inline-styled, per audit evidence). Give it a real accessible name (`aria-label="TutorMind home"` or similar) if it becomes a link back to the welcome screen; otherwise mark it `aria-hidden="true"` deliberately (not by omission) if it stays purely decorative in the top bar.
2. Remove the `.bottom-nav-brand` div (`tutor_mysql.php:719-721`) entirely. Bottom nav goes from 5 slots to 4: New (`:711`), History (`:715`), Settings (`:722`), Profile (`:726`). Update the flex/grid CSS in `mobile.css` bottom-nav rules accordingly (even spacing across 4 items, not 5).
2a. Replace each remaining nav item's Font Awesome glyph (`<i class="fas fa-plus/fa-history/fa-cog/fa-user">`, `tutor_mysql.php:712,716,723,728`) with the matching custom inline `<svg>` from the approved mockup artifact — do not leave any `fas fa-*` class in the bottom nav.
3. Collapse duplicate mic controls: keep one of `.voice-btn` (`tutor_mysql.php:546`) / `#voice-mode-trigger` (`:549`) — per the approved mockup, keep the tap-to-toggle voice-mode trigger and remove the other. Remove its now-dead wiring in `tutor_mysql.js:1169-1181` (the block whose own comment reads "FORCE SHOW button for debugging") along with the removed markup.
4. Collapse duplicate Settings entry points: `#bottomNavSettings` and `#profileSheetSettings` both forward-click a hidden `#open-settings-btn` (`chat-interface.js:427-430`, `452-459`). Keep `#bottomNavSettings` as the nav-bar entry; remove `#profileSheetSettings` from the profile sheet (`tutor_mysql.php:756`) since Settings is now reachable in one tap from the nav bar without opening the sheet first.

**Verification:** every remaining bottom-nav icon has a working click handler (manually tap each in-browser); no element with `aria-hidden` sits where a functional icon used to be; profile sheet no longer duplicates the Settings entry; only one mic/voice control remains in the input bar cluster; `grep -c "fas fa-" ` restricted to the bottom-nav block returns 0.

---

## Phase 3 — Fix the CSS cascade conflict and consolidate tokens

1. Confirm the specific conflict first: `mobile.css:478-482` (greeting font-size override) is currently beaten by `ui-overhaul.css:5697-5701`'s `!important`. Resolve by raising `mobile.css`'s specificity or removing the competing `!important` from `ui-overhaul.css` for the mobile breakpoint — pick whichever leaves a single unambiguous winner, and leave a comment stating which file governs this selector on mobile.
2. Replace `mobile.css`'s ad hoc literal colors (67+ distinct hex/rgba values found in the audit) with references to the existing `ui-overhaul.css:6-25` root tokens (`var(--primary)`, `var(--text-primary)`, `var(--text-secondary)`, `var(--border)`, `var(--bg-card)`, etc.) wherever a mobile.css color is functionally equivalent to an existing token. Only keep a literal where mobile.css needs a value with no existing token — do not invent new tokens for one-off cases.
3. Pick one spacing scale and one type scale for mobile — reconcile the 9-value mobile.css spacing set and the 4-value ui-overhaul.css set the audit found layered on top of each other. Prefer keeping ui-overhaul.css's values where a mobile rule and a base rule currently disagree on the same property, since ui-overhaul.css is the file with the `!important` that's already winning in most cases (per Phase 3.1 finding) — the goal is one scale actually in force, not necessarily migrating everything into mobile.css.
4. Do NOT touch `body.dark-mode` override mechanics — extend the existing pattern (`ui-overhaul.css:3201-3219` for the base tokens, `mobile.css:363-411` "Section 10" for mobile-specific dark overrides) rather than adding a second dark-mode block anywhere.

**Verification:** the greeting element (and every other element the audit found with a losing mobile.css rule) now visibly matches the mobile.css-specified value in-browser; `grep -c "!important" mobile.css` count should not have grown; spot-check 5 random mobile.css color declarations — each should now reference a `var(--...)` token or have a documented reason not to.

---

## Phase 4 — Fix the WCAG-failing nav contrast

1. Light theme: `.bottom-nav-item { color:#94a3b8 }` at `mobile.css:142` (measured 2.56:1 against its translucent-white background at `mobile.css:124`, fails WCAG AA which needs 4.5:1 for text / 3:1 for non-text). Raise to a token value that passes — e.g. `var(--text-secondary)` (`#6b7280` per `ui-overhaul.css:6-25`, ~4.8:1 on white) or darker if the translucent background reduces effective contrast further; compute the actual ratio against the real composited background, not the nominal token color.
2. Dark theme: the existing counterpart at `mobile.css:371-372` (`body.dark-mode .bottom-nav-item { color: rgba(148,163,184,0.6) }`) — recompute contrast against its composited dark background and raise similarly if it also fails.
3. Add a distinct active-state indicator (not just a color shift) to `.bottom-nav-item.active` per the approved mockup — e.g. an underline dot or filled icon background — so active/inactive is legible even for a user who can't distinguish the color difference.

**Verification:** compute the actual contrast ratio for both themes' final nav color against their real composited backgrounds (not the nominal hex) — both must be ≥4.5:1 for the label text, ≥3:1 for the icon. Screenshot both themes and check the active-state indicator is visible without relying on color alone.

---

## Phase 5 — Populate the empty state

**Backend (small, additive — no schema change):**
1. Extend the `SELECT` at `tutor_mysql.php:222` (and the equivalent at `includes/server_mysql.php:103`, `case 'history':`, for consistency even though it's currently unused by the mobile tray) to also select `progress` and `context_data`.
2. Decode `context_data` the same way `contactState` is decoded at `tutor_mysql.php:113-114` — extract `topic`. If `topic` is absent or decode fails, fall back to the `title` column (already selected).
3. `$history[0]` (already in scope before `#welcome-screen` renders) is the most-recent conversation. Handle `count($history) === 0` explicitly — this is a real first-time-user case, not an edge case to skip: the welcome screen must render sensibly with no Continue card in that case (quick-start grid alone, matching the approved mockup's spirit for a first-time state — do not invent a different first-time layout).

**Frontend (`tutor_mysql.php`, `mobile.css`):**
4. Add the "Continue" card to `#welcome-screen` per the mockup: label, `<topic or title>`, and a progress bar driven by the real `progress` value (0-100) — do not fabricate a duration/time-remaining figure; the mockup's "12 min left" was illustrative, not a real field this codebase has. Show percentage or a plain resume affordance instead.
5. Add the 2×2 quick-start grid per the mockup, with the four entries wired to the same handler/destination as the existing `.goal-row` buttons (`tutor_mysql.php:521,524,527,530`) currently nested inside the attach panel — do not duplicate their copy with new independent behavior; promote the existing affordance rather than forking it. Use the custom inline SVG icons from the mockup for each of the four chips (homework/test-prep/explore/practice) — check whether the attach panel's `.goal-row` buttons currently use Font Awesome glyphs too, and if so leave those alone (out of scope, attach panel isn't part of this redesign) while the new grid gets its own inline-SVG icons.
6. Wrap `.quick-start-orb`'s `quick-start-pulse` animation (`ui-overhaul.css:3576`, `@keyframes` at `:3579-3591`) in `@media (prefers-reduced-motion: reduce) { .quick-start-orb { animation: none; } }` (or gate the `animation` declaration itself behind `@media (prefers-reduced-motion: no-preference)`) — this is the only currently-unguarded auto-running animation on the surface per the audit.

**Verification:** with a seeded test user that has ≥1 prior conversation, the Continue card shows a real topic/title and a progress bar matching the DB value; with a fresh user (0 conversations), the welcome screen renders cleanly with no Continue card and no broken layout; the orb animation stops when the OS/browser "reduce motion" preference is enabled (test via browser devtools emulation); all four quick-start grid taps land on the same destination as their attach-panel `.goal-row` counterparts.

---

## Phase 6 — Verification pass

1. **Contrast:** re-run the WCAG check from Phase 4 on the final shipped colors, both themes.
2. **No dead click targets:** tap every visible element on the idle screen (both themes); nothing that looks interactive should be a no-op.
3. **No duplicate controls:** confirm exactly one mic control and one Settings entry point remain.
4. **Zero-history state:** confirm a fresh account renders correctly (Phase 5.3).
5. **Weight:** re-measure synchronous CSS/JS bytes for this screen (`wc -c` on the same file set the original audit measured) — should be lower than the ~493KB baseline given Phase 1's deletions, not higher.
6. **Motion:** confirm `prefers-reduced-motion: reduce` stops all idle-screen animation.
7. **Scope check:** `git diff` touches only `tutor_mysql.php`, `mobile.css`, `ui-overhaul.css`, `chat-interface.js`, `tutor_mysql.js`, and the two history-query call sites — no changes to the active-conversation view or desktop-only selectors.
8. **Icons:** confirm zero `fas fa-*` classes remain on the bottom nav, mic/send controls, quick-start grid, or relocated top-bar logo — all replaced with custom inline SVG; confirm the Font Awesome CDN link itself is still present and unaltered (still needed by the 43 other usages elsewhere on the page).
