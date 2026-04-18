/**
 * TutorMind Custom Dialog System
 * Replaces browser-native confirm() and alert() with brutalist-styled dialogs.
 *
 * Usage:
 *   await TmDialog.confirm({ title, message })           → true / false
 *   await TmDialog.alert({ title, message, type })       → void
 *   await TmDialog.confirm({ ..., destructive: true })   → red confirm button
 */
const TmDialog = (() => {
    const ICONS = {
        confirm: '<i class="fas fa-circle-question"></i>',
        alert:   '<i class="fas fa-circle-exclamation"></i>',
        warning: '<i class="fas fa-triangle-exclamation"></i>',
        info:    '<i class="fas fa-circle-info"></i>',
    };

    function _build({ title, message, type = 'confirm', destructive = false, confirmLabel = 'OK', cancelLabel = 'Cancel', showCancel = true }) {
        const overlay = document.createElement('div');
        overlay.className = 'tm-dialog-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');

        const iconHtml = ICONS[type] || ICONS.info;
        const confirmClass = `tm-dialog-btn tm-dialog-btn-confirm${destructive ? ' destructive' : ''}`;

        overlay.innerHTML = `
            <div class="tm-dialog-box">
                <div class="tm-dialog-icon type-${type}">${iconHtml}</div>
                <p class="tm-dialog-title">${title}</p>
                <p class="tm-dialog-message">${message}</p>
                <div class="tm-dialog-actions">
                    ${showCancel ? `<button class="tm-dialog-btn tm-dialog-btn-cancel" id="tm-cancel">${cancelLabel}</button>` : ''}
                    <button class="${confirmClass}" id="tm-confirm">${confirmLabel}</button>
                </div>
            </div>
        `;

        return overlay;
    }

    /**
     * Show a confirm dialog. Returns a Promise<boolean>.
     */
    function confirm({ title = 'Are you sure?', message = '', destructive = false, confirmLabel = 'Confirm', cancelLabel = 'Cancel' } = {}) {
        return new Promise((resolve) => {
            const overlay = _build({ title, message, type: 'confirm', destructive, confirmLabel, cancelLabel, showCancel: true });
            document.body.appendChild(overlay);

            const focusEl = overlay.querySelector('#tm-confirm');
            if (focusEl) focusEl.focus();

            function cleanup(result) {
                overlay.style.animation = 'tmOverlayIn 0.1s ease reverse';
                setTimeout(() => {
                    if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
                    resolve(result);
                }, 100);
            }

            overlay.querySelector('#tm-confirm').addEventListener('click', () => cleanup(true));
            overlay.querySelector('#tm-cancel').addEventListener('click',  () => cleanup(false));

            // ESC key
            function onKey(e) {
                if (e.key === 'Escape') { document.removeEventListener('keydown', onKey); cleanup(false); }
            }
            document.addEventListener('keydown', onKey);

            // Click outside
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) { document.removeEventListener('keydown', onKey); cleanup(false); }
            });
        });
    }

    /**
     * Show an alert dialog (no cancel). Returns a Promise<void>.
     */
    function alert({ title = 'Notice', message = '', type = 'alert', confirmLabel = 'OK' } = {}) {
        return new Promise((resolve) => {
            const overlay = _build({ title, message, type, confirmLabel, showCancel: false });
            document.body.appendChild(overlay);

            const focusEl = overlay.querySelector('#tm-confirm');
            if (focusEl) focusEl.focus();

            function cleanup() {
                overlay.style.animation = 'tmOverlayIn 0.1s ease reverse';
                setTimeout(() => {
                    if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
                    resolve();
                }, 100);
            }

            overlay.querySelector('#tm-confirm').addEventListener('click', cleanup);

            function onKey(e) {
                if (e.key === 'Escape' || e.key === 'Enter') { document.removeEventListener('keydown', onKey); cleanup(); }
            }
            document.addEventListener('keydown', onKey);
            overlay.addEventListener('click', (e) => { if (e.target === overlay) { document.removeEventListener('keydown', onKey); cleanup(); } });
        });
    }

    return { confirm, alert };
})();

// Make globally available
window.TmDialog = TmDialog;
