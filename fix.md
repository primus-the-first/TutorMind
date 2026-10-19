# TutorMind Mobile Redesign — Bug Fixes

## Context
The mobile-first redesign plan was partially implemented before a power outage. The plan:
- Created `assets/css/mobile.css` as the authoritative mobile stylesheet
- Added bottom nav bar + mobile profile sheet to `tutor_mysql.php`
- Added `isMobile()`, `setupBottomNav()` to `assets/js/chat-interface.js`
- Disabled old 768px blocks in `ui-overhaul.css` with `@media (max-width: 0px)`

After the outage the interface was mangled. These fixes were applied:

---

## Fix 1 — History tray had no structural CSS (mobile.css)

**Problem:** The history tray's `position: fixed; transform: translateY(100%)` was in the
disabled old block. Without it the tray flowed as a flex item in the `<body>` flex row,
appearing as a panel to the right of `.main-chat-wrapper`.

**Fix:** Added full structural CSS to `mobile.css` inside `@media (max-width: 768px)`:
- `.mobile-history-tray { position: fixed; bottom: 0; transform: translateY(100%); display: flex !important; ... }`
- `.mobile-history-tray.open { transform: translateY(0); }`
- Header, content, item, and dark-mode styles for the tray

Also fixed `display: flex !important` to beat `.mobile-only { display: block !important }`.

---

## Fix 2 — Overlay pointer-events blocked close-on-tap (mobile.css)

**Problem:** Global CSS had `.sidebar-overlay.active { pointer-events: none }` so tapping
outside a sheet never fired the close handler.

**Fix:** Added to `mobile.css`:
```css
#mobile-sidebar-overlay.active {
    pointer-events: auto;
}
```
ID selector has higher specificity than the class selector in the global rule.

---

## Fix 3 — Desktop sidebar showing on mobile (mobile.css)

**Problem:** `ui-overhaul.css` line 6337 has:
```css
aside#sidebar.sidebar { display: flex !important; }
```
Specificity (element + ID + class) is higher than `.desktop-only { display: none !important }`
(one class). So the sidebar ignored the mobile hide rule.

**Fix:** Added to `mobile.css` inside `@media (max-width: 768px)` with matching specificity,
loaded after ui-overhaul.css so it wins the cascade:
```css
aside#sidebar.sidebar {
    display: none !important;
}
```

---

## Fix 4 — Input bar offset and vertically centered on mobile (mobile.css)

**Problem:** `ui-overhaul.css` line 6044 has:
```css
.input-bar-area {
    position: fixed !important;
    left: var(--sidebar-width, 300px) !important;  /* offsets for desktop sidebar */
    ...
}
body.chat-empty .input-bar-area {
    top: 50% !important;
    transform: translateY(-50%) !important;        /* vertically centers on empty chat */
}
```
On mobile the sidebar is hidden but the 300px left offset remains, and the empty-chat
centering puts the input bar in the middle of the screen instead of the bottom.

**Fix:** Added to `mobile.css` inside `@media (max-width: 768px)`. Combined selector covers
all body states so no variant can sneak through:
```css
.input-bar-area,
body.chat-empty .input-bar-area,
body:not(.chat-empty) .input-bar-area {
    position: fixed !important;
    left: 0 !important;
    right: 0 !important;
    bottom: calc(var(--mobile-bottom-nav-height) + env(safe-area-inset-bottom, 0px)) !important;
    top: auto !important;
    transform: none !important;
    z-index: 200 !important;
}
```
Also removed the now-redundant `/* Section: Input bar area left fix */` block that only
had `left: 0` without `!important`.

---

## Fix 5 — Level drawer always visible on mobile (mobile.css)

**Problem:** All level selector CSS (`.level-drawer`, `.level-trigger-btn`, `.mobile-level-selector`,
`.level-option`, per-level color vars) was inside the dead `@media (max-width: 0px)` block.
Without it the `level-drawer` had no `opacity: 0` or `position: absolute`, so it rendered
as a static block element flowing inside the input bar — showing "Understand" + all 6 icons
in-line below the textarea.

**Fix:** Ported the full level selector CSS into `mobile.css` (Section 10b):
- `.mobile-level-selector { position: relative }` — anchors the absolute-positioned drawer
- `.level-drawer { opacity: 0; pointer-events: none; position: absolute; bottom: calc(100% + 10px) }` — hides it above the trigger
- `.mobile-level-selector.expanded .level-drawer { opacity: 1; pointer-events: auto }` — shows on tap
- All per-level `--level-color` / `--level-glow` vars, `.level-option` states, and dark mode overrides

---

## Fix 6 — Bottom nav and profile sheet collapsed to single item (mobile.css)

**Problem:** `.mobile-only { display: block !important }` (live rule in `ui-overhaul.css`)
overrode the `display: flex` on `.mobile-bottom-nav` and `.mobile-profile-sheet`, collapsing
their flex children into a vertical stack. Only the first child (New) was visible in the 60px nav height.

**Fix:** Added `!important` to the `display: flex` declarations:
```css
.mobile-bottom-nav  { display: flex !important; }
.mobile-profile-sheet { display: flex !important; }
```
`.mobile-history-tray` already had `!important` from Fix 1.

---

## Fix 7 — Sidebar overlay non-functional (mobile.css)

