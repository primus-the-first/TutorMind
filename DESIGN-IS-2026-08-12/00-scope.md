# Scope Lock

**Audited surface:** TutorMind mobile chat home screen (`/chat` landing/idle state, no active conversation), as rendered on a phone viewport (~540px wide per user screenshot). Primary files implementing this surface:
- `tutor_mysql.php` — page markup (header/timer/avatar, greeting, input bar, bottom nav)
- `assets/css/mobile.css` — mobile-specific styles (added in prior "Mobile redesign" pass)
- `assets/css/ui-overhaul.css` — base desktop-first styles, partially overridden on mobile
- `assets/css/chat-interface.css` — chat/input component styles
- `assets/js/chat-interface.js` — bottom nav / mobile behavior wiring

**Input materials:** User-supplied screenshot of the live mobile chat home screen (Chrome on Android, 12:52 AM, battery 97%). Shows: top bar with pomodoro timer pill (25:00) and profile avatar circle; large purple radial glow; "Hey, Anthony!" greeting; pill-shaped "Ask anything" input with two mic icons; large empty area below; bottom nav bar with 5 icons (new chat +, history, home/logo arch, settings gear, profile).

**Primary user:** A student (e.g. "Anthony") opening TutorMind on their phone to start or resume a tutoring session — mid-task, likely between other apps (screenshot shows WhatsApp/X/YouTube/Phone in status bar), often one-handed.

**Primary task:** Get from "phone unlocked, app open" to "typing or speaking a question to the tutor" in as few taps/as little visual parsing as possible.

**Constraints:**
- Existing brand: dark theme, purple/violet accent, "TutorMind" arch logo
- Stack: PHP server-rendered pages, vanilla CSS/JS (no framework), mobile.css is already a dedicated mobile stylesheet layered after ui-overhaul.css
- This is a live production-adjacent app (dev branch = production per prior project memory) — redesign must be shippable incrementally
- Prior "Mobile redesign" pass (commit 90f83b1) already introduced the bottom nav pattern shown in the screenshot; this audit judges the shipped result, not the original plan

**Reference/competitor context:** User's implicit comparison is modern AI chat apps (ChatGPT, Claude mobile) — home states in those apps fill idle space with suggestions/recent items rather than empty gradient.

**User complaint (verbatim intent):** "the mobile version still sucks, design and all" — global dissatisfaction, not a single-element complaint. Audit will determine whether this is a REFINE or REDESIGN situation.
