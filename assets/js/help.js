/**
 * Help page (help.php): search, deep links and the topic list.
 *   - Search filters questions as you type (every word must match the question
 *     or its answer) and opens the matches; clearing it puts everything back.
 *   - help#<question-id> opens that answer and marks it briefly — the hook for
 *     in-app tooltips to link straight to an explanation.
 *   - The topic list follows the section you're reading.
 */
(function () {
    'use strict';

    var input = document.getElementById('hpSearch');
    var count = document.getElementById('hpCount');
    var empty = document.getElementById('hpEmpty');
    var sections = Array.prototype.slice.call(document.querySelectorAll('.hp-section'));
    var items = Array.prototype.slice.call(document.querySelectorAll('.hp-item'));
    var topicLinks = Array.prototype.slice.call(document.querySelectorAll('.hp-topics a'));
    var topicsBox = document.querySelector('.hp-topics');

    // Searchable text per question, lower-cased once
    items.forEach(function (item) { item._hpText = item.textContent.toLowerCase().replace(/\s+/g, ' '); });

    // ---- Search ----
    function search() {
        var q = input.value.trim().toLowerCase();
        var words = q.split(/\s+/).filter(function (w) { return w.length > 0; });

        if (q.length < 2) {
            items.forEach(function (item) {
                item.hidden = false;
                if (item.dataset.hpOpenedBySearch) { item.open = false; delete item.dataset.hpOpenedBySearch; }
            });
            sections.forEach(function (s) { s.hidden = false; });
            topicLinks.forEach(function (a) { a.parentElement.hidden = false; });
            empty.hidden = true;
            count.textContent = '';
            return;
        }

        var shown = 0;
        items.forEach(function (item) {
            var match = words.every(function (w) { return item._hpText.indexOf(w) !== -1; });
            item.hidden = !match;
            if (match) {
                shown++;
                if (!item.open) { item.open = true; item.dataset.hpOpenedBySearch = '1'; }
            }
        });
        sections.forEach(function (s) {
            var any = !!s.querySelector('.hp-item:not([hidden])');
            s.hidden = !any;
            var link = topicsBox.querySelector('a[data-topic="' + s.id + '"]');
            if (link) link.parentElement.hidden = !any;
        });
        empty.hidden = shown > 0;
        count.textContent = shown === 0 ? '' : shown + (shown === 1 ? ' answer' : ' answers');
    }
    input.addEventListener('input', search);
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && input.value) { input.value = ''; search(); }
    });
    // "/" jumps to search, as on most help sites
    document.addEventListener('keydown', function (e) {
        if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return;
        var t = e.target;
        if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable)) return;
        e.preventDefault();
        input.focus();
    });

    // ---- Deep links: help#question-id ----
    function openFromHash() {
        var id = decodeURIComponent(location.hash.slice(1));
        if (!id) return;
        var el = document.getElementById(id);
        if (!el || !el.classList.contains('hp-item')) return;   // section links scroll natively
        if (input.value) { input.value = ''; search(); }
        el.open = true;
        el.classList.remove('is-arrived');
        void el.offsetWidth;                                   // restart the highlight
        el.classList.add('is-arrived');
        el.scrollIntoView({ block: 'start' });
        var summary = el.querySelector('summary');
        if (summary) summary.focus({ preventScroll: true });
    }
    window.addEventListener('hashchange', openFromHash);
    openFromHash();

    // ---- Topic list follows the section being read ----
    function setCurrent(id) {
        topicLinks.forEach(function (a) {
            var on = a.getAttribute('data-topic') === id;
            if (on) a.setAttribute('aria-current', 'true'); else a.removeAttribute('aria-current');
            // In the chip row (narrow screens), keep the current chip in view without
            // scrolling the page itself
            if (on && topicsBox.scrollWidth > topicsBox.clientWidth) {
                var left = a.offsetLeft - 16, right = a.offsetLeft + a.offsetWidth + 16;
                if (left < topicsBox.scrollLeft) topicsBox.scrollLeft = left;
                else if (right > topicsBox.scrollLeft + topicsBox.clientWidth) topicsBox.scrollLeft = right - topicsBox.clientWidth;
            }
        });
    }
    // Current = the last visible section whose heading has passed just under the
    // sticky nav; before any has (top of the page), the first one. (A band-based
    // IntersectionObserver left the old topic highlighted at the very top.)
    var ticking = false;
    function updateCurrent() {
        ticking = false;
        var shown = sections.filter(function (s) { return !s.hidden; });
        if (!shown.length) return;
        var current = shown[0];
        shown.forEach(function (s) { if (s.getBoundingClientRect().top <= 140) current = s; });
        // Scrolled to the very bottom: the last section can't reach the top, so pick it
        if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2) current = shown[shown.length - 1];
        setCurrent(current.id);
    }
    window.addEventListener('scroll', function () {
        if (!ticking) { ticking = true; requestAnimationFrame(updateCurrent); }
    }, { passive: true });
    input.addEventListener('input', function () { requestAnimationFrame(updateCurrent); });
    updateCurrent();
})();
