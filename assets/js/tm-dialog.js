/**
 * TutorMind Custom Dialog System
 * Replaces browser-native confirm() and alert() with design-system dialogs.
 *
 * Usage:
 *   await TmDialog.confirm({ title, message })           → true / false
 *   await TmDialog.alert({ title, message, type })       → void
 *   await TmDialog.confirm({ ..., destructive: true })   → red confirm button
 */
const TmDialog = (() => {
    // No per-type icon: the old one gave each dialog type its own hue
    // (styles: tm-chat.css §9). `type` is still accepted for callers.
    function _build({ title, message, destructive = false, confirmLabel = 'OK', cancelLabel = 'Cancel', showCancel = true }) {
        const overlay = document.createElement('div');
        overlay.className = 'tm-dialog-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');

        const confirmClass = `tm-dialog-btn tm-dialog-btn-confirm${destructive ? ' destructive' : ''}`;

        overlay.innerHTML = `
            <div class="tm-dialog-box">
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

    /**
     * Show a confirm dialog with one input (e.g. a password to authorise a
     * destructive action). Returns a Promise<string|null>: the value, or null
     * if cancelled. Confirm stays disabled until something is typed.
     */
    function prompt({ title = 'Are you sure?', message = '', inputType = 'text', inputLabel = '', placeholder = '', destructive = false, confirmLabel = 'Confirm', cancelLabel = 'Cancel' } = {}) {
        return new Promise((resolve) => {
            const overlay = _build({ title, message, type: 'warning', destructive, confirmLabel, cancelLabel, showCancel: true });
            const field = document.createElement('label');
            field.className = 'tm-dialog-field';
            field.innerHTML = `<span>${inputLabel}</span><input class="tm-dialog-input" type="${inputType}">`;
            const input = field.querySelector('input');
            input.placeholder = placeholder;
            if (inputType === 'password') input.autocomplete = 'current-password';
            overlay.querySelector('.tm-dialog-actions').before(field);
            document.body.appendChild(overlay);

            const confirmBtn = overlay.querySelector('#tm-confirm');
            confirmBtn.disabled = true;
            input.addEventListener('input', () => { confirmBtn.disabled = !input.value; });
            input.focus();

            function cleanup(result) {
                document.removeEventListener('keydown', onKey);
                overlay.style.animation = 'tmOverlayIn 0.1s ease reverse';
                setTimeout(() => {
                    if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
                    resolve(result);
                }, 100);
            }
            const submit = () => { if (input.value) cleanup(input.value); };

            confirmBtn.addEventListener('click', submit);
            overlay.querySelector('#tm-cancel').addEventListener('click', () => cleanup(null));
            input.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); submit(); } });
            function onKey(e) { if (e.key === 'Escape') cleanup(null); }
            document.addEventListener('keydown', onKey);
            overlay.addEventListener('click', (e) => { if (e.target === overlay) cleanup(null); });
        });
    }

    return { confirm, alert, prompt };
})();

// Make globally available
window.TmDialog = TmDialog;
