---
name: tutormind-design
description: TutorMind's own visual design scheme — brand assets, tokens, and anti-patterns to check any UI/CSS work against before calling it done. Use whenever styling or redesigning any part of the TutorMind UI (widgets, chat, dashboard, onboarding, landing).
---

# TutorMind design scheme

Grounding rule: **reuse what TutorMind already has before inventing anything.** This app has a real brand identity (a logo mark, a display typeface, a token system) sitting mostly unused outside the shell chrome. Generic "AI app" styling (gradients, emoji, a rainbow of accent colors, pills everywhere) is *available* effort — it takes no research. Distinctive styling takes five minutes of grepping the repo for what's already there. Always do the grep first.

## The brand assets to actually use

- **Logo mark** — `assets/logo-bridge.svg`: a hand-drawn bridge arch (single-weight stroke, round caps) with a filled "connection dot" at the peak and two base dots. Metaphor: bridging a gap in understanding. This is the one shape TutorMind owns — reach for it (or a monoline derivative of it) before reaching for an icon font or emoji.
- **Display typeface** — `Funnel Display` (700 weight), already loaded on every real page (`tutor_mysql.php`, `dashboard.php`, `landing.css`, `logo.css`, `tm-loader.css`). This is the wordmark/headline face. Body copy stays on `Outfit` / `Source Sans Pro`. A UI element trying to carry brand personality (a widget label, a section title, a loader message) should be candidates for Funnel Display — check the page already loads it before using it (grep for `Funnel Display` in the page's `<head>`).
- **Color tokens** — defined once in `assets/css/ui-overhaul.css` `:root` (and reflected in `body.dark-mode`): `--primary` (#7C3AED), `--primary-light`, `--primary-dark` (#5B21B6), `--cta` (#F59E0B), `--cta-hover` (#D97706), `--bg-main`, `--bg-card`, `--text-primary`, `--text-secondary`, `--border`, `--shadow-sm`, `--shadow-md`. Every hover/active/pressed state should resolve to one of these tokens (e.g. hover → `--primary-dark`, not a computed tint). If a state needs a color this system doesn't have, that's a sign to check with the user before adding a new one — don't invent hex values or reach for `color-mix()` gradients to fake a token that isn't there.
- **Motion signature** — `assets/css/tm-loader.css`'s "Bridge Draw" animation strokes the arch on with `stroke-dasharray`/`stroke-dashoffset`. That draw-in technique is TutorMind's actual signature motion; prefer it over generic scale/fade pop-ins when a moment deserves real emphasis (a correct answer, a completed loader).

## Anti-patterns — check every redesign against this list

These are the tells of templated/generic AI-generated UI. None of them are wrong in isolation on someone else's project; on TutorMind they're wrong because the product already has a specific identity that they paper over.

- ❌ Emoji as icons or section markers (💬 ✅ 💡 📈 …). → ✅ A monoline SVG icon, ideally derived from the bridge-arch stroke style (see `--tm-mark-arch` / `--tm-mark-check` / `--tm-mark-cross` in `assets/css/tm-widgets.css` for the pattern: a `mask-image` data-URI + `background-color: currentColor`-style token, so the same mark works in both themes and any accent color).
- ❌ A different accent hue per card/section/type ("each widget type gets its own color," a rainbow of 6–10 hues). → ✅ One accent system: `--primary` for structure/interactivity, `--cta`/`--cta-hover` for the one actionable button, `--tm-good`/`--tm-bad` reserved strictly for correctness feedback (never decorative).
- ❌ `border-radius: 999px` / pill shapes on every button and chip. → ✅ Match the app's actual button convention — a modest ~8–10px radius (see `.send-btn` in `ui-overhaul.css`, `border-radius: 8px`). Reserve a fully rounded shape for places that are already pill-shaped in the live app (e.g. `.tm-contact-chip`), not as the default for new buttons.
- ❌ `linear-gradient()` fills and `color-mix()`-generated glow shadows on buttons/hovers. → ✅ Flat, solid token colors; a hover state is a token swap (`--cta` → `--cta-hover`), not a synthesized tint.
- ❌ A decorative colored bar/rail along the top or side of a card ("accent bar on rounded card"). → ✅ If a card needs an identity mark, give it one deliberate, branded element (the arch icon + label) rather than a stripe.
- ❌ Small-caps, letter-spaced, uppercase "eyebrow" labels (`text-transform: uppercase; letter-spacing: 0.1em`) as a generic dashboard convention. → ✅ Sentence case in the brand display face (Funnel Display), sized to read as a small heading, not a tag.
- ❌ Purple-to-blue gradient hero treatments, or any gradient used purely for visual interest rather than to represent something real. → ✅ Flat brand purple; TutorMind's actual gradient (see `logo-bridge.svg`'s `bridge-gradient`) is reserved for the logo mark itself, not reused as decoration elsewhere.

## Before shipping any UI/CSS change

1. Grep the target page's `<head>` for which fonts are actually loaded before assuming `Funnel Display` (or any font) is available there.
2. Grep `assets/css/ui-overhaul.css` for an existing token before hardcoding a color or shadow value.
3. Check `assets/logo-bridge.svg` and `assets/css/tm-loader.css` for the stroke-style/motion patterns already established, and reuse them rather than inventing a new visual language per feature.
4. Run the finished CSS/markup past the anti-pattern list above — if two or more apply, it will read as generic regardless of polish.
5. Where possible, preview the change live (`tm_widget_test.php`-style harness, or a real page) before calling it done — polish that's only verified by reading the CSS is unverified.