**Problem:** The overlay's structural CSS (`position: fixed; inset: 0; background; opacity; transition`)
was inside the dead `@media (max-width: 0px)` block. The overlay was an invisible zero-height
element — tapping outside an open sheet did nothing, and no dim background appeared.

**Fix:** Ported structural CSS to `mobile.css` for `#mobile-sidebar-overlay`:
```css
#mobile-sidebar-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
}
#mobile-sidebar-overlay:not(.hidden) { opacity: 1; }
#mobile-sidebar-overlay.active       { pointer-events: auto; }
```

---

## Fix 8 — Input bar centering on empty chat (mobile.css)

**Problem:** Fix 4's combined selector pinned `bottom: nav-height` for ALL states including
`body.chat-empty`, permanently anchoring the input bar at the bottom. The Gemini-style
center→bottom animation was lost.

**Fix:** Split into two state rules with a transition:
```css
/* shared */
.input-bar-area, body.chat-empty .input-bar-area, body:not(.chat-empty) .input-bar-area {
    position: fixed !important; left: 0 !important; right: 0 !important;
    z-index: 200 !important;
    transition: top 0.55s, bottom 0.55s, transform 0.55s cubic-bezier(0.4,0,0.2,1) !important;
}
/* empty — vertically centred between topbar and bottom nav */
body.chat-empty .input-bar-area {
    top: 50% !important;
    bottom: auto !important;
    transform: translateY(calc(-50% + (var(--mobile-topbar-height) - var(--mobile-bottom-nav-height)) / 2)) !important;
}
/* has messages — pinned above bottom nav */
body:not(.chat-empty) .input-bar-area {
    top: auto !important;
    bottom: calc(var(--mobile-bottom-nav-height) + env(safe-area-inset-bottom, 0px)) !important;
    transform: none !important;
}
```

---

## Fix 9 — Welcome section oversized, setup link misplaced (mobile.css)

**Problem:** `.welcome-section` had `min-height: ~70vh` so the greeting content was centered
within a near-full-screen div, overlapping the now-centered input bar. The setup link was
anchored to `bottom: nav + input` (designed for input-at-bottom), now floating to the right
because `left: 50%; transform: translateX(-50%)` was beaten by a live `!important transform`
rule in `ui-overhaul.css`.

**Fix:**
- `welcome-section` height = `calc(50vh - input/2 - topbar)` — fills exactly the space above the centered input.
- Orb reduced to 3.5rem, title reduced to 1.85rem to fit the compressed space.
- Setup link repositioned: `top: calc(50% + input/2 + 10px); left: 0 !important; right: 0 !important; transform: none !important` — full-width row, flex `justify-content: center` handles centering.

---

## Fix 10 — Header logo visible on mobile (mobile.css)

**Problem:** `.header-logo-link` in `.main-chat-header` showed the bridge logo top-left on
mobile, duplicating the bottom-nav brand and cluttering the topbar.

**Fix:**
```css
.header-logo-link { display: none; }
```

---

## Fix 11 — Voice mode icon missing (tutor_mysql.php)

**Problem:** `<i class="fas fa-waveform-lines">` is a Font Awesome **Pro** icon. The project
loads FA 6.4.0 free CDN, so the icon never rendered — the button showed nothing.

**Fix:** Replaced with an inline SVG waveform (5 bars of varying heights, `fill="currentColor"`):
```html
<svg class="default-voice-icon" width="18" height="14" viewBox="0 0 18 14" fill="currentColor">
    <rect x="0"  y="4" width="2" height="6"  rx="1"/>
    <rect x="4"  y="1" width="2" height="12" rx="1"/>
    <rect x="8"  y="3" width="2" height="8"  rx="1"/>
    <rect x="12" y="0" width="2" height="14" rx="1"/>
    <rect x="16" y="4" width="2" height="6"  rx="1"/>
</svg>
```
Inherits `currentColor` so it matches the button in both light and dark mode.

---

## Fix 12 — Bottom nav premium redesign (mobile.css)

**Changes:**
- **Frosted glass nav:** `background: rgba(255,255,255,0.55); backdrop-filter: blur(28px) saturate(160%)` (light) / `rgba(15,15,25,0.9)` (dark).
- **Pill active indicator:** active highlight is a `border-radius: 20px` pill behind the icon only (not the whole item), iOS-style.
- **Equal column spacing:** `flex: 1` on every item including brand — logo always at exact horizontal centre.
- **Brand logo:** plain `28px` SVG, original colors (purple gradient + amber dot), no circle/filter, blends with nav.
- **`backdrop-filter` stacking context:** previous attempts to raise the logo above the nav were clipped by the stacking context created by `backdrop-filter`. Resolved by keeping brand within nav bounds.

---

## Fix 13 — api/user_settings.php 500 errors (pulse monitor)

**Problem:** Pulse monitor reported 500s at ~7 req/s on `/api/user_settings.php`.

**Investigation:**
- `legibility` column confirmed present in DB (`TINYINT UNSIGNED DEFAULT 100`).
- Apache error log showed no fatal PHP errors on that endpoint.
- App was functioning correctly — errors were historical data already recorded by Pulse before the session.

**Resolution:** No code change required. Pulse displays a rolling window of past events; errors aged out naturally as no new 500s were generated.
