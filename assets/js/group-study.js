/**
 * Group study — room controller (group_study.php, api/group_study.php).
 *
 * A study chat room: everyone can talk, one person teaches at a time, and Q
 * (the AI facilitator) replies to the teacher, to whoever it called on, and
 * to @Q mentions. The server decides when Q speaks and who it calls on; this
 * file renders the room and keeps it in sync.
 *
 * No WebSockets (see migration 015): polls every 3s while the tab is
 * visible, backs off on errors, and pauses when hidden. Identity is by
 * user id from the poll ("me"), never by display name.
 */
(function () {
    'use strict';

    var API = 'api/group_study.php';
    var POLL_MS = 3000;
    var ROOM_KEY = 'tm-group-room';

    var state = {
        sessionId: null,
        lastId: 0,
        room: null,          // last poll response
        timer: null,
        fails: 0,
        sending: false,
        typingPingAt: 0,
        pickers: [],         // hand-off pickers, re-evaluated every poll
        sendError: ''
    };

    var $ = function (id) { return document.getElementById(id); };
    var els = {
        lobby: $('gsLobby'), room: $('gsRoom'),
        lobbyError: $('gsLobbyError'), rejoin: $('gsRejoin'), rejoinList: $('gsRejoinList'),
        createCard: $('gsCreateCard'), createHead: $('gsCreateHead'), createForm: $('gsCreateForm'),
        topicInput: $('gsTopicInput'), createBtn: $('gsCreateBtn'),
        joinCard: $('gsJoinCard'), joinHead: $('gsJoinHead'), joinForm: $('gsJoinForm'),
        codeInput: $('gsJoinCodeInput'), joinBtn: $('gsJoinBtn'),
        topic: $('gsTopic'), teaching: $('gsTeaching'),
        peopleToggle: $('gsPeopleToggle'), peopleCount: $('gsPeopleCount'), people: $('gsPeople'), peopleList: $('gsPeopleList'),
        sideCode: $('gsSideCode'),
        passBtn: $('gsPassBtn'), passMenu: $('gsPassMenu'),
        endBtn: $('gsEndBtn'), endConfirm: $('gsEndConfirm'), endYes: $('gsEndYes'), endNo: $('gsEndNo'),
        leaveBtn: $('gsLeaveBtn'),
        invite: $('gsInvite'), inviteCode: $('gsInviteCode'), copyCode: $('gsCopyCode'), copyLink: $('gsCopyLink'),
        transcript: $('gsTranscript'), status: $('gsStatus'), asked: $('gsAsked'),
        composer: $('gsComposer'), input: $('gsInput'), sendBtn: $('gsSendBtn'), micBtn: $('gsMicBtn'),
        hint: $('gsHint'), ended: $('gsEnded'), backToLobby: $('gsBackToLobby')
    };

    // ---------------------------------------------------------------- API
    function api(action, data) {
        return fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(Object.assign({ action: action }, data || {}))
        }).then(function (r) {
            return r.json().catch(function () { throw new Error('bad response'); }).then(function (j) {
                j.httpStatus = r.status;
                return j;
            });
        });
    }

    function firstName(name) { return String(name || '').trim().split(/\s+/)[0]; }
    function storage(fn) { try { return fn(); } catch (e) { return null; } }

    // ---------------------------------------------------------------- Lobby
    function lobbyError(msg) {
        els.lobbyError.querySelector('span').textContent = msg || '';
        els.lobbyError.hidden = !msg;
    }

    function openCard(which) {
        [['create', els.createCard, els.createHead, els.topicInput], ['join', els.joinCard, els.joinHead, els.codeInput]].forEach(function (c) {
            var on = c[0] === which;
            c[1].classList.toggle('is-open', on);
            c[2].setAttribute('aria-expanded', String(on));
            if (on) setTimeout(function () { c[3].focus(); }, 150);
        });
    }
    els.createHead.addEventListener('click', function () { openCard('create'); });
    els.joinHead.addEventListener('click', function () { openCard('join'); });

    els.createForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var topic = els.topicInput.value.trim();
        if (!topic) { lobbyError('Give the room a topic first.'); els.topicInput.focus(); return; }
        lobbyError('');
        els.createBtn.disabled = true;
        api('create', { topic: topic }).then(function (res) {
            els.createBtn.disabled = false;
            if (!res.success) { lobbyError(res.error || 'Could not open a room.'); return; }
            enterRoom(res.session_id);
        }).catch(function () {
            els.createBtn.disabled = false;
            lobbyError('Couldn’t reach TutorMind. Check your connection and try again.');
        });
    });

    els.codeInput.addEventListener('input', function () {
        els.codeInput.value = els.codeInput.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    });
    els.joinForm.addEventListener('submit', function (e) {
        e.preventDefault();
        joinByCode(els.codeInput.value.trim());
    });

    function joinByCode(code) {
        if (!/^[A-Z0-9]{6}$/.test(code)) { lobbyError('Join codes are 6 letters and numbers.'); return; }
        lobbyError('');
        els.joinBtn.disabled = true;
        api('join', { join_code: code }).then(function (res) {
            els.joinBtn.disabled = false;
            if (!res.success) { lobbyError(res.error || 'Could not join that room.'); return; }
            enterRoom(res.session_id);
        }).catch(function () {
            els.joinBtn.disabled = false;
            lobbyError('Couldn’t reach TutorMind. Check your connection and try again.');
        });
    }

    function loadRejoin() {
        api('mine').then(function (res) {
            var rooms = (res.success && res.rooms) || [];
            els.rejoinList.innerHTML = '';
            rooms.forEach(function (r) {
                var li = document.createElement('li');
                li.className = 'gs-rejoin__item';
                var text = document.createElement('div');
                var t = document.createElement('span'); t.className = 'gs-rejoin__topic'; t.textContent = r.topic;
                var meta = document.createElement('span'); meta.className = 'gs-rejoin__meta';
                meta.textContent = (r.is_host ? 'Your room' : 'Joined') + ' · ' + r.join_code + (r.status === 'waiting' ? ' · waiting for people' : '');
                text.appendChild(t); text.appendChild(meta);
                var btn = document.createElement('button');
                btn.type = 'button'; btn.className = 'ds-btn ds-btn--tertiary ds-btn--sm'; btn.textContent = 'Rejoin';
                btn.addEventListener('click', function () {
                    api('open', { session_id: r.session_id }).then(function (res2) {
                        if (res2.success) enterRoom(r.session_id); else { lobbyError(res2.error); loadRejoin(); }
                    });
                });
                li.appendChild(text); li.appendChild(btn);
                els.rejoinList.appendChild(li);
            });
            els.rejoin.hidden = rooms.length === 0;
        }).catch(function () {});
    }

    // Keep "Pick up where you left off" current while the lobby is on screen
    // (a room can end or fill up while you're looking at it)
    setInterval(function () {
        if (!els.lobby.hidden && !document.hidden) loadRejoin();
    }, 20000);

    function showLobby(message) {
        stopPolling();
        stopVoice();
        state.sessionId = null;
        storage(function () { sessionStorage.removeItem(ROOM_KEY); });
        els.room.hidden = true;
        els.lobby.hidden = false;
        document.title = 'Group study — TutorMind';
        els.topicInput.value = '';
        els.codeInput.value = '';
        lobbyError(message || '');
        loadRejoin();
    }

    // ---------------------------------------------------------------- Room
    function enterRoom(sessionId) {
        state.sessionId = sessionId;
        state.lastId = 0;
        state.room = null;
        state.pickers = [];
        state.sendError = '';
        storage(function () { sessionStorage.setItem(ROOM_KEY, String(sessionId)); });
        els.transcript.innerHTML = '';
        els.input.value = '';
        els.lobby.hidden = true;
        els.room.hidden = false;
        closeMenus();
        startPolling();
    }

    function startPolling() {
        stopPolling();
        state.fails = 0;
        poll();
    }
    function stopPolling() {
        clearTimeout(state.timer);
        state.timer = null;
    }
    function schedule() {
        clearTimeout(state.timer);
        if (!state.sessionId || document.hidden) return;
        if (state.room && state.room.status === 'completed') return;
        var delay = state.fails ? Math.min(30000, POLL_MS * Math.pow(2, state.fails)) : POLL_MS;
        state.timer = setTimeout(poll, delay);
    }

    function poll() {
        var sid = state.sessionId;
        if (!sid) return;
        api('poll', { session_id: sid, since_id: state.lastId }).then(function (res) {
            if (sid !== state.sessionId) return;
            if (!res.success) {
                if ([403, 404, 410].indexOf(res.httpStatus) !== -1) { showLobby(res.error); return; }
                throw new Error(res.error);
            }
            state.fails = 0;
            render(res);
        }).catch(function () {
            state.fails++;
            renderStatus();
        }).then(schedule);
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden && state.sessionId) { stopPolling(); poll(); }
    });

    function render(res) {
        // Decide "stick to the bottom" before anything changes the layout
        var t = els.transcript;
        var wasAtBottom = !t.childElementCount || t.scrollHeight - t.scrollTop - t.clientHeight < 80;
        var wasAskedMe = !els.asked.hidden;
        state.room = res;
        var me = res.me;
        var people = res.participants || [];
        var byId = {};
        people.forEach(function (p) { byId[p.user_id] = p; });
        var teacher = byId[res.teacher_user_id];
        var active = res.status === 'active';
        var ended = res.status === 'completed';

        document.title = res.topic + ' — Group study';
        els.topic.textContent = res.topic;
        els.teaching.textContent = ended ? 'Session ended'
            : !active ? 'Waiting for someone to join'
            : teacher ? (teacher.user_id === me ? 'You’re teaching' : firstName(teacher.display_name) + ' is teaching') : 'Nobody is teaching';
        els.teaching.classList.toggle('is-you', !!teacher && teacher.user_id === me && active);

        // Invite panel while waiting
        els.invite.hidden = res.status !== 'waiting';
        els.inviteCode.textContent = res.join_code;
        els.sideCode.textContent = res.join_code;

        // People
        var here = people.filter(function (p) { return !p.left; });
        els.peopleCount.textContent = here.length;
        els.peopleList.innerHTML = '';
        people.slice().sort(function (a, b) {
            return (a.left - b.left) || (b.online - a.online);
        }).forEach(function (p) {
            var li = document.createElement('li');
            li.className = 'gs-person' + (p.left ? ' is-left' : p.online ? ' is-here' : ' is-away');
            li.appendChild(avatar(p.display_name));
            var name = document.createElement('span');
            name.className = 'gs-person__name';
            name.textContent = p.display_name + (p.user_id === me ? ' (you)' : '');
            li.appendChild(name);
            var tag = document.createElement('span');
            tag.className = 'gs-person__tag';
            tag.textContent = p.left ? 'left' : p.user_id === res.teacher_user_id && active ? 'teaching' : p.online ? '' : 'away';
            if (tag.textContent) li.appendChild(tag);
            els.peopleList.appendChild(li);
        });

        // Controls
        var canPass = active && (me === res.teacher_user_id || me === res.host_user_id) && here.length > 1;
        els.passBtn.hidden = !canPass;
        if (!canPass) closeMenus();
        els.endBtn.hidden = ended || me !== res.host_user_id;
        if (els.endBtn.hidden) els.endConfirm.hidden = true;

        // Messages
        (res.messages || []).forEach(function (m) {
            appendMessage(m, res);
            if (m.id > state.lastId) state.lastId = m.id;
        });
        state.pickers.forEach(function (p) { p.update(res); });
        markAddressed(res);

        // Composer / ended
        var askedMe = active && res.open_ask && res.open_ask.user_id === me;
        els.asked.hidden = !askedMe;
        els.input.placeholder = askedMe ? 'Answer Q in your own words…' : active ? 'Message the room…' : 'The room opens when someone joins…';
        els.composer.hidden = ended;
        els.hint.hidden = ended || !active;
        els.ended.hidden = !ended;
        setComposerEnabled(active && !state.sending);
        if (ended) { stopVoice(); storage(function () { sessionStorage.removeItem(ROOM_KEY); }); }
        renderStatus();

        // The callout/ended panel can shrink the transcript after new messages
        // landed, so scroll last. When Q has just asked me, show its whole question.
        var asking = askedMe && !wasAskedMe && t.querySelector('.gs-msg.is-asking-me');
        if (asking) asking.scrollIntoView({ block: 'nearest' });
        else if (wasAtBottom) t.scrollTop = t.scrollHeight;
    }

    function renderStatus() {
        var res = state.room;
        var text = '', tone = '';
        if (state.fails >= 2) { text = 'Reconnecting…'; tone = 'warn'; }
        else if (state.sendError) { text = state.sendError; tone = 'bad'; }
        else if (res && res.status === 'active') {
            var parts = [];
            if (res.q_thinking) parts.push('Q is thinking…');
            var t = res.typing || [];
            if (t.length === 1) parts.push(firstName(t[0]) + ' is typing…');
            else if (t.length === 2) parts.push(firstName(t[0]) + ' and ' + firstName(t[1]) + ' are typing…');
            else if (t.length > 2) parts.push(t.length + ' people are typing…');
            text = parts.join(' · ');
            if (text) tone = 'live';
        }
        els.status.textContent = text;
        els.status.dataset.tone = tone;
    }

    // ---------------------------------------------------------------- Messages
    function avatar(name, isQ) {
        var a = document.createElement('span');
        a.className = 'gs-avatar' + (isQ ? ' gs-avatar--q' : '');
        a.setAttribute('aria-hidden', 'true');
        if (!isQ) a.textContent = firstName(name).charAt(0).toUpperCase();
        return a;
    }

    // Plain text with **bold**, *italic* and line breaks — built as nodes, never innerHTML
    function richText(container, text) {
        String(text).split(/\n{2,}/).forEach(function (para) {
            var p = document.createElement('p');
            para.split(/(\*\*[^*]+\*\*|\*[^*\s][^*]*\*)/).forEach(function (bit) {
                if (!bit) return;
                if (/^\*\*[^*]+\*\*$/.test(bit)) {
                    var b = document.createElement('strong'); b.textContent = bit.slice(2, -2); p.appendChild(b);
                } else if (/^\*[^*\s][^*]*\*$/.test(bit)) {
                    var em = document.createElement('em'); em.textContent = bit.slice(1, -1); p.appendChild(em);
                } else {
                    bit.split('\n').forEach(function (line, j) {
                        if (j) p.appendChild(document.createElement('br'));
                        p.appendChild(document.createTextNode(line));
                    });
                }
            });
            container.appendChild(p);
        });
    }

    function appendMessage(m, res) {
        if (els.transcript.querySelector('[data-id="' + m.id + '"]')) return;

        var row = document.createElement('div');
        row.dataset.id = m.id;

        if (m.type === 'system') {
            row.className = 'gs-msg gs-msg--system';
            row.textContent = m.content;
        } else {
            var isQ = m.type === 'ai';
            var mine = !isQ && m.user_id === res.me;
            row.className = 'gs-msg' + (isQ ? ' gs-msg--q' : mine ? ' gs-msg--me' : '');
            if (!mine) row.appendChild(avatar(m.sender, isQ));

            var body = document.createElement('div');
            body.className = 'gs-msg__body';
            var head = document.createElement('div');
            head.className = 'gs-msg__head';
            if (!mine) {
                var who = document.createElement('span');
                who.className = 'gs-msg__name';
                who.textContent = isQ ? 'Q' : m.sender;
                head.appendChild(who);
            }
            if (isQ && m.addressed_user_id) {
                var tag = document.createElement('span');
                tag.className = 'gs-msg__asked';
                tag.dataset.userId = m.addressed_user_id;
                head.appendChild(tag);
            }
            if (isQ) head.appendChild(speakButton(m.content));
            if (head.childNodes.length) body.appendChild(head);

            var bubble = document.createElement('div');
            bubble.className = 'gs-msg__bubble';
            var fence = isQ ? m.content.match(/```tm-chips\s*\n([\s\S]*?)```/) : null;
            richText(bubble, fence ? m.content.replace(fence[0], '').trim() : m.content);
            body.appendChild(bubble);
            if (fence) {
                var picker = handoffPicker(fence[1], m);
                if (picker) body.appendChild(picker);
            }
            row.appendChild(body);
        }

        els.transcript.appendChild(row);
        if (m.type === 'student' && m.user_id === res.me) els.transcript.scrollTop = els.transcript.scrollHeight;
    }

    // "asked you" / "asked Ben" tags, and a highlight while the ask is open
    function markAddressed(res) {
        var byId = {};
        (res.participants || []).forEach(function (p) { byId[p.user_id] = p; });
        els.transcript.querySelectorAll('.gs-msg__asked').forEach(function (tag) {
            var uid = Number(tag.dataset.userId);
            tag.textContent = uid === res.me ? 'asked you' : 'asked ' + firstName((byId[uid] || {}).display_name || 'someone');
            var row = tag.closest('.gs-msg');
            var open = res.open_ask && res.open_ask.message_id === Number(row.dataset.id);
            row.classList.toggle('is-asking-me', !!open && uid === res.me);
        });
    }

    // The facilitator's "who's teaching next" block → buttons only the current
    // teacher (or host) can use, and only while that turn is still open.
    function handoffPicker(json, msg) {
        var spec;
        try { spec = JSON.parse(json); } catch (e) { return null; }
        if (!spec || !Array.isArray(spec.options)) return null;

        var box = document.createElement('div');
        box.className = 'gs-handoff';
        var q = document.createElement('p');
        q.className = 'gs-handoff__q';
        q.textContent = spec.q || 'Who’s teaching next?';
        var row = document.createElement('div');
        row.className = 'gs-handoff__options';
        var note = document.createElement('p');
        note.className = 'gs-handoff__note';
        box.appendChild(q); box.appendChild(row); box.appendChild(note);

        var buttons = spec.options.map(function (name) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'ds-chip';
            b.textContent = firstName(name);
            b.dataset.name = name;
            b.addEventListener('click', function () {
                var p = (state.room.participants || []).filter(function (x) { return x.display_name === name && !x.left; })[0];
                if (!p) return;
                buttons.forEach(function (x) { x.disabled = true; });
                b.setAttribute('aria-pressed', 'true');
                passTurn(p.user_id);
            });
            row.appendChild(b);
            return b;
        });

        state.pickers.push({
            update: function (res) {
                var current = res.status === 'active' && msg.turn_id !== null && msg.turn_id === res.turn_id;
                var mayPick = current && (res.me === res.teacher_user_id || res.me === res.host_user_id);
                var teacher = (res.participants || []).filter(function (p) { return p.user_id === res.teacher_user_id; })[0];
                buttons.forEach(function (b) {
                    var p = (res.participants || []).filter(function (x) { return x.display_name === b.dataset.name; })[0];
                    b.disabled = !mayPick || !p || p.left;
                });
                box.classList.toggle('is-done', !current);
                note.textContent = !current ? 'Turn passed.'
                    : mayPick ? 'Pick who explains next.'
                    : (teacher ? firstName(teacher.display_name) : 'The teacher') + ' is picking who teaches next.';
            }
        });
        return box;
    }

    // ---------------------------------------------------------------- Sending
    function setComposerEnabled(on) {
        els.input.disabled = !on && !state.sending;
        els.sendBtn.disabled = !on;
    }

    els.composer.addEventListener('submit', function (e) {
        e.preventDefault();
        send();
    });
    els.input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); send(); }
    });
    els.input.addEventListener('input', function () {
        autosize();
        if (state.sendError) { state.sendError = ''; renderStatus(); }
        var now = Date.now();
        if (state.sessionId && els.input.value.trim() && now - state.typingPingAt > 2500) {
            state.typingPingAt = now;
            api('typing', { session_id: state.sessionId }).catch(function () {});
        }
    });
    function autosize() {
        els.input.style.height = 'auto';
        els.input.style.height = Math.min(els.input.scrollHeight, 160) + 'px';
    }

    function send() {
        var text = els.input.value.trim();
        var sid = state.sessionId;
        if (!text || !sid || state.sending) return;
        if (listening) stopVoice();
        state.sending = true;
        state.sendError = '';
        setComposerEnabled(false);
        api('send', { session_id: sid, message: text }).then(function (res) {
            if (!res.success) throw new Error(res.error || 'Your message didn’t send.');
            // Only now is it safe to clear: the server has it
            if (els.input.value.trim() === text) els.input.value = '';
            autosize();
            state.typingPingAt = 0;
            if (res.q_replies) askQ(sid, res.message_id);
        }).catch(function (err) {
            state.sendError = err && err.message && err.message !== 'Failed to fetch' && err.message !== 'bad response'
                ? err.message : 'Couldn’t send. Check your connection — your message is still in the box.';
        }).then(function () {
            state.sending = false;
            setComposerEnabled(state.room && state.room.status === 'active');
            els.input.focus();
            stopPolling(); poll();
        });
    }

    // The slow half: Q's reply. The room shows "Q is thinking" from the server.
    function askQ(sid, messageId) {
        setTimeout(function () { if (state.sessionId === sid) { stopPolling(); poll(); } }, 400);
        api('reply', { session_id: sid, message_id: messageId }).then(function (res) {
            if (!res.success && state.sessionId === sid) { state.sendError = res.error || 'Q couldn’t reply just now.'; renderStatus(); }
        }).catch(function () {}).then(function () {
            if (state.sessionId === sid) { stopPolling(); poll(); }
        });
    }

    // ---------------------------------------------------------------- Turn, end, leave
    function passTurn(userId) {
        closeMenus();
        api('pass_turn', { session_id: state.sessionId, next_user_id: userId }).then(function (res) {
            if (!res.success) { state.sendError = res.error; renderStatus(); }
        }).catch(function () {}).then(function () { stopPolling(); poll(); });
    }

    function closeMenus() {
        els.passMenu.hidden = true;
        els.passBtn.setAttribute('aria-expanded', 'false');
    }
    els.passBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        var open = els.passMenu.hidden;
        closeMenus();
        if (!open || !state.room) return;
        var res = state.room;
        els.passMenu.innerHTML = '';
        (res.participants || []).filter(function (p) { return !p.left && p.user_id !== res.teacher_user_id; })
            .sort(function (a, b) { return (a.has_taught - b.has_taught) || (b.online - a.online); })
            .forEach(function (p) {
                var item = document.createElement('button');
                item.type = 'button';
                item.className = 'gs-menu__item';
                item.setAttribute('role', 'menuitem');
                item.textContent = p.display_name + (p.user_id === res.me ? ' (you)' : '');
                var note = document.createElement('span');
                note.textContent = p.has_taught ? 'has taught' : p.online ? 'hasn’t taught yet' : 'away';
                item.appendChild(note);
                item.addEventListener('click', function () { passTurn(p.user_id); });
                els.passMenu.appendChild(item);
            });
        els.passMenu.hidden = false;
        els.passBtn.setAttribute('aria-expanded', 'true');
        var first = els.passMenu.querySelector('button');
        if (first) first.focus();
    });
    function closePeople() {
        els.room.classList.remove('is-people-open');
        els.peopleToggle.setAttribute('aria-expanded', 'false');
    }
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.gs-menu-wrap')) closeMenus();
        // The phone's people panel closes on a tap outside it
        if (els.room.classList.contains('is-people-open') && !e.target.closest('.gs-people, #gsPeopleToggle')) closePeople();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeMenus(); closePeople(); els.endConfirm.hidden = true; }
    });

    els.endBtn.addEventListener('click', function () { els.endConfirm.hidden = false; els.endYes.focus(); });
    els.endNo.addEventListener('click', function () { els.endConfirm.hidden = true; });
    els.endYes.addEventListener('click', function () {
        els.endConfirm.hidden = true;
        api('end', { session_id: state.sessionId }).catch(function () {}).then(function () { stopPolling(); poll(); });
    });

    els.leaveBtn.addEventListener('click', function () {
        var sid = state.sessionId;
        showLobby();
        if (sid) api('leave', { session_id: sid }).catch(function () {}).then(loadRejoin);
    });
    els.backToLobby.addEventListener('click', function () { showLobby(); });

    els.peopleToggle.addEventListener('click', function () {
        var open = !els.room.classList.contains('is-people-open');
        els.room.classList.toggle('is-people-open', open);
        els.peopleToggle.setAttribute('aria-expanded', String(open));
    });

    // ---------------------------------------------------------------- Invite
    function copy(text, btn, done) {
        var label = btn.querySelector('span') || btn;
        var original = label.textContent;
        var finish = function () { label.textContent = done; setTimeout(function () { label.textContent = original; }, 1500); };
        if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(text).then(finish).catch(function () {});
    }
    function inviteLink() {
        return location.origin + location.pathname.replace(/\.php$/, '') + '?code=' + (state.room ? state.room.join_code : '');
    }
    els.copyCode.addEventListener('click', function () { copy(state.room.join_code, els.copyCode, 'Copied'); });
    els.copyLink.addEventListener('click', function () { copy(inviteLink(), els.copyLink, 'Link copied'); });
    els.sideCode.addEventListener('click', function () { copy(state.room.join_code, els.sideCode, 'Copied'); });

    // ---------------------------------------------------------------- Voice (read aloud + voice typing)
    var audio = null, speakingBtn = null;
    function speakButton(content) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'gs-msg__speak';
        b.setAttribute('aria-label', 'Read aloud');
        b.innerHTML = '<svg class="ds-i"><use href="#i-volume"/></svg>';
        var text = content.replace(/```[\s\S]*?```/g, '').replace(/\*/g, '').trim();
        b.addEventListener('click', function () { speak(text, b); });
        return b;
    }
    function setSpeaking(btn, on) {
        if (!btn) return;
        btn.setAttribute('aria-label', on ? 'Stop reading' : 'Read aloud');
        btn.querySelector('use').setAttribute('href', on ? '#i-stop' : '#i-volume');
    }
    function stopSpeaking() {
        if (audio) { audio.pause(); audio = null; }
        if (window.speechSynthesis) window.speechSynthesis.cancel();
        setSpeaking(speakingBtn, false);
        speakingBtn = null;
    }
    function speak(text, btn) {
        if (speakingBtn === btn) { stopSpeaking(); return; }
        stopSpeaking();
        speakingBtn = btn;
        setSpeaking(btn, true);
        fetch('api/tts.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ text: text }) })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (speakingBtn !== btn) return;
                if (!d.success || !d.audio) throw new Error('no audio');
                var bytes = Uint8Array.from(atob(d.audio), function (c) { return c.charCodeAt(0); });
                audio = new Audio(URL.createObjectURL(new Blob([bytes], { type: d.contentType || 'audio/mpeg' })));
                audio.onended = stopSpeaking;
                return audio.play();
            })
            .catch(function () {
                if (speakingBtn !== btn || !window.speechSynthesis) { if (speakingBtn === btn) stopSpeaking(); return; }
                var u = new SpeechSynthesisUtterance(text);
                u.onend = u.onerror = stopSpeaking;
                window.speechSynthesis.speak(u);
            });
    }

    var Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    var recognition = null, listening = false, baseText = '';
    if (Recognition) {
        recognition = new Recognition();
        recognition.continuous = true;
        recognition.interimResults = true;
        recognition.lang = document.documentElement.lang === 'en' ? 'en-US' : (navigator.language || 'en-US');
        recognition.onstart = function () {
            listening = true;
            baseText = els.input.value ? els.input.value.trim() + ' ' : '';
            els.micBtn.setAttribute('aria-pressed', 'true');
        };
        recognition.onresult = function (e) {
            var said = '';
            for (var i = 0; i < e.results.length; i++) said += e.results[i][0].transcript;
            els.input.value = baseText + said;
            autosize();
        };
        recognition.onend = recognition.onerror = function () {
            listening = false;
            els.micBtn.setAttribute('aria-pressed', 'false');
        };
    } else {
        els.micBtn.hidden = true;
    }
    function stopVoice() {
        stopSpeaking();
        if (listening && recognition) { try { recognition.stop(); } catch (e) {} }
    }
    els.micBtn.addEventListener('click', function () {
        if (!recognition) return;
        if (listening) recognition.stop();
        else { try { recognition.start(); } catch (e) {} }
    });

    // ---------------------------------------------------------------- Boot
    var params = new URLSearchParams(location.search);
    var inviteCode = (params.get('code') || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
    var savedRoom = Number(storage(function () { return sessionStorage.getItem(ROOM_KEY); })) || 0;
    if (inviteCode) {
        history.replaceState(null, '', location.pathname);
        openCard('join');
        els.codeInput.value = inviteCode;
        joinByCode(inviteCode);
    } else if (savedRoom) {
        enterRoom(savedRoom); // a refresh drops you back in the same room
    } else {
        loadRejoin();
    }
})();
