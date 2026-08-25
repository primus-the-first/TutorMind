/**
 * TutorMind Bridge Loader
 * Branded loading animations built from the bridge-arch logo mark.
 *
 * Usage:
 *   TmLoader.showFullscreen('Signing you in…')   → shows the full-screen "Bridge Draw" overlay
 *   TmLoader.hide()                              → removes it
 *   TmLoader.inlineHTML()                        → small "Orbit Dot" <svg> markup as a string,
 *                                                   for dropping into a button (like fa-spinner)
 */
const TmLoader = (() => {
    // Must match the animation-duration of .tm-loader-draw-arch in tm-loader.css
    const FULL_CYCLE_MS = 2200;

    let overlay = null;

    function showFullscreen(message = 'Loading…') {
        if (overlay) return;

        overlay = document.createElement('div');
        overlay.className = 'tm-loader-overlay';
        overlay.setAttribute('role', 'status');
        overlay.setAttribute('aria-live', 'polite');

        overlay.innerHTML = `
            <svg viewBox="0 0 40 40" fill="none">
                <defs>
                    <linearGradient id="tm-loader-gradient" x1="0" y1="20" x2="40" y2="20" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#5E2EBF" />
                        <stop offset="100%" stop-color="#9D6BF5" />
                    </linearGradient>
                </defs>
                <path class="tm-loader-draw-arch" stroke="url(#tm-loader-gradient)" d="M6 30 C 6 20, 14 12, 20 12 C 26 12, 34 20, 34 30"/>
                <circle class="tm-loader-draw-dot-top" cx="20" cy="8" r="3"/>
                <circle cx="6" cy="30" r="2.5" fill="#5E2EBF"/>
                <circle cx="34" cy="30" r="2.5" fill="#9D6BF5"/>
            </svg>
            <span class="tm-loader-message"></span>
        `;
        overlay.querySelector('.tm-loader-message').textContent = message;

        document.body.appendChild(overlay);
    }

    function hide() {
        if (overlay && overlay.parentNode) {
            overlay.parentNode.removeChild(overlay);
        }
        overlay = null;
    }

    function inlineHTML() {
        return `
            <svg class="tm-loader-inline" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                <path class="tm-loader-orbit-arch" d="M6 30 C 6 20, 14 12, 20 12 C 26 12, 34 20, 34 30"/>
                <circle class="tm-loader-orbit-dot" r="3.2"/>
            </svg>
        `;
    }

    return { showFullscreen, hide, inlineHTML, FULL_CYCLE_MS };
})();
