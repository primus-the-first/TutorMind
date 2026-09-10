/**
 * TutorMind Interactive Widgets (Brilliant-style in-chat components).
 *
 * The AI emits fenced ```tm-<type> blocks holding one JSON object; Parsedown
 * turns each into <pre><code class="language-tm-<type>">{json}</code></pre>
 * (the fence language grammar forbids ':', hence 'tm-check', not 'tm:check').
 * This module swaps those blocks for tappable widgets.
 *
 * Exposes window.TMWidgets:
 *   init({ onReply, onReveal })  — onReply(text, opts): send text as the student's
 *       message; opts.silent skips the visible bubble. onReveal(el): re-run
 *       MathJax/scroll on freshly revealed content.
 *   render(container, opts)      — swap raw tm-* blocks already in the DOM.
 *       opts.solvedChecks: optional Set of tm-check question strings already
 *       answered correctly earlier in the conversation (re-render as locked/solved).
 *       opts.solvedChips: optional Map of tm-chips question -> chosen option text.
 *       opts.solvedTasks: optional Map of tm-task question -> submitted answer text.
 *       Same idea as solvedChecks — re-render those widgets already locked in.
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
    // opts.silent skips the visible chat bubble for synthesized text (e.g. the
    // tm-check "I chose X" echo) whose content is already shown in the widget.
    function reply(text, opts) {
        if (!text || !onReply) return;
        try { onReply(text, opts || {}); } catch (e) { console.error('tm-widget: reply failed', e); }
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

    function tmShell(kindLabel, kind) {
        var w = document.createElement('div');
        w.className = 'tm-widget tm-enter';
        if (kind) w.setAttribute('data-tm-kind', kind);
        if (kindLabel) {
            var k = document.createElement('div');
            k.className = 'tm-widget-kind';
            var icon = document.createElement('span');
            icon.className = 'tm-widget-icon';
            icon.setAttribute('aria-hidden', 'true');
            k.appendChild(icon);
            var label = document.createElement('span');
            label.textContent = kindLabel;
            k.appendChild(label);
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
    // data.__solvedChoice (optional) marks a chip question already answered in an
    // earlier turn (detected from chat history) — see extractSolvedChipChoice() in
    // tutor_mysql.js. Renders straight into the locked, picked state.
    function buildChips(data) {
        var w = tmShell('Quick reply', 'chips');
        if (data.q) w.appendChild(tmQuestion(data.q));
        var row = document.createElement('div');
        row.className = 'tm-chips';
        var solved = data.__solvedChoice;
        if (solved) row.classList.add('tm-done');
        (data.options || []).forEach(function (opt) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'tm-chip';
            b.textContent = opt;
            if (solved) {
                b.setAttribute('disabled', '');
                if (opt === solved) b.classList.add('tm-picked');
            } else {
                b.addEventListener('click', function () {
                    if (row.classList.contains('tm-done')) return;
                    row.classList.add('tm-done');
                    b.classList.add('tm-picked');
                    // Silent: the pick is already shown in the widget, so the
                    // AI is pinged in the background without a redundant chat bubble.
                    reply('For the question "' + data.q + '", I chose "' + opt + '".', { silent: true });
                });
            }
            row.appendChild(b);
        });
        w.appendChild(row);
        return w;
    }

    // Multiple-choice check with instant green/red feedback and per-option explanations.
    // data.__solved marks a check the student already answered correctly in an earlier
    // turn (detected from chat history) — it renders straight into the locked, correct
    // state instead of waiting for a tap, so re-visiting the conversation doesn't reset it.
    function buildCheck(data) {
        var w = tmShell('Check · tap an answer', 'check');
        if (data.q) w.appendChild(tmQuestion(data.q));
        var opts = document.createElement('div');
        opts.className = 'tm-opts';
        var explain = Array.isArray(data.explain) ? data.explain : [];
        var answer = Number(data.answer);
        var verdict = document.createElement('div');
        verdict.className = 'tm-verdict';
        verdict.setAttribute('role', 'status');
        verdict.style.display = 'none';
        var solved = !!data.__solved;

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
            if (solved) {
                b.setAttribute('disabled', '');
                if (i === answer) b.classList.add('tm-correct');
            } else {
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
                        // instead of stalling until the student types something. The verdict
                        // is already shown in the widget, so send it silently — no duplicate bubble.
                        reply('For the check "' + data.q + '", I chose "' + opt + '" — the correct answer.', { silent: true });
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
            }
            opts.appendChild(b);
        });
        w.appendChild(opts);

        if (solved) {
            verdict.className = 'tm-verdict tm-v-good';
            verdict.innerHTML = '<div class="tm-verdict-title">Correct!</div>';
            if (explain[answer]) verdict.appendChild(document.createTextNode(explain[answer]));
            verdict.style.display = 'block';
        }

        w.appendChild(verdict);
        return w;
    }

    // Progressive hint ladder — hints unlock one at a time, in order.
    function buildHints(data, kindLabel) {
        var w = tmShell(kindLabel || 'Stuck? Reveal a hint', 'hints');
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

    // Draw a static graph shape (author-placed x/y, no layout algorithm) with a
    // per-step highlight/dist delta merged in. graphShape: {nodes, edges}. visual:
    // {highlight: {nodes:[ids], edges:[[from,to]]}, dist: {id: label}}.
    // Shared logical coordinate box for every small node/edge graph canvas
    // (tm-steps graph visuals, tm-path) — author-placed x/y, no layout algorithm.
    var GRAPH_LOGICAL_W = 220, GRAPH_LOGICAL_H = 160, GRAPH_NODE_RADIUS = 14;

    // Draw a graph shape (nodes/edges with author-placed x/y) onto a canvas
    // context already scaled for DPR. opts:
    //   hiNodes    — array of highlighted node ids (tm-steps + tm-path)
    //   hiEdges    — array of [from, to] pairs to draw thicker/highlighted
    //   dist       — {id: label} badge shown above a node (tm-steps only)
    //   currentNodeId — draws a "you are here" ring around one node (tm-path only)
    //   wrongNodeId   — fills one node with the bad/error color (tm-path wrong-tap flash)
    function drawGraphOnCanvas(ctx, graphShape, opts) {
        opts = opts || {};
        var nodes = (graphShape && graphShape.nodes) || [];
        var edges = (graphShape && graphShape.edges) || [];
        var hiNodes = Array.isArray(opts.hiNodes) ? opts.hiNodes : [];
        var hiEdges = Array.isArray(opts.hiEdges) ? opts.hiEdges : [];
        var dist = opts.dist || {};
        var currentNodeId = opts.currentNodeId;
        var wrongNodeId = opts.wrongNodeId;

        var byId = {};
        nodes.forEach(function (n) { byId[n.id] = n; });

        function isHiEdge(from, to) {
            return hiEdges.some(function (pair) {
                return (pair[0] === from && pair[1] === to) || (pair[0] === to && pair[1] === from);
            });
        }

        var style = getComputedStyle(document.documentElement);
        var primary = style.getPropertyValue('--primary').trim() || '#7c3aed';
        var border = style.getPropertyValue('--border').trim() || '#ccc';
        var text = style.getPropertyValue('--text-primary').trim() || '#222';
        var bad = style.getPropertyValue('--tm-bad').trim() || '#ef4444';

        ctx.clearRect(0, 0, GRAPH_LOGICAL_W, GRAPH_LOGICAL_H);

        // Edges first, so nodes draw on top.
        edges.forEach(function (e) {
            var a = byId[e.from], b = byId[e.to];
            if (!a || !b) return;
            var hiE = isHiEdge(e.from, e.to);
            ctx.beginPath();
            ctx.moveTo(a.x, a.y);
            ctx.lineTo(b.x, b.y);
            ctx.strokeStyle = hiE ? primary : border;
            ctx.lineWidth = hiE ? 2.5 : 1.5;
            ctx.stroke();
            if (e.weight != null) {
                ctx.fillStyle = text;
                ctx.font = '10px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(String(e.weight), (a.x + b.x) / 2, (a.y + b.y) / 2 - 4);
            }
        });

        // Nodes.
        nodes.forEach(function (n) {
            var isHi = hiNodes.indexOf(n.id) !== -1;
            var isWrong = wrongNodeId != null && n.id === wrongNodeId;
            ctx.beginPath();
            ctx.arc(n.x, n.y, GRAPH_NODE_RADIUS, 0, Math.PI * 2);
            ctx.fillStyle = isWrong ? bad : (isHi ? primary : (document.body.classList.contains('dark-mode') ? '#241E32' : '#fff'));
            ctx.fill();
            ctx.strokeStyle = isWrong ? bad : (isHi ? primary : border);
            ctx.lineWidth = 2;
            ctx.stroke();

            if (currentNodeId != null && n.id === currentNodeId) {
                ctx.beginPath();
                ctx.arc(n.x, n.y, GRAPH_NODE_RADIUS + 5, 0, Math.PI * 2);
                ctx.strokeStyle = primary;
                ctx.lineWidth = 2;
                ctx.setLineDash([3, 3]);
                ctx.stroke();
                ctx.setLineDash([]);
            }

            ctx.fillStyle = (isHi || isWrong) ? '#fff' : text;
            ctx.font = 'bold 11px sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(String(n.label != null ? n.label : n.id), n.x, n.y);

            if (dist[n.id] != null) {
                ctx.fillStyle = text;
                ctx.font = '10px sans-serif';
                ctx.textBaseline = 'alphabetic';
                ctx.fillText(String(dist[n.id]), n.x, n.y - 18);
            }
        });
    }

    // Create a DPR-scaled canvas sized to the shared graph logical box.
    function createGraphCanvas(className) {
        var canvas = document.createElement('canvas');
        canvas.className = className;
        var dpr = window.devicePixelRatio || 1;
        canvas.width = GRAPH_LOGICAL_W * dpr;
        canvas.height = GRAPH_LOGICAL_H * dpr;
        var ctx = canvas.getContext('2d');
        ctx.scale(dpr, dpr);
        return { canvas: canvas, ctx: ctx };
    }

    function buildStepGraph(graphShape, visual) {
        var made = createGraphCanvas('tm-step-graph-canvas');
        var hi = (visual && visual.highlight) || {};
        drawGraphOnCanvas(made.ctx, graphShape, {
            hiNodes: Array.isArray(hi.nodes) ? hi.nodes : [],
            hiEdges: Array.isArray(hi.edges) ? hi.edges : [],
            dist: (visual && visual.dist) || {}
        });
        return made.canvas;
    }

    // Render an array/list snapshot with highlighted indices.
    function buildStepArray(visual) {
        var row = document.createElement('div');
        row.className = 'tm-step-array';
        var arr = (visual && visual.array) || [];
        var hi = (visual && visual.highlight) || [];
        arr.forEach(function (val, i) {
            var cell = document.createElement('div');
            cell.className = 'tm-step-cell' + (hi.indexOf(i) !== -1 ? ' tm-step-cell-hi' : '');
            cell.textContent = String(val);
            row.appendChild(cell);
        });
        return row;
    }

    // Worked example revealed one step at a time (optionally with a predict-first nudge).
    // Each entry in data.steps is either a plain string (as before) or
    // {text, visual} where visual is an array snapshot ({array, highlight}) or a
    // graph-state delta ({highlight, dist}) merged against the top-level data.graph
    // shape at draw time — the graph's node/edge positions are defined once, not
    // repeated per step.
    function buildSteps(data) {
        var w = tmShell('Worked example · reveal step by step', 'steps');
        if (data.q) w.appendChild(tmQuestion(data.q));
        var list = document.createElement('div');
        list.className = 'tm-steps';
        var rawSteps = (data.steps || []).map(function (s) {
            return typeof s === 'string' ? { text: s } : (s || {});
        });
        var steps = rawSteps.map(function (entry) {
            var row = document.createElement('div');
            row.className = 'tm-step';
            row.style.display = 'none';
            var n = document.createElement('span');
            n.className = 'tm-step-n';
            n.textContent = 'STEP ' + (list.children.length + 1);
            var body = document.createElement('span');
            body.textContent = entry.text || '';
            row.appendChild(n);
            row.appendChild(body);

            if (entry.visual) {
                row.classList.add('tm-has-visual');
                if (entry.visual.array) {
                    row.appendChild(buildStepArray(entry.visual));
                } else if (entry.visual.highlight || entry.visual.dist) {
                    row.appendChild(buildStepGraph(data.graph, entry.visual));
                }
            }

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
    // message. May carry an embedded hint ladder. data.__solvedAnswer (optional)
    // marks a task already answered in an earlier turn (detected from chat history)
    // — see extractSolvedTaskAnswer() in tutor_mysql.js. Renders straight into the
    // same locked state a live submission already produces.
    function buildTask(data) {
        var w = tmShell('Your turn', 'task');
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
        if (data.__solvedAnswer) {
            ta.value = data.__solvedAnswer;
            ta.setAttribute('disabled', '');
            btn.setAttribute('disabled', '');
        } else {
            btn.addEventListener('click', function () {
                var val = ta.value.trim();
                if (val.length < 2) { ta.focus(); return; }
                btn.setAttribute('disabled', '');
                ta.setAttribute('disabled', '');
                // Silent: the answer is already shown in the widget, so the AI is
                // pinged in the background without a redundant chat bubble.
                reply('For the task "' + data.q + '", I answered: ' + val, { silent: true });
            });
        }
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
        var w = tmShell('Put these in order', 'order');
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
        verdict.setAttribute('role', 'status');
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

    // Guided checkpoint navigation through a small node/edge graph. Unlike
    // tm-order (reorder a flat list), the student taps their way through an
    // actual spatially/structurally connected graph — correctness is by
    // position in data.path (mirrors tm-order's _correctIndex check), not by
    // graph adjacency, so the graph's edges stay purely illustrative/contextual.
    function buildPath(data) {
        var w = tmShell('Guide it · tap the next checkpoint', 'path');
        if (data.q) w.appendChild(tmQuestion(data.q));

        var graph = data.graph || {};
        var path = Array.isArray(data.path) ? data.path : [];
        var checkpoints = data.checkpoints || {};
        var idx = 0; // index into path already reached

        var made = createGraphCanvas('tm-path-canvas');
        made.canvas.style.cursor = 'pointer';
        w.appendChild(made.canvas);

        var status = document.createElement('div');
        status.className = 'tm-step-nudge tm-path-status';
        status.style.display = 'none';
        w.appendChild(status);

        var verdict = document.createElement('div');
        verdict.className = 'tm-verdict';
        verdict.setAttribute('role', 'status');
        verdict.style.display = 'none';
        w.appendChild(verdict);

        function redraw(wrongNodeId) {
            drawGraphOnCanvas(made.ctx, graph, {
                hiNodes: path.slice(0, idx + 1),
                currentNodeId: idx < path.length - 1 ? path[idx] : null,
                wrongNodeId: wrongNodeId
            });
        }
        redraw();

        var flashTimer = null;

        made.canvas.addEventListener('click', function (evt) {
            if (!path.length || idx >= path.length - 1) return; // no path, or already done

            var rect = made.canvas.getBoundingClientRect();
            if (!rect.width || !rect.height) return;
            // The canvas's on-screen size (CSS) differs from its logical
            // GRAPH_LOGICAL_W/H coordinate space, so click coordinates must be
            // rescaled before hit-testing against author-placed node positions.
            var scaleX = GRAPH_LOGICAL_W / rect.width;
            var scaleY = GRAPH_LOGICAL_H / rect.height;
            var x = (evt.clientX - rect.left) * scaleX;
            var y = (evt.clientY - rect.top) * scaleY;

            var hitNode = null;
            (graph.nodes || []).forEach(function (n) {
                var dx = x - n.x, dy = y - n.y;
                if (Math.sqrt(dx * dx + dy * dy) <= GRAPH_NODE_RADIUS + 4) hitNode = n;
            });
            if (!hitNode) return;

            if (hitNode.id === path[idx + 1]) {
                clearTimeout(flashTimer);
                idx++;
                redraw();

                var note = checkpoints[hitNode.id];
                if (note) {
                    status.textContent = note;
                    status.style.display = 'block';
                    reveal(status);
                }

                if (idx === path.length - 1) {
                    verdict.className = 'tm-verdict tm-v-good';
                    verdict.innerHTML = '<div class="tm-verdict-title">Done!</div>';
                    verdict.style.display = 'block';
                    reveal(verdict);
                }
            } else {
                clearTimeout(flashTimer);
                redraw(hitNode.id);
                flashTimer = setTimeout(function () { redraw(); }, 400);
            }
        });

        return w;
    }

    // Fill-in-the-blank code. data.code holds {{1}}, {{2}}… placeholders; each
    // entry in data.blanks gives that blank's options and correct option index.
    function buildCloze(data) {
        var w = tmShell('Complete the code', 'cloze');
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
        verdict.setAttribute('role', 'status');
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

    // ---- tm-graph: draggable slider graph ----
    // Safety: the AI only ever selects a template name (enum lookup) and numeric
    // coefficients — there is no eval()/new Function() anywhere in this widget, so
    // there is no path from AI-authored JSON to arbitrary code execution.
    function num(v, fallback) {
        var n = Number(v);
        return isFinite(n) ? n : fallback;
    }
    function clamp(v, lo, hi) {
        return Math.min(hi, Math.max(lo, v));
    }
    var TEMPLATE_FNS = {
        linear:      function (x, c) { return num(c.a, 1) * x + num(c.b, 0); },
        quadratic:   function (x, c) { return num(c.a, 1) * x * x + num(c.b, 0) * x + num(c.c, 0); },
        sine:        function (x, c) { return num(c.a, 1) * Math.sin(num(c.b, 1) * x + num(c.c, 0)) + num(c.d, 0); },
        cosine:      function (x, c) { return num(c.a, 1) * Math.cos(num(c.b, 1) * x + num(c.c, 0)) + num(c.d, 0); },
        exponential: function (x, c) { return num(c.a, 1) * Math.pow(clamp(num(c.b, 2), 0.01, 10), x); },
        absolute:    function (x, c) { return num(c.a, 1) * Math.abs(x - num(c.b, 0)) + num(c.c, 0); },
        // Rises from 0 and approaches a plateau (a) as x grows, rate of approach set
        // by b — the common "diminishing returns" shape (photosynthesis rate vs.
        // light/CO2, enzyme activity vs. substrate concentration, drug saturation),
        // which none of the other templates fit (exponential is unbounded growth,
        // not a plateau). Intended for x >= 0 domains.
        saturating:  function (x, c) { return num(c.a, 1) * (1 - Math.exp(-clamp(num(c.b, 1), 0.01, 20) * x)); }
    };

    function buildGraph(data) {
        var w = tmShell('Explore · drag the sliders', 'graph');
        if (data.q) w.appendChild(tmQuestion(data.q));

        var fn = TEMPLATE_FNS[data.template];
        if (!fn) {
            var fallback = document.createElement('div');
            fallback.className = 'tm-widget-q';
            fallback.textContent = 'This graph type isn\'t available.';
            w.appendChild(fallback);
            return w;
        }

        var domain = Array.isArray(data.domain) ? data.domain : [-10, 10];
        var range = Array.isArray(data.range) ? data.range : [-6, 6];
        var params = Array.isArray(data.params) ? data.params : [];

        var LOGICAL_W = 320, LOGICAL_H = 220;
        var canvas = document.createElement('canvas');
        canvas.className = 'tm-graph-canvas';
        var dpr = window.devicePixelRatio || 1;
        canvas.width = LOGICAL_W * dpr;
        canvas.height = LOGICAL_H * dpr;
        var ctx = canvas.getContext('2d');
        ctx.scale(dpr, dpr);
        w.appendChild(canvas);

        var coeffs = {};
        params.forEach(function (p) { coeffs[p.key] = num(p.default, 0); });

        function toPx(x, y) {
            var px = (x - domain[0]) / (domain[1] - domain[0]) * LOGICAL_W;
            var py = LOGICAL_H - (y - range[0]) / (range[1] - range[0]) * LOGICAL_H;
            return [px, py];
        }

        function draw() {
            var style = getComputedStyle(canvas.ownerDocument.documentElement);
            var primary = style.getPropertyValue('--primary').trim() || '#7c3aed';
            var border = style.getPropertyValue('--border').trim() || '#ccc';

            ctx.clearRect(0, 0, LOGICAL_W, LOGICAL_H);

            // Axes.
            ctx.strokeStyle = border;
            ctx.lineWidth = 1;
            var origin = toPx(0, 0);
            ctx.beginPath();
            ctx.moveTo(0, clamp(origin[1], 0, LOGICAL_H));
            ctx.lineTo(LOGICAL_W, clamp(origin[1], 0, LOGICAL_H));
            ctx.moveTo(clamp(origin[0], 0, LOGICAL_W), 0);
            ctx.lineTo(clamp(origin[0], 0, LOGICAL_W), LOGICAL_H);
            ctx.stroke();

            // Curve — one sample per horizontal pixel.
            ctx.strokeStyle = primary;
            ctx.lineWidth = 2;
            ctx.beginPath();
            var started = false;
            for (var px = 0; px <= LOGICAL_W; px++) {
                var x = domain[0] + (px / LOGICAL_W) * (domain[1] - domain[0]);
                var y = fn(x, coeffs);
                if (!isFinite(y)) { started = false; continue; }
                var yClamped = clamp(y, range[0] - 1, range[1] + 1);
                var pt = toPx(x, yClamped);
                if (!started) { ctx.moveTo(pt[0], pt[1]); started = true; }
                else ctx.lineTo(pt[0], pt[1]);
            }
            ctx.stroke();
        }
        draw();

        if (params.length) {
            var controls = document.createElement('div');
            controls.className = 'tm-graph-controls';
            params.forEach(function (p) {
                var row = document.createElement('div');
                row.className = 'tm-graph-row';
                var label = document.createElement('label');
                label.textContent = p.label || p.key;
                var input = document.createElement('input');
                input.type = 'range';
                input.min = String(num(p.min, -10));
                input.max = String(num(p.max, 10));
                input.step = String(num(p.step, 1));
                input.value = String(coeffs[p.key]);
                var val = document.createElement('span');
                val.className = 'tm-graph-val';
                val.textContent = input.value;
                input.addEventListener('input', function () {
                    coeffs[p.key] = num(input.value, coeffs[p.key]);
                    val.textContent = input.value;
                    draw();
                });
                row.appendChild(label);
                row.appendChild(input);
                row.appendChild(val);
                controls.appendChild(row);
            });
            w.appendChild(controls);
        }

        if (data.task) {
            var nudge = document.createElement('div');
            nudge.className = 'tm-step-nudge';
            nudge.textContent = data.task;
            w.appendChild(nudge);
        }

        return w;
    }

    // ---- tm-code: in-browser JavaScript runner ----
    // Executes inside a Web Worker (assets/js/tm-code-worker.js) — no DOM/network
    // access from within a worker — with a hard wall-clock timeout that terminates
    // the worker on an infinite loop. See tm-code-worker.js for the sandboxed side.
    function buildCode(data) {
        var w = tmShell('Run it · JavaScript', 'code');
        if (data.q) w.appendChild(tmQuestion(data.q));

        var starterText = Array.isArray(data.starter) ? data.starter.join('\n') : String(data.starter || '');

        if (data.language && data.language !== 'javascript') {
            var pre = document.createElement('div');
            pre.className = 'tm-code-editor';
            pre.style.whiteSpace = 'pre-wrap';
            pre.textContent = starterText;
            var note = document.createElement('div');
            note.className = 'tm-code-expected';
            note.textContent = 'This language isn\'t runnable yet.';
            w.appendChild(pre);
            w.appendChild(note);
            return w;
        }

        var editor = document.createElement('textarea');
        editor.className = 'tm-code-editor';
        editor.spellcheck = false;
        editor.value = starterText;
        w.appendChild(editor);

        var controls = document.createElement('div');
        controls.className = 'tm-code-controls';
        var runBtn = document.createElement('button');
        runBtn.type = 'button';
        runBtn.className = 'tm-check-btn';
        runBtn.textContent = 'Run';
        controls.appendChild(runBtn);
        if (data.expected) {
            var expected = document.createElement('span');
            expected.className = 'tm-code-expected';
            expected.textContent = 'Expected: ' + data.expected;
            controls.appendChild(expected);
        }
        w.appendChild(controls);

        var output = document.createElement('div');
        output.className = 'tm-code-output';
        output.setAttribute('aria-live', 'polite');
        w.appendChild(output);

        var MAX_LINES = 200, MAX_CHARS = 20000, TIMEOUT_MS = 3000;

        runBtn.addEventListener('click', function () {
            runBtn.setAttribute('disabled', '');
            output.className = 'tm-code-output';
            output.textContent = 'Running…';
            output.style.display = 'block';

            var worker = new Worker('assets/js/tm-code-worker.js');
            var done = false;

            var timer = setTimeout(function () {
                if (done) return;
                done = true;
                worker.terminate();
                output.className = 'tm-code-output tm-v-bad';
                output.textContent = 'Timed out after ' + (TIMEOUT_MS / 1000) + 's — check for an infinite loop.';
                runBtn.removeAttribute('disabled');
            }, TIMEOUT_MS);

            worker.onmessage = function (e) {
                if (done) return;
                done = true;
                clearTimeout(timer);
                worker.terminate();
                runBtn.removeAttribute('disabled');

                var lines = (e.data && e.data.lines) || [];
                var shown = lines.slice(0, MAX_LINES);
                var text = shown.join('\n');
                if (text.length > MAX_CHARS) text = text.slice(0, MAX_CHARS) + '\n… (truncated)';
                else if (lines.length > MAX_LINES) text += '\n… (truncated)';

                if (e.data && e.data.ok) {
                    output.className = 'tm-code-output' + (text ? '' : ' tm-empty');
                    output.textContent = text || '(no output — try adding a console.log)';
                } else {
                    output.className = 'tm-code-output tm-v-bad';
                    output.textContent = (text ? text + '\n' : '') + ((e.data && e.data.error) || 'Error running code.');
                }
            };

            worker.onerror = function (err) {
                if (done) return;
                done = true;
                clearTimeout(timer);
                worker.terminate();
                runBtn.removeAttribute('disabled');
                output.className = 'tm-code-output tm-v-bad';
                output.textContent = 'Error: ' + (err && err.message ? err.message : 'could not run code.');
            };

            worker.postMessage(editor.value);
        });

        return w;
    }

    // solvedChecks (optional Set of question strings) marks tm-check widgets the
    // student already answered correctly in an earlier turn — see buildCheck().
    // solved: { checks: Set<question>, chips: Map<question, choice>, tasks: Map<question, answer> }
    // — all optional, describing which widgets in this conversation were already
    // answered in an earlier turn so they render straight into their locked state.
    function buildTmWidget(spec, solved) {
        if (!spec || !spec.data) return null;
        solved = solved || {};
        switch (spec.type) {
            case 'chips': {
                var solvedChoice = solved.chips && solved.chips.get(String(spec.data.q || ''));
                return buildChips(solvedChoice ? Object.assign({}, spec.data, { __solvedChoice: solvedChoice }) : spec.data);
            }
            case 'check': {
                var isSolved = !!(solved.checks && solved.checks.has(String(spec.data.q || '')));
                return buildCheck(isSolved ? Object.assign({}, spec.data, { __solved: true }) : spec.data);
            }
            case 'hints': return buildHints(spec.data);
            case 'steps': return buildSteps(spec.data);
            case 'task':  {
                var solvedAnswer = solved.tasks && solved.tasks.get(String(spec.data.q || ''));
                return buildTask(solvedAnswer ? Object.assign({}, spec.data, { __solvedAnswer: solvedAnswer }) : spec.data);
            }
            case 'order': return buildOrder(spec.data);
            case 'cloze': return buildCloze(spec.data);
            case 'graph': return buildGraph(spec.data);
            case 'code':  return buildCode(spec.data);
            case 'path':  return buildPath(spec.data);
            default:      return null;
        }
    }

    // Swap any raw tm-* code blocks already in the DOM for their widgets.
    // Used for instant renders, SSR hydration, and history reload.
    // opts.solvedChecks: optional Set of tm-check question strings already answered
    // correctly earlier in this conversation (see tutor_mysql.js for how it's built).
    function renderInteractiveWidgets(container, opts) {
        if (!container || !container.querySelectorAll) return;
        var solved = {
            checks: opts && opts.solvedChecks,
            chips: opts && opts.solvedChips,
            tasks: opts && opts.solvedTasks
        };
        container.querySelectorAll('pre').forEach(function (pre) {
            var spec = parseTmSpec(pre);
            if (!spec) return;
            var widget = buildTmWidget(spec, solved);
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
