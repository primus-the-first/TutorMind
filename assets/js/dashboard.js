/**
 * "Your learning" dashboard (dashboard.php). Data: api/analytics.php.
 *
 * Order of questions it answers: what do I pick up, what's worth another look,
 * is my streak alive, how well is it sticking, which subjects am I on.
 * The period chips only change the summary band; everything else is "now".
 *
 * "Try again" / "Pick it up" hand a prompt to the chat through sessionStorage
 * (tm_review_prompt, read by review-handoff.js) — prefilled, never auto-sent.
 */
(function () {
    'use strict';

    const content = document.getElementById('dbContent');
    const PERIODS = [['7days', '7 days', 'the 7 days before'], ['30days', '30 days', 'the 30 days before'],
                     ['90days', '90 days', 'the 90 days before'], ['all', 'All time', null]];
    const REVIEWED_KEY = 'tm-reviewed-quizzes';
    const TIPS = {
        free_recall: 'Explain a topic back to the tutor without notes. That is the skill this score measures.',
        application: 'Ask the tutor for a fresh problem that uses what you just learned.',
        cued: 'Before asking for a hint, write down what you already remember.',
        recognition: 'Try answering before you look at the options.',
    };

    let period = '30days';
    let data = null;
    let reviewItems = [];   // what the review card currently shows (buttons index into it)

    // ── helpers ────────────────────────────────────────────
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const icon = (name, cls = '') => `<svg class="ds-i ${cls}" aria-hidden="true"><use href="#i-${name}"/></svg>`;
    const plural = (n, one, many = one + 's') => `${n} ${n === 1 ? one : many}`;
    const read = (key, fallback) => { try { return JSON.parse(localStorage.getItem(key)) ?? fallback; } catch (e) { return fallback; } };
    const write = (key, val) => { try { localStorage.setItem(key, JSON.stringify(val)); } catch (e) {} };

    // Timestamps and "today" both come from MySQL, so day differences use one clock.
    const dayNum = s => { const [y, m, d] = String(s).slice(0, 10).split('-').map(Number); return Date.UTC(y, m - 1, d) / 86400000; };
    function ago(ts) {
        const days = dayNum(data.today) - dayNum(ts);
        if (days <= 0) return 'today';
        if (days === 1) return 'yesterday';
        if (days < 14) return `${days} days ago`;
        if (days < 60) return `${Math.round(days / 7)} weeks ago`;
        return `${Math.round(days / 30)} months ago`;
    }
    function minutes(m) {
        if (m < 60) return `${m}m`;
        const h = Math.floor(m / 60), r = m % 60;
        return r ? `${h}h ${r}m` : `${h}h`;
    }
    const segs = (done, total) => total > 0
        ? `<div class="db-segs" aria-hidden="true">${Array.from({ length: total }, (_, i) => `<span${i < done ? ' class="is-on"' : ''}></span>`).join('')}</div>`
        : '';
    const covered = (done, total) => total > 0 ? `${done} of ${total} milestones covered` : 'No milestones yet';

    function handoff(conversationId, prompt) {
        try { sessionStorage.setItem('tm_review_prompt', JSON.stringify({ conversationId, prompt })); } catch (e) {}
        location.href = conversationId ? `chat/${conversationId}` : 'chat';
    }

    // ── greeting (the learner's own clock) ─────────────────
    (function greet() {
        const el = document.getElementById('dbGreeting');
        const h = new Date().getHours();
        const part = h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
        el.textContent = el.dataset.name ? `${part}, ${el.dataset.name}` : part;
    })();

    // ── sections ───────────────────────────────────────────
    function summaryHTML() {
        const s = data.summary;
        const [, , before] = PERIODS.find(p => p[0] === period);
        const diff = (cur, prev) => {
            if (!s.prev) return 'All time';
            const d = cur - prev;
            return d > 0 ? `${d} more than ${before}` : d < 0 ? `${-d} fewer than ${before}` : `Same as ${before}`;
        };
        const chips = PERIODS.map(([key, label]) =>
            `<button type="button" class="ds-chip" data-period="${key}" aria-pressed="${key === period}">${label}</button>`).join('');
        return `
            <div class="db-summary__head">
                <span class="db-summary__label" id="dbPeriodLabel">Numbers cover</span>
                <div class="ds-chip-row" role="group" aria-labelledby="dbPeriodLabel">${chips}</div>
            </div>
            <div class="db-summary__band">
                <div class="db-stat">
                    <span class="db-stat__value">${s.sessions}</span>
                    <span class="db-stat__label">${s.sessions === 1 ? 'study session' : 'study sessions'}</span>
                    <span class="db-stat__note">${diff(s.sessions, s.prev?.sessions)}</span>
                </div>
                <div class="db-stat">
                    <span class="db-stat__value">${s.activeDays}</span>
                    <span class="db-stat__label">${s.activeDays === 1 ? 'day you studied' : 'days you studied'}</span>
                    <span class="db-stat__note">${s.prev ? `${s.prev.activeDays} in ${before}` : 'All time'}</span>
                </div>
                <div class="db-stat">
                    <span class="db-stat__value">${minutes(s.focusMinutes)}</span>
                    <span class="db-stat__label">focused time</span>
                    <span class="db-stat__note">${s.pomodorosDone ? `Across ${plural(s.pomodorosDone, 'finished Pomodoro')}` : 'Use Pomodoro mode in a session to track this'}</span>
                </div>
            </div>`;
    }

    function continueHTML() {
        const c = data.continue;
        const others = data.recent.slice(0, 2).map(r => `
            <a class="db-row" href="chat/${r.id}">
                <span class="db-row__title">${esc(r.title)}</span>
                <span class="db-row__meta">${ago(r.updatedAt)}</span>
                ${icon('chevron')}
            </a>`).join('');
        return `
            <div class="db-card__kind">
                <span>Pick up where you left off</span>
                ${c.topic ? `<span class="db-tag">${esc(c.topic)}</span>` : ''}
            </div>
            <h2 class="db-continue__title">${esc(c.title)}</h2>
            <div class="db-continue__progress">
                <div class="db-continue__meta"><span>Last opened ${ago(c.updatedAt)}</span>${c.milestonesTotal ? `<span>${covered(c.milestonesDone, c.milestonesTotal)}</span>` : ''}</div>
                ${segs(c.milestonesDone, c.milestonesTotal)}
                ${c.nextMilestone ? `<p class="db-continue__next"><span>Next up:</span> ${esc(c.nextMilestone)}</p>` : ''}
            </div>
            <div class="db-continue__actions">
                <a class="ds-btn ds-btn--cta" href="chat/${c.id}">Continue session ${icon('arrow', 'ds-i-arrow')}</a>
            </div>
            ${others ? `<div class="db-rows"><span class="db-rows__label">Also recent</span>${others}</div>` : ''}`;
    }

    function streakHTML() {
        const st = data.streak;
        const next = st.days + 1;
        const sub = st.studiedToday ? `You studied today. Come back tomorrow to make it ${next}.`
            : st.days > 0 ? `Study today to make it ${next}.` : 'Study today to start one.';
        const todayUTC = dayNum(data.today) * 86400000;
        const days = st.last7.map((v, i) => {
            const isToday = i === 6;
            const label = isToday ? 'Today' : new Date(todayUTC - (6 - i) * 86400000).toLocaleDateString('en-US', { weekday: 'short', timeZone: 'UTC' });
            const state = v === true ? 'is-done' : isToday ? 'is-today' : 'is-miss';
            const said = v === true ? 'studied' : isToday ? 'not yet' : 'no study';
            return `<li class="db-day ${state}"><span class="db-day__box"></span><span class="db-day__label">${label}</span><span class="ds-visually-hidden">: ${said}</span></li>`;
        }).join('');
        return `
            <span class="db-card__kind"><span>Your streak</span></span>
            <div class="db-streak__count">
                <svg class="db-arch" viewBox="0 0 40 40" aria-hidden="true"><path d="M6 30 C 6 20, 14 12, 20 12 C 26 12, 34 20, 34 30"/><circle cx="20" cy="8" r="3" class="db-arch__dot"/><circle cx="6" cy="30" r="2.5"/><circle cx="34" cy="30" r="2.5"/></svg>
                <span>${plural(st.days, 'day')}</span>
            </div>
            <p class="db-muted">${sub}</p>
            <ol class="db-week" aria-label="Last 7 days">${days}</ol>
            ${heatHTML()}`;
    }

    function reviewHTML() {
        const done = new Set(read(REVIEWED_KEY, []));
        const items = data.review.filter(r => r.kind !== 'quiz' || !done.has(r.quizId)).slice(0, 3);
        const list = items.map((r, i) => r.kind === 'quiz' ? `
            <li class="db-review__item">
                <div class="db-review__text">
                    <span class="db-review__kind">Missed question${r.topic ? ` · ${esc(r.topic)}` : ''}</span>
                    <span class="db-review__q">${esc(r.question)}</span>
                    <span class="db-review__meta">Scored ${r.score}% · ${ago(r.answeredAt)}</span>
                </div>
                <button type="button" class="ds-btn ds-btn--secondary ds-btn--sm" data-review="${i}">${icon('retry')}Try again</button>
            </li>` : `
            <li class="db-review__item">
                <div class="db-review__text">
                    <span class="db-review__kind">Gone quiet · ${esc(r.topic)}</span>
                    <span class="db-review__q">Last studied ${ago(r.lastStudied)}</span>
                    <span class="db-review__meta">${covered(r.milestonesDone, r.milestonesTotal)}</span>
                </div>
                <button type="button" class="ds-btn ds-btn--secondary ds-btn--sm" data-review="${i}">Pick it up</button>
            </li>`).join('');
        reviewItems = items;
        return `
            <h2 class="db-card__title">Worth another look</h2>
            ${items.length
                ? `<p class="db-muted">Picked from questions you missed and subjects you have not opened lately.</p><ul class="db-review__list">${list}</ul>`
                : `<p class="db-muted">Nothing to catch up on. Quiz questions you miss come back here, ready to try again.</p>`}`;
    }

    function recallHTML() {
        const r = data.recall;
        if (!r.length) {
            return `
                <h2 class="db-card__title">How well it sticks</h2>
                <p class="db-muted">Finish a Pomodoro during a session and answer its quick recall quiz. Your results show up here.</p>`;
        }
        const total = r.reduce((n, x) => n + x.count, 0);
        const rows = r.map((x, i) => `
            <li class="db-recall__row">
                <div class="db-recall__line">
                    <span class="db-recall__label">${esc(x.label)}</span>
                    ${i === 0 && r.length > 1 ? '<span class="db-tag">Weakest</span>' : ''}
                    <span class="db-recall__pct">${x.avg}%</span>
                </div>
                <div class="db-bar" aria-hidden="true"><span style="width:${Math.max(0, Math.min(100, x.avg))}%"></span></div>
            </li>`).join('');
        return `
            <h2 class="db-card__title">How well it sticks</h2>
            <p class="db-muted">Quiz results by how the question was asked.</p>
            <ul class="db-recall__list">${rows}</ul>
            ${r.length > 1 && TIPS[r[0].type] ? `<p class="db-tip">${TIPS[r[0].type]}</p>` : ''}
            <span class="db-foot">Based on ${plural(total, 'answer')} in the last 90 days</span>`;
    }

    function subjectsHTML() {
        return `
            <div class="db-section__head">
                <h2 class="db-card__title">Your subjects</h2>
                <span class="db-muted">Most recent first</span>
            </div>
            <div class="db-subjects__grid">
                ${data.subjects.map(s => `
                <a class="db-subject" href="chat/${s.conversationId}">
                    <span class="db-subject__top"><span class="db-subject__name">${esc(s.topic)}</span>${icon('arrow')}</span>
                    ${segs(s.milestonesDone, s.milestonesTotal)}
                    <span class="db-subject__meta">
                        <span>${covered(s.milestonesDone, s.milestonesTotal)}</span>
                        <span class="db-muted">${plural(s.sessions, 'session')} · last studied ${ago(s.lastStudied)}</span>
                    </span>
                </a>`).join('')}
            </div>`;
    }

    // Desktop shows this inside the streak card; narrow screens show the 7-day strip instead.
    function heatHTML() {
        // 13 columns (Mon–Sun), the last holding this week up to today.
        const today = dayNum(data.today);
        const dow = (new Date(today * 86400000).getUTCDay() + 6) % 7; // Mon = 0
        const start = today - dow - 12 * 7;
        const iso = n => new Date(n * 86400000).toISOString().slice(0, 10);
        const pretty = n => new Date(n * 86400000).toLocaleDateString('en-US', { month: 'short', day: 'numeric', timeZone: 'UTC' });
        let active = 0;
        const cols = Array.from({ length: 13 }, (_, w) => `<div class="db-heat__col">${Array.from({ length: 7 }, (_, d) => {
            const n = start + w * 7 + d;
            if (n > today) return '<span class="db-heat__cell is-future"></span>';
            const c = data.heatmap[iso(n)] || 0;
            if (c) active++;
            const lvl = c === 0 ? 0 : c === 1 ? 1 : c <= 3 ? 2 : 3;
            return `<span class="db-heat__cell l${lvl}${n === today ? ' is-today' : ''}" title="${pretty(n)} · ${plural(c, 'session')}"></span>`;
        }).join('')}</div>`).join('');
        return `
            <div class="db-activity">
                <div class="db-activity__head">
                    <span class="db-activity__title">Past 12 weeks</span>
                    <span class="db-muted">${plural(active, 'day')} studied</span>
                </div>
                <div class="db-heat" role="img" aria-label="You studied on ${plural(active, 'day')} in the past 12 weeks">
                    <div class="db-heat__days" aria-hidden="true"><span>Mon</span><span></span><span>Wed</span><span></span><span>Fri</span><span></span><span>Sun</span></div>
                    <div>
                        <div class="db-heat__grid">${cols}</div>
                        <div class="db-heat__ends" aria-hidden="true"><span>${pretty(start)}</span><span>Today</span></div>
                    </div>
                </div>
                <div class="db-heat__legend" aria-hidden="true">Fewer <span class="db-heat__cell l0"></span><span class="db-heat__cell l1"></span><span class="db-heat__cell l2"></span><span class="db-heat__cell l3"></span> More</div>
            </div>`;
    }

    function firstVisitHTML() {
        return `
            <section class="db-first">
                <svg class="db-arch db-first__arch" viewBox="0 0 40 40" aria-hidden="true"><path d="M6 30 C 6 20, 14 12, 20 12 C 26 12, 34 20, 34 30"/><circle cx="20" cy="8" r="2.4" class="db-arch__dot"/><circle cx="6" cy="30" r="2"/><circle cx="34" cy="30" r="2"/></svg>
                <div class="db-first__text">
                    <h2 class="db-first__title">Start your first session</h2>
                    <p class="db-muted">Ask about anything you are studying, whether it is homework, a test next week or something you are curious about. Your streak starts the same day.</p>
                    <a class="ds-btn ds-btn--cta" href="chat">Start a session ${icon('arrow', 'ds-i-arrow')}</a>
                </div>
            </section>
            <section class="db-preview" aria-labelledby="dbPreviewTitle">
                <h2 class="db-card__title" id="dbPreviewTitle">What shows up here</h2>
                <div class="db-preview__grid">
                    <div class="db-preview__item">${icon('arrow')}<span class="db-preview__name">Pick up where you left off</span><span class="db-muted">Your last session and its next milestone, one tap away.</span></div>
                    <div class="db-preview__item">${icon('retry')}<span class="db-preview__name">Worth another look</span><span class="db-muted">Quiz questions you miss come back here, ready to try again.</span></div>
                    <div class="db-preview__item"><svg class="db-arch db-preview__arch" viewBox="0 0 40 40" aria-hidden="true"><path d="M6 30 C 6 20, 14 12, 20 12 C 26 12, 34 20, 34 30"/><circle cx="20" cy="8" r="3" class="db-arch__dot"/></svg><span class="db-preview__name">Your streak</span><span class="db-muted">Each day you study adds to it. Day one is your first session.</span></div>
                </div>
            </section>`;
    }

    // ── render ─────────────────────────────────────────────
    function render() {
        const sub = document.getElementById('dbHelloSub');
        if (!data.continue) {
            sub.textContent = 'This page shows what to pick up next, what to review and how well things are sticking. It fills in as you learn.';
            content.innerHTML = firstVisitHTML();
            return;
        }
        const st = data.streak;
        sub.textContent = st.studiedToday
            ? (st.days > 1 ? `You have studied ${st.days} days in a row, today included.` : 'You studied today. Nice.')
            : st.days > 0 ? `You have studied ${plural(st.days, 'day')} in a row. One session today makes it ${st.days + 1}.`
            : 'Pick up where you left off, or start something new.';

        // "Back to chat" returns to the newest conversation, not a blank chat.
        const newest = [data.continue, ...data.recent].sort((a, b) => String(b.updatedAt).localeCompare(String(a.updatedAt)))[0];
        document.getElementById('dbBack').href = `chat/${newest.id}`;

        content.innerHTML = `
            <div class="db-layout">
                <section class="db-summary" id="dbSummary" aria-label="Your numbers">${summaryHTML()}</section>
                <section class="db-card db-continue" aria-label="Pick up where you left off">${continueHTML()}</section>
                <section class="db-card db-streak" aria-label="Your streak">${streakHTML()}</section>
                <section class="db-card db-review" aria-label="Worth another look">${reviewHTML()}</section>
                <section class="db-card db-recall" aria-label="How well it sticks">${recallHTML()}</section>
                ${data.subjects.length ? `<section class="db-subjects" aria-label="Your subjects">${subjectsHTML()}</section>` : ''}
            </div>`;
    }

    // ── events ─────────────────────────────────────────────
    content.addEventListener('click', e => {
        const chip = e.target.closest('[data-period]');
        if (chip && chip.dataset.period !== period) {
            period = chip.dataset.period;
            loadSummary();
            return;
        }
        const btn = e.target.closest('[data-review]');
        if (btn) {
            const r = reviewItems[Number(btn.dataset.review)];
            if (!r) return;
            if (r.kind === 'quiz') {
                write(REVIEWED_KEY, [r.quizId, ...read(REVIEWED_KEY, []).filter(id => id !== r.quizId)].slice(0, 50));
                handoff(r.conversationId, `Ask me this again so I can have another go: "${r.question}"`);
            } else {
                handoff(r.conversationId, `Can we pick up ${r.topic} where we left off?`);
            }
        }
        const retry = e.target.closest('[data-reload]');
        if (retry) load();
    });

    // ── data ───────────────────────────────────────────────
    async function fetchData() {
        const res = await fetch(`api/analytics.php?period=${encodeURIComponent(period)}`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.error || 'Could not load your learning.');
        return json;
    }

    async function load() {
        content.setAttribute('aria-busy', 'true');
        try {
            data = await fetchData();
            render();
        } catch (err) {
            content.innerHTML = `
                <div class="db-card db-error" role="alert">
                    <h2 class="db-card__title">We could not load this page</h2>
                    <p class="db-muted">${esc(err.message)}</p>
                    <button type="button" class="ds-btn ds-btn--secondary ds-btn--sm" data-reload>Try again</button>
                </div>`;
        }
        content.setAttribute('aria-busy', 'false');
    }

    // Changing the period only swaps the summary band — the rest of the page stays put.
    async function loadSummary() {
        const box = document.getElementById('dbSummary');
        if (!box) return load();
        box.setAttribute('aria-busy', 'true');
        box.querySelectorAll('[data-period]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.period === period)));
        try {
            const fresh = await fetchData();
            data.summary = fresh.summary;
            box.innerHTML = summaryHTML();
            box.querySelector(`[data-period="${period}"]`)?.focus();
        } catch (err) {
            box.querySelector('.db-stat__note')?.replaceChildren(document.createTextNode('Could not update. Try again.'));
        }
        box.setAttribute('aria-busy', 'false');
    }

    load();
})();
