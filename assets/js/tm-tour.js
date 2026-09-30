/**
 * TmTour — a guided tour (styles: assets/css/tm-tour.css).
 * The page dims, a spotlight cuts out one control at a time, and a card beside
 * it explains it: Back / Next / Skip, a step counter, arrow keys, Esc to skip.
 *
 *   TmTour.start('chat')           run a tour now
 *   TmTour.startWhenReady('chat')  run it once the loader / any dialog has cleared
 *                                  (the help page's "Take the tour" → chat#tour)
 *   TmTour.startIfNew('chat')      the same, but only once per browser; returns
 *                                  false if this browser has already seen it
 *
 * Steps whose control isn't on screen are skipped (the three dots before the
 * first conversation, the desktop sidebar on a phone), so one tour fits every
 * width. A step with a list of targets uses the first one that's visible.
 * Tours are defined in TOURS below.
 */
(function () {
    'use strict';

    var TOURS = {
        chat: [
            { title: 'Welcome to TutorMind',
              body: 'A quick look around the chat. A few stops, under a minute.',
              next: 'Show me' },
            { target: '.combined-input-bar', title: 'Ask anything',
              body: 'Type what you want to learn. Your tutor explains, then asks you to use it, because using an idea is what makes it stick.' },
            { target: '#attachTrigger', title: 'Bring your material',
              body: 'Add notes, slides, PDFs or images to learn from, or pick a quick start.' },
            { target: '#voice-mode-trigger', title: 'Or just talk',
              body: 'Voice mode: say your question out loud and hear the answer.' },
            { target: '#contactChip', id: 'dots', title: 'Watch it stick',
              body: 'These three dots fill in as you connect an idea to something you know, build with it, and predict with it. All three means it’s encoded.' },
            { target: '#pomodoroTrigger', title: 'Study in focused bursts',
              body: 'Start a study timer. When it ends, one quick question checks what stuck.' },
            { target: ['#user-account-trigger', '#bottomNavProfile'], title: 'Help is always here',
              body: 'Your profile menu has Settings, Personalization and Help. You can replay this tour from Help any time.',
              next: 'Start learning', finish: function () { var q = document.getElementById('question'); if (q) q.focus(); } }
        ],
        // For people who took the tour before they had a conversation (the dots
        // only show inside one): shown once, on a later visit, when they're there.
        dots: [
            { target: '#contactChip', id: 'dots', title: 'Watch it stick',
              body: 'These three dots fill in as you connect an idea to something you know, build with it, and predict with it. All three means it’s encoded.',
              next: 'Got it' }
        ]
    };

    var KEY = 'tm-tour:';
    function seen(name) { try { return localStorage.getItem(KEY + name) === 'done'; } catch (e) { return false; } }
    function markSeen(name) { try { localStorage.setItem(KEY + name, 'done'); } catch (e) {} }

    var root, spot, card, countEl, dotsEl, titleEl, bodyEl, backBtn, nextBtn, skipBtn, markEl;
    var run = null;   // { name, steps: [resolved steps], i, returnFocus }

    function visibleTarget(target) {
        var list = Array.isArray(target) ? target : [target];
        for (var i = 0; i < list.length; i++) {
            var el = document.querySelector(list[i]);
            if (!el) continue;
            var r = el.getBoundingClientRect();
            if (r.width > 0 && r.height > 0 && getComputedStyle(el).visibility !== 'hidden') return el;
        }
        return null;
    }

    function build() {
        root = document.createElement('div');
        root.className = 'tm-tour';
        root.hidden = true;
        root.innerHTML =
            '<div class="tm-tour__blocker"></div>' +
            '<div class="tm-tour__spot" aria-hidden="true"></div>' +
            '<div class="tm-tour__card" role="dialog" aria-modal="true" aria-labelledby="tmTourTitle" aria-describedby="tmTourBody">' +
                '<div class="tm-tour__top">' +
                    '<span class="tm-tour__mark" aria-hidden="true"></span>' +
                    '<span class="tm-tour__count" id="tmTourCount"></span>' +
                    '<span class="tm-tour__dots" aria-hidden="true"></span>' +
                '</div>' +
                '<div aria-live="polite">' +
                    '<h2 class="tm-tour__title" id="tmTourTitle"></h2>' +
                    '<p class="tm-tour__body" id="tmTourBody"></p>' +
                '</div>' +
                '<div class="tm-tour__actions">' +
                    '<button type="button" class="tm-tour__skip">Skip tour</button>' +
                    '<button type="button" class="tm-tour__btn tm-tour__btn--back">Back</button>' +
                    '<button type="button" class="tm-tour__btn tm-tour__btn--next">Next</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(root);
        spot = root.querySelector('.tm-tour__spot');
        card = root.querySelector('.tm-tour__card');
        markEl = root.querySelector('.tm-tour__mark');
        countEl = root.querySelector('.tm-tour__count');
        dotsEl = root.querySelector('.tm-tour__dots');
        titleEl = root.querySelector('.tm-tour__title');
        bodyEl = root.querySelector('.tm-tour__body');
        backBtn = root.querySelector('.tm-tour__btn--back');
        nextBtn = root.querySelector('.tm-tour__btn--next');
        skipBtn = root.querySelector('.tm-tour__skip');

        nextBtn.addEventListener('click', next);
        backBtn.addEventListener('click', back);
        skipBtn.addEventListener('click', function () { end(false); });
        root.addEventListener('keydown', onKey);
        window.addEventListener('resize', function () { if (run) place(); });
    }

    function start(name) {
        var defs = TOURS[name];
        if (!defs || run) return false;
        // Only the steps that have something to point at right now
        var steps = defs.filter(function (s) { return !s.target || visibleTarget(s.target); });
        if (!steps.length) return false;
        if (!root) build();
        if (window.TmTips) window.TmTips.hide();
        run = { name: name, steps: steps, i: 0, returnFocus: document.activeElement };
        root.hidden = false;
        root.classList.remove('is-in');
        void root.offsetWidth;
        root.classList.add('is-in');
        show(0);
        return true;
    }

    // Something else on screen that shouldn't be talked over
    function busy() {
        return !!document.querySelector('.tm-loader-overlay, .tm-focus, .tm-dialog-overlay, .feedback-modal-overlay')
            || !!document.querySelector('#recall-modal:not(.hidden), #voice-mode-overlay:not(.hidden), #settings-modal:not(.hidden)');
    }

    // Start once the page is free: waits (up to ~15s) for the loader and any
    // dialog to clear first, so the tour never talks over them
    function startWhenReady(name, onlyIfNew) {
        if (!TOURS[name] || run) return false;
        if (onlyIfNew && seen(name)) return false;
        var tries = 0;
        (function attempt() {
            if (run || (onlyIfNew && seen(name))) return;
            if (busy()) { if (++tries < 50) setTimeout(attempt, 300); return; }
            start(name);
        })();
        return true;
    }
    // Once per browser
    function startIfNew(name) { return startWhenReady(name, true); }

    function show(i) {
        run.i = i;
        var step = run.steps[i];
        var n = run.steps.length;
        var target = step.target ? visibleTarget(step.target) : null;
        if (step.target && !target) {           // went away since the tour started
            if (i < n - 1) return show(i + 1);
            return end(true);
        }
        run.target = target;
        root.classList.toggle('is-centered', !target);
        markEl.hidden = !!target;
        // A one-step hint pointing at a control has no mark or counter to show
        markEl.parentNode.hidden = !!target && n <= 1;
        countEl.textContent = n > 1 ? (i + 1) + ' of ' + n : '';
        dotsEl.innerHTML = n > 1 ? run.steps.map(function (s, k) {
            return '<span class="' + (k < i ? 'is-done' : k === i ? 'is-now' : '') + '"></span>';
        }).join('') : '';
        titleEl.textContent = step.title;
        bodyEl.textContent = step.body;
        backBtn.hidden = i === 0;
        skipBtn.hidden = i === n - 1;
        nextBtn.textContent = step.next || (i === n - 1 ? 'Done' : 'Next');
        card.classList.remove('is-step-in');
        place();
        void card.offsetWidth;
        card.classList.add('is-step-in');
        nextBtn.focus({ preventScroll: true });
    }

    function place() {
        var target = run && run.target;
        var vw = window.innerWidth, vh = window.innerHeight, margin = 12, gap = 16;
        card.style.left = '0px'; card.style.top = '0px';
        var cw = card.offsetWidth, ch = card.offsetHeight;
        if (!target) {
            card.style.left = Math.round((vw - cw) / 2) + 'px';
            card.style.top = Math.round(Math.max(margin, (vh - ch) / 2)) + 'px';
            card.removeAttribute('data-side');
            return;
        }
        var r = target.getBoundingClientRect();
        var pad = 6, edge = 3;
        // Padded around the control, but kept on screen (a control flush with
        // the edge, like the phone's bottom-nav profile button, would overhang)
        var sl = Math.max(edge, r.left - pad), st = Math.max(edge, r.top - pad);
        var sr = Math.min(vw - edge, r.right + pad), sb = Math.min(vh - edge, r.bottom + pad);
        spot.style.left = Math.round(sl) + 'px';
        spot.style.top = Math.round(st) + 'px';
        spot.style.width = Math.round(sr - sl) + 'px';
        spot.style.height = Math.round(sb - st) + 'px';
        var below = r.top + r.height / 2 < vh / 2;
        var top = below ? r.bottom + pad + gap : r.top - pad - gap - ch;
        top = Math.max(margin, Math.min(vh - margin - ch, top));
        var centre = r.left + r.width / 2;
        var left = Math.max(margin, Math.min(vw - margin - cw, centre - cw / 2));
        card.style.left = Math.round(left) + 'px';
        card.style.top = Math.round(top) + 'px';
        card.dataset.side = below ? 'below' : 'above';
        card.style.setProperty('--tm-tour-x', Math.round(Math.max(18, Math.min(cw - 18, centre - left))) + 'px');
    }

    function next() {
        if (!run) return;
        if (run.i < run.steps.length - 1) show(run.i + 1);
        else end(true);
    }
    function back() { if (run && run.i > 0) show(run.i - 1); }

    // Finished or skipped, it's remembered either way; a skipped tour doesn't
    // come back on its own (the help page can replay it)
    function end(finished) {
        if (!run) return;
        var r = run;
        run = null;
        markSeen(r.name);
        if (r.steps.some(function (s) { return s.id === 'dots'; })) markSeen('dots');
        root.hidden = true;
        var last = r.steps[r.steps.length - 1];
        if (finished && last.finish) last.finish();
        else if (r.returnFocus && document.contains(r.returnFocus) && r.returnFocus.focus) r.returnFocus.focus({ preventScroll: true });
    }

    function onKey(e) {
        if (!run) return;
        if (e.key === 'Escape') { e.preventDefault(); end(false); return; }
        if (e.key === 'ArrowRight') { e.preventDefault(); next(); return; }
        if (e.key === 'ArrowLeft') { e.preventDefault(); back(); return; }
        if (e.key === 'Tab') {
            var items = [skipBtn, backBtn, nextBtn].filter(function (b) { return !b.hidden; });
            var i = items.indexOf(document.activeElement);
            e.preventDefault();
            items[(i + (e.shiftKey ? items.length - 1 : 1) + items.length) % items.length].focus();
        }
    }

    window.TmTour = {
        start: start,
        startWhenReady: function (name) { return startWhenReady(name, false); },
        startIfNew: startIfNew,
        end: function () { end(false); }
    };
})();
