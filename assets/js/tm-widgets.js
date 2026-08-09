/**
 * TutorMind Interactive Widgets (Brilliant-style in-chat components).
 *
 * The AI emits fenced ```tm-<type> blocks holding one JSON object; Parsedown
 * turns each into <pre><code class="language-tm-<type>">{json}</code></pre>
 * (the fence language grammar forbids ':', hence 'tm-check', not 'tm:check').
 * This module swaps those blocks for tappable widgets.
 *
 * Exposes window.TMWidgets:
 *   init({ onReply, onReveal })  — onReply(text): send text as the student's
 *       message; onReveal(el): re-run MathJax/scroll on freshly revealed content.
 *   render(container)            — swap raw tm-* blocks already in the DOM.
 *   extract(html) -> {html,specs}— pull blocks out before a typewriter pass.
 *   fill(container, specs)       — fill the placeholders extract() left behind.
 *
 * Standalone: with no init() it still renders; onReply/onReveal simply no-op.
 */
(function () {
    'use strict';

    var onReply = null;
    var onReveal = null;

    // Send text back through the chat pipeline as if the student typed it,
    // so the AI reacts and server-side contact detection sees the interaction.
    function reply(text) {
        if (!text || !onReply) return;
        try { onReply(text); } catch (e) { console.error('tm-widget: reply failed', e); }
    }

    // Re-run MathJax / scroll on newly revealed content (verdicts, hints, steps).
    function reveal(el) {
        if (!onReveal) return;
        try { onReveal(el); } catch (e) { console.error('tm-widget: reveal failed', e); }
    }

    // Read a <pre> code block and, if it's a tm-* widget spec, return {type, data}.
    function parseTmSpec(pre) {
        var code = pre.querySelector('code');
        if (!code) return null;
        var langClass = Array.prototype.slice.call(code.classList)
            .find(function (c) { return /^language-tm-/i.test(c); });
        if (!langClass) return null;
        var type = langClass.replace(/^language-tm-/i, '').toLowerCase();
        var data;
        try {
            data = JSON.parse(code.textContent.trim());
        } catch (e) {
            console.warn('tm-widget: invalid JSON in', type, 'block — leaving as code', e);
            return null;
        }
        return { type: type, data: data };
    }

    // ---- Shared shell helpers ----

    function tmShell(kindLabel) {
        var w = document.createElement('div');
        w.className = 'tm-widget';
        if (kindLabel) {
            var k = document.createElement('div');
            k.className = 'tm-widget-kind';
            k.textContent = kindLabel;
            w.appendChild(k);
        }
        return w;
    }

    function tmQuestion(text) {
        var q = document.createElement('div');
        q.className = 'tm-widget-q';
        q.textContent = text || '';
        return q;
    }

    // ---- Individual widget builders. Each returns a DOM node. ----

    // Quick-reply chips → clicking submits that reply as the student's message.
    function buildChips(data) {
        var w = tmShell('Quick reply');
        if (data.q) w.appendChild(tmQuestion(data.q));
        var row = document.createElement('div');
        row.className = 'tm-chips';
        (data.options || []).forEach(function (opt) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'tm-chip';
            b.textContent = opt;
            b.addEventListener('click', function () {
                if (row.classList.contains('tm-done')) return;
                row.classList.add('tm-done');
                b.classList.add('tm-picked');
                reply(opt);
            });
            row.appendChild(b);
        });
        w.appendChild(row);
        return w;
    }

    // Multiple-choice check with instant green/red feedback and per-option explanations.
    function buildCheck(data) {
        var w = tmShell('Check · tap an answer');
        if (data.q) w.appendChild(tmQuestion(data.q));
        var opts = document.createElement('div');
        opts.className = 'tm-opts';
        var explain = Array.isArray(data.explain) ? data.explain : [];
        var answer = Number(data.answer);
        var verdict = document.createElement('div');
        verdict.className = 'tm-verdict';
        verdict.style.display = 'none';
        var solved = false;

        (data.options || []).forEach(function (opt, i) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'tm-opt';
            var key = document.createElement('span');
            key.className = 'tm-opt-key';
            key.textContent = String.fromCharCode(65 + i);
            var txt = document.createElement('span');
            txt.className = 'tm-opt-text';
            txt.textContent = opt;
            b.appendChild(key);
            b.appendChild(txt);
            b.addEventListener('click', function () {
                if (solved) return;
                if (i === answer) {
                    solved = true;
                    b.classList.add('tm-correct');
                    opts.querySelectorAll('.tm-opt').forEach(function (o) { o.setAttribute('disabled', ''); });
                    verdict.className = 'tm-verdict tm-v-good';
                    verdict.innerHTML = '<div class="tm-verdict-title">Correct!</div>';
                    if (explain[i]) verdict.appendChild(document.createTextNode(explain[i]));
                    verdict.style.display = 'block';
                    reveal(verdict);
                    // Always ping the AI so the lesson continues after a correct tap
                    // instead of stalling until the student types something.
                    reply('For the check "' + data.q + '", I chose "' + opt + '" — the correct answer.');
                } else {
                    b.classList.add('tm-wrong');
                    b.setAttribute('disabled', '');
                    verdict.className = 'tm-verdict tm-v-bad';
                    verdict.innerHTML = '<div class="tm-verdict-title">Not quite — try again</div>';
                    if (explain[i]) verdict.appendChild(document.createTextNode(explain[i]));
                    verdict.style.display = 'block';
                    reveal(verdict);
                }
            });
            opts.appendChild(b);
        });
        w.appendChild(opts);
        w.appendChild(verdict);
        return w;
    }

    // Progressive hint ladder — hints unlock one at a time, in order.
    function buildHints(data, kindLabel) {
        var w = tmShell(kindLabel || 'Stuck? Reveal a hint');
        if (data.q) w.appendChild(tmQuestion(data.q));
        var list = document.createElement('div');
        list.className = 'tm-hints';
        (data.hints || []).forEach(function (hint, i) {
            var row = document.createElement('div');
            row.className = 'tm-hint-row';
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'tm-hint-btn';
            btn.textContent = 'Hint ' + (i + 1);
            if (i > 0) btn.setAttribute('disabled', '');
            var text = document.createElement('div');
            text.className = 'tm-hint-text';
            text.textContent = hint;
            text.style.display = 'none';
            btn.addEventListener('click', function () {
                text.style.display = 'block';
                btn.setAttribute('disabled', '');
                reveal(text);
                var next = list.querySelectorAll('.tm-hint-btn')[i + 1];
                if (next) next.removeAttribute('disabled');
            });
            row.appendChild(btn);
            row.appendChild(text);
            list.appendChild(row);
        });
        w.appendChild(list);
        return w;
    }

    // Worked example revealed one step at a time (optionally with a predict-first nudge).
    function buildSteps(data) {
        var w = tmShell('Worked example · reveal step by step');
        if (data.q) w.appendChild(tmQuestion(data.q));
        var list = document.createElement('div');
        list.className = 'tm-steps';
        var steps = (data.steps || []).map(function (s) {
            var row = document.createElement('div');
            row.className = 'tm-step';
            row.style.display = 'none';
            var n = document.createElement('span');
            n.className = 'tm-step-n';
            n.textContent = 'STEP ' + (list.children.length + 1);
            var body = document.createElement('span');
            body.textContent = s;
            row.appendChild(n);
            row.appendChild(body);
            list.appendChild(row);
            return row;
        });
        w.appendChild(list);

        var nudge = null;
        if (data.predict) {
            nudge = document.createElement('div');
            nudge.className = 'tm-step-nudge';
            nudge.textContent = 'Predict what happens before you reveal it…';
            w.appendChild(nudge);
        }

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'tm-step-btn';
        btn.textContent = 'Show step 1';
        var idx = 0;
        btn.addEventListener('click', function () {
            if (idx >= steps.length) return;
            steps[idx].style.display = 'flex';
            reveal(steps[idx]);
            idx++;
            if (idx >= steps.length) {
                btn.setAttribute('hidden', '');
                if (nudge) nudge.style.display = 'none';
            } else {
                btn.textContent = 'Show step ' + (idx + 1);
            }
        });
        w.appendChild(btn);
        return w;
    }

    // "Your turn" short-answer task; submitting sends the answer as the student's
    // message. May carry an embedded hint ladder.
    function buildTask(data) {
        var w = tmShell('Your turn');
        if (data.q) w.appendChild(tmQuestion(data.q));
        var box = document.createElement('div');
        box.className = 'tm-answer';
        var ta = document.createElement('textarea');
        ta.placeholder = data.placeholder || 'Type your answer…';
        ta.setAttribute('aria-label', 'Your answer');
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'tm-check-btn';
        btn.textContent = 'Submit answer';
        btn.addEventListener('click', function () {
            var val = ta.value.trim();
            if (val.length < 2) { ta.focus(); return; }
            btn.setAttribute('disabled', '');
            ta.setAttribute('disabled', '');
            reply(val);
        });
        box.appendChild(ta);
        box.appendChild(btn);
        w.appendChild(box);
        if (Array.isArray(data.hints) && data.hints.length) {
            w.appendChild(buildHints({ hints: data.hints }, 'Stuck? Reveal a hint'));
        }
        return w;
    }

    // Sequence ordering — tap the items in the order they belong.
    // data.steps is authored in the CORRECT order; the widget shuffles for display.
    function buildOrder(data) {
        var w = tmShell('Put these in order');
        if (data.q) w.appendChild(tmQuestion(data.q));

        var correct = data.steps || [];
        // Pair each step with its correct index, then shuffle for display.
        var items = correct.map(function (text, i) { return { text: text, correctIndex: i }; });
        for (var i = items.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var tmp = items[i]; items[i] = items[j]; items[j] = tmp;
        }

        var hint = document.createElement('div');
        hint.className = 'tm-step-nudge';
        hint.textContent = 'Tap them one by one, in the order they happen.';
        w.appendChild(hint);

        var list = document.createElement('div');
        list.className = 'tm-order';
        var picked = [];   // entries in the order the learner tapped them
        var checked = false;

        var verdict = document.createElement('div');
        verdict.className = 'tm-verdict';
        verdict.style.display = 'none';

        var checkBtn = document.createElement('button');
        checkBtn.type = 'button';
        checkBtn.className = 'tm-check-btn';
        checkBtn.textContent = 'Check order';
        checkBtn.setAttribute('disabled', '');

        var resetBtn = document.createElement('button');
        resetBtn.type = 'button';
        resetBtn.className = 'tm-step-btn';
        resetBtn.textContent = 'Reset';

        function renumber() {
            list.querySelectorAll('.tm-order-item').forEach(function (el) {
                var pos = picked.indexOf(el);
                var badge = el.querySelector('.tm-order-badge');
                if (pos === -1) {
                    el.classList.remove('tm-picked');
                    badge.textContent = '';
                } else {
                    el.classList.add('tm-picked');
                    badge.textContent = String(pos + 1);
                }
            });
            if (picked.length === items.length) checkBtn.removeAttribute('disabled');
            else checkBtn.setAttribute('disabled', '');
        }

        items.forEach(function (item) {
            var el = document.createElement('button');
            el.type = 'button';
            el.className = 'tm-order-item';
            var badge = document.createElement('span');
            badge.className = 'tm-order-badge';
            var text = document.createElement('span');
            text.className = 'tm-order-text';
            text.textContent = item.text;
            el.appendChild(badge);
            el.appendChild(text);
            el._correctIndex = item.correctIndex;
            el.addEventListener('click', function () {
                if (checked) return;
                var at = picked.indexOf(el);
                if (at === -1) picked.push(el); else picked.splice(at, 1);
                renumber();
            });
            list.appendChild(el);
        });

        checkBtn.addEventListener('click', function () {
            if (checked || picked.length !== items.length) return;
            checked = true;
            var wrong = 0;
            picked.forEach(function (el, pos) {
                if (el._correctIndex === pos) {
                    el.classList.add('tm-correct');
                } else {
                    el.classList.add('tm-wrong');
                    wrong++;
                }
            });
            if (wrong === 0) {
                verdict.className = 'tm-verdict tm-v-good';
                verdict.innerHTML = '<div class="tm-verdict-title">Correct order!</div>';
                if (data.explain) verdict.appendChild(document.createTextNode(data.explain));
                checkBtn.setAttribute('hidden', '');
                resetBtn.setAttribute('hidden', '');
            } else {
                verdict.className = 'tm-verdict tm-v-bad';
                verdict.innerHTML = '<div class="tm-verdict-title">' + wrong +
                    (wrong === 1 ? ' item is' : ' items are') + ' out of place</div>';
                verdict.appendChild(document.createTextNode(
                    'The green ones are in the right spot. Reset and try moving the red ones.'));
                checkBtn.setAttribute('disabled', '');
            }
            verdict.style.display = 'block';
            reveal(verdict);
        });

        resetBtn.addEventListener('click', function () {
            checked = false;
            picked = [];
            list.querySelectorAll('.tm-order-item').forEach(function (el) {
                el.classList.remove('tm-correct', 'tm-wrong', 'tm-picked');
            });
            verdict.style.display = 'none';
            checkBtn.removeAttribute('hidden');
            renumber();
        });

        var controls = document.createElement('div');
        controls.className = 'tm-order-controls';
        controls.appendChild(resetBtn);
        controls.appendChild(checkBtn);

        w.appendChild(list);
        w.appendChild(controls);
        w.appendChild(verdict);
        return w;
    }

    // Fill-in-the-blank code. data.code holds {{1}}, {{2}}… placeholders; each
    // entry in data.blanks gives that blank's options and correct option index.
    function buildCloze(data) {
        var w = tmShell('Complete the code');
        if (data.q) w.appendChild(tmQuestion(data.q));

        var hint = document.createElement('div');
        hint.className = 'tm-step-nudge';
        hint.textContent = 'Tap a blank to cycle through the options, then check.';
        w.appendChild(hint);

        var blanks = data.blanks || [];
        var pre = document.createElement('pre');
        pre.className = 'tm-cloze-code';
        var code = document.createElement('code');
        var blankEls = [];
        var checked = false;

        // `code` is authored as an array of lines (no newline escaping needed in
        // JSON, which models get wrong); a plain multi-line string also works.
        var codeText = Array.isArray(data.code) ? data.code.join('\n') : String(data.code || '');

        // Split the code on {{n}} markers, interleaving text with blank buttons.
        var parts = codeText.split(/(\{\{\d+\}\})/);
        parts.forEach(function (part) {
            var m = part.match(/^\{\{(\d+)\}\}$/);
            if (!m) {
                if (part) code.appendChild(document.createTextNode(part));
                return;
            }
            var idx = parseInt(m[1], 10) - 1;
            var spec = blanks[idx];
            if (!spec) { code.appendChild(document.createTextNode(part)); return; }
            var opts = spec.options || [];
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'tm-cloze-blank';
            btn.textContent = '?';
            btn._choice = -1;
            btn._blankIndex = idx;
            btn.addEventListener('click', function () {
                if (checked || !opts.length) return;
                btn._choice = (btn._choice + 1) % opts.length;
                btn.textContent = opts[btn._choice];
                btn.classList.add('tm-filled');
                if (blankEls.every(function (b) { return b._choice !== -1; })) {
                    checkBtn.removeAttribute('disabled');
                }
            });
            blankEls.push(btn);
            code.appendChild(btn);
        });
        pre.appendChild(code);
        w.appendChild(pre);

        var verdict = document.createElement('div');
        verdict.className = 'tm-verdict';
        verdict.style.display = 'none';

        var checkBtn = document.createElement('button');
        checkBtn.type = 'button';
        checkBtn.className = 'tm-check-btn';
        checkBtn.textContent = 'Check';
        checkBtn.setAttribute('disabled', '');
        checkBtn.addEventListener('click', function () {
            if (checked) return;
            var wrong = 0;
            blankEls.forEach(function (b) {
                var spec = blanks[b._blankIndex] || {};
                if (b._choice === Number(spec.answer)) {
                    b.classList.add('tm-correct');
                } else {
                    b.classList.add('tm-wrong');
                    wrong++;
                }
            });
            if (wrong === 0) {
                checked = true;
                verdict.className = 'tm-verdict tm-v-good';
                verdict.innerHTML = '<div class="tm-verdict-title">That compiles!</div>';
                if (data.explain) verdict.appendChild(document.createTextNode(data.explain));
                checkBtn.setAttribute('hidden', '');
            } else {
                verdict.className = 'tm-verdict tm-v-bad';
                verdict.innerHTML = '<div class="tm-verdict-title">Not yet — ' + wrong +
                    (wrong === 1 ? ' blank is' : ' blanks are') + ' wrong</div>';
                verdict.appendChild(document.createTextNode('Keep tapping the red blanks to try other options.'));
                // Let them keep trying: clear the red marks on next interaction.
                blankEls.forEach(function (b) {
                    if (b.classList.contains('tm-wrong')) {
                        b.addEventListener('click', function clear() {
                            b.classList.remove('tm-wrong');
                            b.removeEventListener('click', clear);
                        });
                    }
                });
            }
            verdict.style.display = 'block';
            reveal(verdict);
        });

        var controls = document.createElement('div');
        controls.className = 'tm-order-controls';
        controls.appendChild(checkBtn);
        w.appendChild(controls);
        w.appendChild(verdict);
        return w;
    }

    function buildTmWidget(spec) {
        if (!spec || !spec.data) return null;
        switch (spec.type) {
            case 'chips': return buildChips(spec.data);
            case 'check': return buildCheck(spec.data);
            case 'hints': return buildHints(spec.data);
            case 'steps': return buildSteps(spec.data);
            case 'task':  return buildTask(spec.data);
            case 'order': return buildOrder(spec.data);
            case 'cloze': return buildCloze(spec.data);
            default:      return null;
        }
    }

    // Swap any raw tm-* code blocks already in the DOM for their widgets.
    // Used for instant renders, SSR hydration, and history reload.
    function renderInteractiveWidgets(container) {
        if (!container || !container.querySelectorAll) return;
        container.querySelectorAll('pre').forEach(function (pre) {
            var spec = parseTmSpec(pre);
            if (!spec) return;
            var widget = buildTmWidget(spec);
            if (!widget) return;
            var target = pre.closest('.code-block') || pre;
            target.replaceWith(widget);
        });
    }

    // Pull tm-* blocks out of HTML before the typewriter runs (so raw JSON never
    // streams into view), leaving indexed placeholders to fill afterwards.
    function extractTmBlocks(html) {
        var tmp = document.createElement('div');
        tmp.innerHTML = html;
        var specs = [];
        tmp.querySelectorAll('pre').forEach(function (pre) {
            var spec = parseTmSpec(pre);
            if (!spec) return;
            var slot = document.createElement('div');
            slot.className = 'tm-widget-slot';
            slot.setAttribute('data-tm-index', String(specs.length));
            pre.replaceWith(slot);
            specs.push(spec);
        });
        return { html: tmp.innerHTML, specs: specs };
    }

    // Replace the placeholders left by extractTmBlocks with real widgets.
    function fillTmSlots(container, specs) {
        container.querySelectorAll('.tm-widget-slot').forEach(function (slot) {
            var spec = specs[Number(slot.getAttribute('data-tm-index'))];
            var widget = buildTmWidget(spec);
            if (widget) slot.replaceWith(widget); else slot.remove();
        });
    }

    window.TMWidgets = {
        init: function (opts) {
            opts = opts || {};
            if (typeof opts.onReply === 'function') onReply = opts.onReply;
            if (typeof opts.onReveal === 'function') onReveal = opts.onReveal;
        },
        render: renderInteractiveWidgets,
        extract: extractTmBlocks,
        fill: fillTmSlots
    };
})();
