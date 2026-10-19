# Evidence — TutorMind Mobile Chat Home Screen

Surface: idle "Hey/Welcome back, {name}" screen, `tutor_mysql.php` + `assets/css/mobile.css` + `assets/css/ui-overhaul.css` (mobile-active cascade) + `assets/js/chat-interface.js`.

## A. Structural (subagent 1)

- **Interactive elements: 31** (30 always-rendered + 1 conditional `.setup-link`). Full list spans top-bar/timer (8), input bar (12), bottom nav (4), profile sheet (5), history tray (1).
- **Max nesting depth: 9** structural levels from `body` to the voice-mode-trigger `svg` (`tutor_mysql.php:288→550`).
- **Repeated patterns (3):**
  1. Two live mic/voice controls in one cluster — `.voice-btn` (`tutor_mysql.php:546`, wired via a comment reading *"FORCE SHOW button for debugging"*, `tutor_mysql.js:1169-1181`) and `#voice-mode-trigger` (`tutor_mysql.php:549`, `tutor_mysql.js:1325-1328`).
  2. Two Settings entry points that both just forward-click the same hidden button — `#bottomNavSettings` and `#profileSheetSettings` (`chat-interface.js:427-430`, `452-459`).
  3. Split "New chat" affordance by breakpoint — `#newChatBtn` (desktop, `tutor_mysql.php:313`) vs `#bottomNavNew` (mobile, `tutor_mysql.php:711`), CSS-gated (`mobile.css:25-27`).
- **Dead CSS (4 groups):** `.header-center`/`.header-right` (inline-styled, zero rules); `.tm-contact-chip*` (zero rules in the 3 in-scope files); five dead mobile.css selectors with no matching markup (`.mobile-nav-selector`, `.action-pill-btn span`, `.voice-mode-trigger-btn span`, `.input-actions-row`, `.nav-option`); and an entire self-commented **"dead block" of Mobile Level Selector CSS**, `mobile.css:632-759` (128 lines), whose own in-file comment admits it's dead.

## B. Visual (subagent 2, all INFERRED from static source — no live render)

- **Spacing scale:** mobile.css uses 9 distinct values [4, 6, 8, 10, 12, 14, 15, 16, 20]px; the un-overridden ui-overhaul.css base cascade adds [6, 8, 12, 16]px underneath — no single consistent scale, two systems layered.
- **Type scale:** 10 distinct sizes in mobile.css alone, from 9.28px to 29.6px. **Cascade conflict found:** the mobile.css greeting override (`mobile.css:478-482`, 1.85rem) is silently beaten by an `!important` rule in ui-overhaul.css (`:5697-5701`, `clamp(1.8rem,5vw,3rem)`) — the mobile-specific rule never wins, meaning mobile.css is dead code for this element specifically.
- **Color count:** 67 distinct literal color tokens in mobile.css alone (8 alpha steps of the primary purple, 10 of black/white overlay, plus 6 one-off "learning-level" accent colors that belong to a drawer not even visible on this screen), plus 6+ more literals leaking in unoverridden from ui-overhaul.css (page background gradient, `--primary`, `--secondary`, orb gradient, submit-button color). No enforced token system — colors are ad hoc per-rule.
- **Contrast — lowest found: 2.56:1** on the bottom-nav icon/label (`#94a3b8` on ~white translucent blur, `mobile.css:142/124`) — **fails WCAG AA** (needs 4.5:1 for text, 3:1 for non-text) on the primary navigation the whole redesign was built around. Placeholder text is borderline (4.83:1). Greeting and timer text are fine (14.5:1+).
- **States:** Empty state is minimal-but-present; there is dead CSS for a richer suggestion-chip empty state (`body.chat-empty .suggestion-pills-row`, `ui-overhaul.css:673-675`) that **no markup ever populates** — built and abandoned. Loading is only a generic disabled-opacity, no true loading state on this surface. Error and Success states are both **missing** on this surface. Focus and Disabled are present.

## C. Copy & Honesty (subagent 3)

- Full string inventory taken (greeting, timer, attach panel, voice, nav, onboarding CTA) — see subagent report for the complete file:line list.
- **One inflation:** "Complete setup for a **personalized** experience" (`tutor_mysql.php:573`) — no personalization is demonstrated anywhere on this screen.
- **No dark patterns found.**
- **Jargon:** the bottom-nav center logo has no accessible name (`aria-hidden="true"`, no label) despite sitting visually among 4 working nav buttons; "Encoded / n of 3" contact chip uses internal learning-science terminology; Pomodoro "Quiz Mode: Gentle/Standard/Challenge" labels don't explain the behavioral difference.
- **Label→behavior mismatch (load-bearing):** the bottom-nav center logo (`tutor_mysql.php:719-721`) is a plain `<div>` with no `href`, no button role, and **no click handler anywhere in chat-interface.js** — it visually occupies the center slot of a 5-icon nav bar (the position users expect to be most important, per the screenshot) and does nothing when tapped. Every other nav item (New, History, Settings, Profile) is correctly wired.

## D. Weight & Friction (subagent 4, method noted per item)

- **Initial JS: ~287 KB local, all render-blocking** (no `defer`/`async` on any of the 7 local scripts) — `tutor_mysql.js` alone is 149.5 KB. Plus unmeasured blocking external GSAP.
- **16 network requests on load** (7 stylesheets + 9 scripts); confirmed no active on-load `fetch`/XHR (one `fetch` call in chat-interface.js is dead/commented-out code).
- **~493 KB of synchronous CSS+JS** must load and parse before the input bar has any event listeners attached (textarea exists in markup but is inert until then) — method: sum of non-deferred local file bytes.
- **1 unguarded infinite animation** on the idle screen: `.quick-start-orb` pulses every 4s forever (`ui-overhaul.css:3576`, `@keyframes` at `:3579-3591`) with **no `prefers-reduced-motion` guard anywhere in ui-overhaul.css or mobile.css** (the only two files that matter for this screen). A second `.gradient-orb` pulse animation exists in ui-overhaul.css but its class is never used in markup — dead code, not actually running.
- **No modals/toasts/badges fire automatically on load** — both located overlays (copy-toast, recall-modal) are correctly hidden by default and gated behind explicit user/timer triggers.
