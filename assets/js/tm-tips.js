/**
 * TmTips — explain-a-control tooltips (styles: assets/css/tm-tips.css).
 *
 *   <button data-tip="One line on what it does" data-tip-help="voice-mode">…</button>
 *
 * - Mouse: hover for a moment. Once one tip has shown, the next opens at once.
 * - Keyboard: focusing the control shows it; Tab moves into "Learn more",
 *   Tab again carries on through the page; Esc closes.
 * - Touch: press and hold (a normal tap still just does the control's action).
 * data-tip-help adds "Learn more", which opens that answer on the help page
 * (help#<id>) in a new tab, so the lesson stays where it was. data-tip is read
 * each time the tip opens, so pages can update it whenever (the contact chip does).
 * The tip is a single element on <body>, so no container can clip it.
 */
(function () {
    'use strict';

    var OPEN_DELAY = 450, WARM_MS = 700, CLOSE_DELAY = 140, HOLD_MS = 550;
    var tip, textEl, moreEl;
    var current = null;            // the control whose tip is showing
    var openTimer = null, closeTimer = null, holdTimer = null;
    var lastHidden = 0;            // for the "warm" instant re-open
    var holdStart = null, suppressClick = false;

    var ARROW = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';

    function build() {
        tip = document.createElement('div');
        tip.className = 'tm-tip';
        tip.hidden = true;
        textEl = document.createElement('span');
        textEl.className = 'tm-tip__text';
        textEl.id = 'tm-tip-text';
        moreEl = document.createElement('a');
        moreEl.className = 'tm-tip__more';
        moreEl.target = '_blank';
        moreEl.rel = 'noopener';
        moreEl.innerHTML = 'Learn more' + ARROW;
        tip.appendChild(textEl);
        tip.appendChild(moreEl);
        tip.addEventListener('pointerenter', cancelClose);
        tip.addEventListener('pointerleave', function (e) { if (e.pointerType === 'mouse') scheduleClose(); });
        moreEl.addEventListener('click', function () { hide(); });
        document.body.appendChild(tip);
    }

    function targetOf(node) {
        return node && node.closest ? node.closest('[data-tip]') : null;
    }

    // ---- show / hide ----
    function show(el) {
        clearTimeout(openTimer); cancelClose();
        var text = el.getAttribute('data-tip');
        if (!text) return;
        if (current && current !== el) detach();
        current = el;
        textEl.textContent = text;
        var help = el.getAttribute('data-tip-help');
        moreEl.hidden = !help;
        if (help) {
            moreEl.href = 'help#' + encodeURIComponent(help);
            moreEl.setAttribute('aria-label', 'Learn more about this in Help (opens in a new tab)');
        }
        tip.hidden = false;
        tip.classList.remove('is-in');
        place(el);
        void tip.offsetWidth;
        tip.classList.add('is-in');
        // Screen readers hear the explanation as the control's description
        var d = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
        if (d.indexOf('tm-tip-text') === -1) { d.push('tm-tip-text'); el.setAttribute('aria-describedby', d.join(' ')); }
    }

    function detach() {
        if (!current) return;
        var d = (current.getAttribute('aria-describedby') || '').split(/\s+/).filter(function (x) { return x && x !== 'tm-tip-text'; });
        if (d.length) current.setAttribute('aria-describedby', d.join(' ')); else current.removeAttribute('aria-describedby');
    }

    function hide() {
        clearTimeout(openTimer); cancelClose();
        if (!current) return;
        detach();
        current = null;
        tip.hidden = true;
        tip.classList.remove('is-in');
        lastHidden = Date.now();
    }

    function scheduleOpen(el) {
        cancelClose();
        if (current === el) return;
        clearTimeout(openTimer);
        // Moving between controls right after a tip closed: no second wait
        var warm = !!current || Date.now() - lastHidden < WARM_MS;
        openTimer = setTimeout(function () { show(el); }, warm ? 0 : OPEN_DELAY);
    }
    function scheduleClose() {
        clearTimeout(openTimer);
        cancelClose();
        closeTimer = setTimeout(hide, CLOSE_DELAY);
    }
    function cancelClose() { clearTimeout(closeTimer); closeTimer = null; }

    // Above the control when it sits in the lower half of the screen (the
    // message box), below it otherwise (the header); centred, kept on screen.
    function place(el) {
        var r = el.getBoundingClientRect();
        var gap = 10, margin = 8;
        tip.style.left = '0px'; tip.style.top = '0px';
        var w = tip.offsetWidth, h = tip.offsetHeight;
        var below = r.top + r.height / 2 < window.innerHeight / 2;
        var top = below ? r.bottom + gap : r.top - gap - h;
        var centre = r.left + r.width / 2;
        var left = Math.max(margin, Math.min(window.innerWidth - margin - w, centre - w / 2));
        tip.style.left = Math.round(left) + 'px';
        tip.style.top = Math.round(top) + 'px';
        tip.dataset.side = below ? 'below' : 'above';
        tip.style.setProperty('--tm-tip-x', Math.round(Math.max(14, Math.min(w - 14, centre - left))) + 'px');
    }

    // ---- mouse ----
    document.addEventListener('pointerover', function (e) {
        if (e.pointerType !== 'mouse') return;
        var el = targetOf(e.target);
        if (el) scheduleOpen(el);
    });
    document.addEventListener('pointerout', function (e) {
        if (e.pointerType !== 'mouse') return;
        var el = targetOf(e.target);
        if (!el) return;
        var to = e.relatedTarget;
        if (to && (el.contains(to) || tip.contains(to))) return;
        if (current === el) scheduleClose(); else clearTimeout(openTimer);
    });
    // Using the control means you know what it does
    document.addEventListener('click', function (e) {
        if (suppressClick) { suppressClick = false; e.preventDefault(); e.stopPropagation(); return; }
        if (current && !tip.contains(e.target)) hide();
    }, true);

    // ---- keyboard ----
    document.addEventListener('focusin', function (e) {
        if (tip.contains(e.target)) { cancelClose(); return; }
        var el = targetOf(e.target);
        if (el && el === e.target && el.matches(':focus-visible')) show(el);
        // Focus landing on the control whose tip is open (a finger lifting after
        // press-and-hold) keeps it open; focus anywhere else closes it
        else if (current && el !== current) hide();
    });
    document.addEventListener('focusout', function (e) {
        var to = e.relatedTarget;
        if (!current) return;
        if (to && (to === current || tip.contains(to))) return;
        // Leaving for somewhere else entirely (closing the window, clicking the page)
        setTimeout(function () {
            var a = document.activeElement;
            if (current && a !== current && !tip.contains(a)) hide();
        }, 0);
    });
    document.addEventListener('keydown', function (e) {
        if (!current) return;
        if (e.key === 'Escape') {
            var inTip = tip.contains(document.activeElement);
            var from = current;
            hide();
            if (inTip) from.focus();
            return;
        }
        if (e.key !== 'Tab' || moreEl.hidden) return;
        var a = document.activeElement;
        if (a === current && !e.shiftKey) {
            // The tip isn't next to the control in the page, so step into it by hand
            e.preventDefault();
            moreEl.focus();
        } else if (a === moreEl) {
            e.preventDefault();
            var from = current;
            if (e.shiftKey) { from.focus(); return; }
            hide();
            var next = nextFocusable(from);
            (next || from).focus();
        }
    }, true);

    function nextFocusable(from) {
        var all = Array.prototype.filter.call(
            document.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'),
            function (el) { return !tip.contains(el) && el.getClientRects().length && el.tabIndex >= 0; });
        var i = all.indexOf(from);
        return i === -1 ? null : all[i + 1] || null;
    }

    // ---- touch: press and hold ----
    document.addEventListener('pointerdown', function (e) {
        if (current && !tip.contains(e.target) && targetOf(e.target) !== current) hide();
        if (e.pointerType === 'mouse') return;
        var el = targetOf(e.target);
        if (!el) return;
        holdStart = { x: e.clientX, y: e.clientY };
        clearTimeout(holdTimer);
        holdTimer = setTimeout(function () {
            show(el);
            // The hold was for the tip, not the button: swallow the click it ends
            // with. Expires, in case the browser never sends that click (it can
            // cancel it after a long press), so it can't eat the next real tap.
            suppressClick = true;
            setTimeout(function () { suppressClick = false; }, 1000);
        }, HOLD_MS);
    });
    function endHold() { clearTimeout(holdTimer); holdStart = null; }
    document.addEventListener('pointerup', endHold);
    document.addEventListener('pointercancel', endHold);
    document.addEventListener('pointermove', function (e) {
        if (!holdStart) return;
        if (Math.abs(e.clientX - holdStart.x) > 10 || Math.abs(e.clientY - holdStart.y) > 10) endHold();
    });
    document.addEventListener('contextmenu', function (e) {
        if (current && targetOf(e.target) === current) e.preventDefault();   // Android's long-press menu
    });

    // Anything moving under an open tip makes its position wrong. Only an open
    // one: the chat scrolls itself after loading, and cancelling a tip that's
    // still waiting to open made the first hover of a visit do nothing.
    function hideIfOpen() { if (current) hide(); }
    window.addEventListener('resize', hideIfOpen);
    window.addEventListener('scroll', hideIfOpen, true);

    function init() {
        build();
        // A native title would show a second, unstyled tooltip on top of ours
        document.querySelectorAll('[data-tip][title]').forEach(function (el) {
            if (!el.hasAttribute('aria-label')) el.setAttribute('aria-label', el.getAttribute('title'));
            el.removeAttribute('title');
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();

    window.TmTips = { hide: hide };
})();
