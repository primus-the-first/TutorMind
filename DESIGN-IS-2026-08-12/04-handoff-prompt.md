/make-plan Redesign TutorMind's mobile chat home screen (the idle "Welcome back, {name}" screen in `tutor_mysql.php` before a conversation starts — top bar, greeting, input bar, bottom nav). Current design failed audit at 10/30 with critical gaps across nearly every principle (no principle scored above 2/3).

Verdict paragraph (quoted from 03-verdict.md):
> TutorMind's mobile chat home screen scores 10/30 against the Rams principles, with no principle above a 2 — the prior "mobile redesign" pass (commit 90f83b1) changed the shell (hamburger → bottom nav) without resolving the underlying accretion problem: two competing CSS scales fighting over the same elements, ~130 lines of admitted-dead CSS still shipping, duplicated affordances (two mic buttons, two Settings entry points, a breakpoint-split New-chat button), a decorative element (the pulsing glow orb) that dominates an otherwise empty screen, and a centerpiece nav icon that looks tappable and isn't.

Why redesign and not refine: total is well under the REFINE threshold (20/30), and the failures are structural, not cosmetic — dead CSS actively shadowing live CSS, a broken affordance sitting in the most visually prominent nav position, and Error/Success states missing entirely. A targeted style pass won't fix a screen where two stylesheets are fighting each other.

Preserve from current design:
- Brand tokens: primary purple `#7C3AED` / `--primary-dark #5B21B6`, dark theme, "TutorMind" arch logo mark.
- The bottom-nav pattern itself (New / History / [center] / Settings / Profile) as the structural replacement for the old hamburger drawer — the *position* of this pattern is sound per prior project history; only its execution (dead center icon, contrast failure, duplicate Settings target) needs fixing.
- The mobile profile sheet (slide-up from bottom, `tutor_mysql.php:733-773`) as the settings/profile pattern — keep the mechanism, fix its one duplicate entry point.

Discard (structural patterns causing the failures):
- The entire "Mobile Level Selector" dead CSS block, `mobile.css:632-759` (128 lines, self-commented as dead, no matching markup anywhere). Caused failure on principle #10.
- The unused `.gradient-orb` pulse animation and unused `.suggestion-pills-row` CSS in `ui-overhaul.css` (built, never wired to markup — CSS exists for a richer empty state that was never populated). Caused failure on principle #8 and #10.
- Two independently-cascading stylesheets governing the same mobile elements (`mobile.css` + `ui-overhaul.css` mobile-media-query rules), which currently silently conflict — e.g. mobile.css's own greeting-size rule (`mobile.css:478-482`) is dead, beaten by an `!important` in `ui-overhaul.css:5697-5701`. Caused failure on principle #3.
- The unlabeled, unwired `.bottom-nav-brand` center nav icon (`tutor_mysql.php:719-721`) — a div with no href, no role, no click handler, sitting in the nav bar's most prominent position. Caused failure on principles #4 and #6.
- Duplicate voice/mic controls in one cluster (`.voice-btn` + `#voice-mode-trigger`, `tutor_mysql.php:546`/`549`) and duplicate Settings entry points (`#bottomNavSettings` + `#profileSheetSettings`, both forwarding to the same hidden button). Caused failure on principles #2 and #10.

Top 5 moves from the audit (verbatim):
1. #10 (as little design as possible): Delete, don't restyle, the dead weight first — remove the 128-line dead Mobile Level Selector block, the unused `.gradient-orb` pulse and unused `.suggestion-pills-row` CSS, and collapse the duplicate voice controls and duplicate Settings entry points down to one each. Evidence: `mobile.css:632-759`; `ui-overhaul.css` (.gradient-orb, .suggestion-pills-row); `tutor_mysql.php:546,549`.
2. #4/#6 (understandable/honest): Fix or remove the dead center nav icon. Either wire `.bottom-nav-brand` to something real (home/new-chat) or strip it from the tab bar. Evidence: `tutor_mysql.php:719-721`.
3. #3 (aesthetic): Collapse to one spacing/type/color system and fix the cascade bug so mobile.css actually wins on mobile. Consolidate 9+ spacing values, 10+ type sizes, and 67+ literal colors down to a small token set. Evidence: `mobile.css:478-482` vs `ui-overhaul.css:5697-5701`.
4. #3/#4 (aesthetic/understandable): Fix the WCAG-failing bottom nav contrast — `.bottom-nav-item` text/icon color `#94a3b8` on its translucent background computes to 2.56:1, failing AA on the primary navigation surface. Evidence: `mobile.css:142,124`.
5. #5/#9 (unobtrusive/environmentally friendly): Give the idle screen actual content instead of empty space under a permanently-pulsing decorative orb — populate the suggestion-chip empty state (CSS already exists, just never wired) with recent chats or quick-start prompts, and wrap the orb's animation in a `prefers-reduced-motion` guard (none exists in `ui-overhaul.css` or `mobile.css` today). Evidence: `ui-overhaul.css:673-675` (unused chips CSS); `ui-overhaul.css:3576-3591` (unguarded orb animation).

Redesign principles in priority order:
1. #2 Useful — one clear path to typing/speaking a question, zero duplicate/decoy controls on the way there.
2. #4 Understandable — every icon-only control has a real accessible name; nothing in the nav looks tappable unless it is.
3. #10 As little design as possible — before adding anything, delete every dead CSS block and duplicate affordance found in this audit; the redesign should ship a net-smaller mobile.css, not a bigger one.

Constraints: dark theme + purple brand tokens above must survive; stack is PHP server-rendered `tutor_mysql.php` with vanilla CSS/JS (`mobile.css`, `chat-interface.js`) — no framework introduction; dev branch is production, so changes must be shippable incrementally rather than as one giant cutover; ~493KB of synchronous CSS/JS currently blocks interactivity — new work should not add to that budget, and removing the dead CSS in move #1 should shrink it.

Deliverables for the plan:
- New information architecture for the idle screen (not derived from the current dead-CSS-laden version) — what fills the empty space below the input bar
- New primary flow wireframe (low-fi, labeled), compared side-by-side to the current screenshot state
- Consolidated token decisions: one spacing scale, one type scale, a capped color palette (replacing the 67+ ad hoc literals)
- States checklist for this surface: empty (populated, not just greeting+glow), loading, error, success, focus, disabled — Error and Success currently don't exist and must be designed
- A concrete plan for removing every dead/duplicate item listed above, sequenced so nothing breaks mid-migration (dev branch is live production)
- Migration path: since dev IS production per project history, this should land in stages that each leave the screen in a shippable state, not one big-bang PR
- Cutover criteria: when the old `mobile.css` Mobile Level Selector block and the ui-overhaul.css mobile-media-query rules currently fighting it are considered fully retired

Anti-patterns to guard against (specific to REDESIGN):
- Porting the old dead-CSS-laden structure under new colors/spacing — this must actually delete, not just restyle
- Keeping both the old and new empty-state treatments behind a flag indefinitely
- Redesigning to chase a trend (this audit already flagged glassmorphism + gradient-orb-hero as dated markers on principle #7 — don't reintroduce them in new form)
- Treating the Preserve list as optional — brand tokens, bottom-nav position, and profile-sheet mechanism must survive this pass
