# Scorecard — TutorMind Mobile Chat Home Screen

1. Good design is innovative — Score: 1/3
   Evidence: 01-evidence.md §A — bottom nav + input pill is a standard mobile-chat pattern, not a refinement with a clear improvement over peers.
   Justification: Imitates the now-common AI-chat mobile layout with minor variation; introduces nothing peers don't already do.

2. Good design makes a product useful — Score: 2/3
   Evidence: 01-evidence.md §A — input bar is immediately reachable and functional, but 2 duplicate voice controls and a dead-looking center nav icon are decoy affordances on the same screen.
   Justification: Primary task (reach the input, type/speak) completes without detour, but adjacent duplicated controls add noise and a false choice.

3. Good design is aesthetic — Score: 1/3
   Evidence: 01-evidence.md §B — two overlapping spacing/type scales, a silent cascade bug that makes mobile.css's own greeting-size rule dead, 67+ ad hoc color literals, and a WCAG-failing contrast ratio (2.56:1) on the primary bottom nav.
   Justification: More than 5 inconsistencies plus one jarring violation (failing contrast on the nav the whole redesign centers on) — no single coherent system is actually in force.

4. Good design makes a product understandable — Score: 1/3
   Evidence: 01-evidence.md §C — unlabeled center nav icon with no accessible name, "Encoded / n of 3" internal jargon, unexplained Pomodoro "Quiz Mode" tiers.
   Justification: 2-3 controls are unclear to a first-time user, matching the "jargon present" tier.

5. Good design is unobtrusive — Score: 1/3
   Evidence: 01-evidence.md §D + user screenshot — a permanently-pulsing decorative glow orb (`quick-start-pulse`, 4s infinite, unguarded) is the single largest, most animated element on an otherwise near-empty screen.
   Justification: Decoration competes with content for attention on a screen that has almost no content to compete with.

6. Good design is honest — Score: 1/3
   Evidence: 01-evidence.md §C — "personalized experience" claim with no on-screen backing, plus the center nav icon visually implying tap-ability it doesn't have.
   Justification: One copy inflation plus one false-affordance control together exceed the single-minor-inflation threshold for a 2.

7. Good design is long-lasting — Score: 1/3
   Evidence: 01-evidence.md §B — glassmorphism blur overlays (`rgba(255,255,255,.55/.78)`), an animated gradient orb, and a heavy purple-gradient AI-branding palette.
   Justification: 2-3 dated trend markers (glassmorphism + gradient-orb hero + generic "AI purple") that read as current-generation AI-app styling rather than timeless.

8. Good design is thorough down to the last detail — Score: 1/3
   Evidence: 01-evidence.md §B — Error and Success states are both missing from this surface; Loading is only a generic opacity dim; a richer empty-state (suggestion chips) exists in CSS but is never populated in markup.
   Justification: 2-3 of the six standard states are missing or unbuilt-despite-CSS-existing.

9. Good design is environmentally friendly — Score: 1/3
   Evidence: 01-evidence.md §D — ~493KB of synchronous CSS+JS blocks interactivity, 16 requests on load, and the idle-screen animation runs forever with no `prefers-reduced-motion` guard anywhere in the two files that matter.
   Justification: Weight sits near the 500KB tier-2 boundary (and is undercounted — GSAP/fonts/Font Awesome/Highlight.js are blocking and unmeasured), and motion is not gated at all, which caps this below the "motion gated" tiers regardless of byte count.

10. Good design is as little design as possible — Score: 0/3
    Evidence: 01-evidence.md §A — a 128-line self-admitted "dead block" of unused Mobile Level Selector CSS, duplicate voice controls, duplicate Settings entry points, a breakpoint-split New-chat button, unused suggestion-chip CSS, and an unused second orb-pulse animation.
    Justification: More than five distinct removable/duplicated elements are present — well past the "≤2 removable" and "3-5 removable" tiers into a screen genuinely padded with dead and duplicated affordances.

**Total: 10/30**
