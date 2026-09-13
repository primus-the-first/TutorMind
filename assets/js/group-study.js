/**
 * Group Study page controller.
 *
 * Talks to api/group_study.php (create/join/teach/advance_turn/poll).
 * No WebSockets — polls every 3s while a session is active, the same
 * "cheap real-time" tradeoff documented in migrations/015's header
 * (this hosting can't sustain persistent connections, and nothing else
 * in the app polls today either).
 *
 * Reuses TMWidgets as-is to render the AI facilitator's tm-chips
 * hand-off block — but with its own onReply, since a chip pick here
 * means "advance the turn," not "reply to the AI in a normal chat."
 */
(function () {
    'use strict';

    var API = 'api/group_study.php';
    var POLL_INTERVAL_MS = 3000;

    var state = {
        sessionId: null,
        lastMessageId: 0,
        pollTimer: null,
        myDisplayName: document.body.dataset.displayName || '',
    };

    var els = {
        landing: document.getElementById('gsLanding'),
        waiting: document.getElementById('gsWaiting'),
        session: document.getElementById('gsSession'),
        landingError: document.getElementById('gsLandingError'),
        topicInput: document.getElementById('gsTopicInput'),
        createBtn: document.getElementById('gsCreateBtn'),
        joinCodeInput: document.getElementById('gsJoinCodeInput'),
        joinBtn: document.getElementById('gsJoinBtn'),
        waitingCode: document.getElementById('gsWaitingCode'),
        waitingTopic: document.getElementById('gsWaitingTopic'),
        sessionTopic: document.getElementById('gsSessionTopic'),
        currentTeacher: document.getElementById('gsCurrentTeacher'),
        participants: document.getElementById('gsParticipants'),
        transcript: document.getElementById('gsTranscript'),
        messageInput: document.getElementById('gsMessageInput'),
        sendBtn: document.getElementById('gsSendBtn'),
        composer: document.getElementById('gsComposer'),
        composerHint: document.getElementById('gsComposerHint'),
    };

    function showPanel(name) {
        els.landing.hidden = name !== 'landing';
        els.waiting.hidden = name !== 'waiting';
        els.session.hidden = name !== 'session';
    }

    function post(action, data) {
        return fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(Object.assign({ action: action }, data)),
        }).then(function (r) { return r.json(); });
    }

    function setError(msg) {
        els.landingError.textContent = msg || '';
        els.landingError.hidden = !msg;
    }

    // ---- Create / Join ----

    els.createBtn.addEventListener('click', function () {
        var topic = els.topicInput.value.trim();
        if (!topic) { setError('Enter a topic first.'); return; }
        setError('');
        els.createBtn.disabled = true;
        post('create', { topic: topic }).then(function (res) {
            els.createBtn.disabled = false;
            if (!res.success) { setError(res.error || 'Could not create a session.'); return; }
            state.sessionId = res.session_id;
            els.waitingCode.textContent = res.join_code;
            els.waitingTopic.textContent = res.topic;
            showPanel('waiting');
            startPolling();
        });
    });

    els.joinBtn.addEventListener('click', function () {
        var code = els.joinCodeInput.value.trim().toUpperCase();
        if (code.length !== 6) { setError('Join codes are 6 characters.'); return; }
        setError('');
        els.joinBtn.disabled = true;
        post('join', { join_code: code }).then(function (res) {
            els.joinBtn.disabled = false;
            if (!res.success) { setError(res.error || 'Could not join that session.'); return; }
            state.sessionId = res.session_id;
            if (res.status === 'waiting') {
                els.waitingCode.textContent = code;
                els.waitingTopic.textContent = res.topic;
                showPanel('waiting');
            } else {
                showPanel('session');
                renderSessionMeta(res);
            }
            startPolling();
        });
    });

    // ---- Teaching / messaging ----

    function sendMessage() {
        var text = els.messageInput.value.trim();
        if (!text || !state.sessionId) return;
        els.messageInput.value = '';
        els.sendBtn.disabled = true;
        post('teach', { session_id: state.sessionId, message: text }).then(function (res) {
            els.sendBtn.disabled = false;
            if (!res.success) {
                // Message still landed (student row is inserted before the AI
                // call), so pull it in via a poll rather than losing it.
                poll();
                return;
            }
            poll(); // one immediate poll picks up both the student message and the AI reply in order
        });
    }
    els.sendBtn.addEventListener('click', sendMessage);
    els.messageInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    });

    // ---- Polling ----

    function startPolling() {
        if (state.pollTimer) return;
        poll();
        state.pollTimer = setInterval(poll, POLL_INTERVAL_MS);
    }

    function poll() {
        if (!state.sessionId) return;
        post('poll', { session_id: state.sessionId, since_id: state.lastMessageId }).then(function (res) {
            if (!res.success) return;

            if (res.status === 'active') {
                showPanel('session');
            }
            renderSessionMeta(res);

            res.messages.forEach(function (msg) {
                appendMessage(msg);
                if (msg.id > state.lastMessageId) state.lastMessageId = msg.id;
            });

            if (res.status === 'completed') {
                els.composer.hidden = true;
                els.composerHint.hidden = false;
                clearInterval(state.pollTimer);
                state.pollTimer = null;
            }
        });
    }

    function renderSessionMeta(res) {
        els.sessionTopic.textContent = res.topic;
        els.currentTeacher.textContent = res.current_teacher_name || '—';
        els.participants.innerHTML = '';
        (res.participants || []).forEach(function (p) {
            var chip = document.createElement('span');
            chip.className = 'gs-participant-chip' + (p.user_id === res.current_teacher_user_id ? ' gs-is-teacher' : '');
            chip.textContent = p.display_name;
            els.participants.appendChild(chip);
        });
    }

    function appendMessage(msg) {
        // Overlapping polls (the 3s interval firing near a send-triggered
        // poll) can both return the same "new" message before either
        // updates lastMessageId — guard against rendering it twice.
        if (els.transcript.querySelector('[data-msg-id="' + msg.id + '"]')) return;

        var isSelf = !msg.is_ai && msg.sender === state.myDisplayName;
        var row = document.createElement('div');
        row.className = 'gs-msg' + (msg.is_ai ? ' gs-msg-ai' : '') + (isSelf ? ' gs-msg-self' : '');
        row.dataset.msgId = msg.id;

        var avatar = document.createElement('div');
        avatar.className = 'gs-msg-avatar';
        if (msg.is_ai) {
            var icon = document.createElement('span');
            icon.className = 'gs-msg-avatar-icon';
            icon.setAttribute('aria-hidden', 'true');
            avatar.appendChild(icon);
        } else {
            avatar.textContent = msg.sender.charAt(0).toUpperCase();
        }

        var body = document.createElement('div');
        body.className = 'gs-msg-body';
        var sender = document.createElement('div');
        sender.className = 'gs-msg-sender';
        sender.textContent = msg.sender;
        var content = document.createElement('div');
        content.className = 'gs-msg-content';

        // The AI's response may contain a fenced tm-chips block (the
        // hand-off) alongside plain prose — split it out and render the
        // prose as text, the widget as a real widget, same as normal chat.
        var fenceMatch = msg.content.match(/```tm-chips\s*\n([\s\S]*?)```/);
        if (fenceMatch) {
            var prose = msg.content.replace(fenceMatch[0], '').trim();
            if (prose) {
                var p = document.createElement('p');
                p.style.margin = '0 0 0.5rem';
                p.textContent = prose;
                content.appendChild(p);
            }
            var pre = document.createElement('pre');
            var code = document.createElement('code');
            code.className = 'language-tm-chips';
            code.textContent = fenceMatch[1].trim();
            pre.appendChild(code);
            content.appendChild(pre);
        } else {
            content.textContent = msg.content;
        }

        body.appendChild(sender);
        body.appendChild(content);
        row.appendChild(avatar);
        row.appendChild(body);
        els.transcript.appendChild(row);
        els.transcript.scrollTop = els.transcript.scrollHeight;

        if (fenceMatch) {
            TMWidgets.render(content);
        }
    }

    // ---- Widget wiring: a chip pick here advances the turn, it does not
    // reply to the AI the way it would in normal chat. ----
    TMWidgets.init({
        onReply: function (text) {
            var m = text.match(/I chose "([^"]+)"\.?$/);
            if (!m) return;
            var nextTeacherName = m[1];
            post('advance_turn', { session_id: state.sessionId, next_teacher_name: nextTeacherName }).then(function () {
                poll();
            });
        },
        onReveal: function () {},
    });
})();
