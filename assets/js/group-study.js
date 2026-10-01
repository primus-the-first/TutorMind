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
        sendError: '',
        picks: {},           // round id -> option picked but not locked in yet
        focusEndsAt: 0,      // local clock time the shared focus timer runs out
        focusTick: null,
        timerAskAt: 0        // last time this client asked the server to fire the timer
    };

    var $ = function (id) { return document.getElementById(id); };
    var els = {
        lobby: $('gsLobby'), room: $('gsRoom'),
        lobbyError: $('gsLobbyError'), rejoin: $('gsRejoin'), rejoinList: $('gsRejoinList'),
        history: $('gsHistory'), historyList: $('gsHistoryList'),
        createForm: $('gsCreateForm'), topicInput: $('gsTopicInput'), createBtn: $('gsCreateBtn'),
        joinForm: $('gsJoinForm'),
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
        hint: $('gsHint'), ended: $('gsEnded'), backToLobby: $('gsBackToLobby'),
        bridge: $('gsBridge'), bridgeFill: $('gsBridgeFill'), bridgeLabel: $('gsBridgeLabel'),
        focus: $('gsFocus'), focusTime: $('gsFocusTime'), focusStop: $('gsFocusStop'),
        focusBtn: $('gsFocusBtn'), focusMenu: $('gsFocusMenu'), roundBtn: $('gsRoundBtn')
    };

    // ---------------------------------------------------------------- Huddle rounds
    // What the room calls a round. The server's GS_ROUND_NAME (Q's lines) matches.
    var ROUND_NAME = 'Huddle round';
    // Reactions are feedback on a message, never a score for the person
    var REACTIONS = [
        { kind: 'clicked', label: 'Clicked', icon: '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6.3 6.3l2.8 2.8M14.9 14.9l2.8 2.8M6.3 17.7l2.8-2.8M14.9 9.1l2.8-2.8"/>' },
        { kind: 'wait', label: 'Wait what', icon: '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .8-1 1.5v.7M12 17h.01"/>' },
        { kind: 'same', label: 'Same', icon: '<path d="M4 16a8 8 0 0 1 16 0"/><path d="M8 16a4 4 0 0 1 8 0"/>' },
        { kind: 'cheer', label: 'You got this', icon: '<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/>' }
    ];
    var LETTERS = ['A', 'B', 'C', 'D'];
    function icon(paths, cls) {
        return '<svg class="' + (cls || 'gs-ico') + '" viewBox="0 0 24 24" aria-hidden="true">' + paths + '</svg>';
    }
    var LOCK_ICON = '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>';
    var CHECK_ICON = '<path d="M4 13l5 5L20 6"/>';

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

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text != null) n.textContent = text;
        return n;
    }

    // "Here now" only counts other people: you're on the lobby, not in the room
    function roomStatus(r) {
        var others = r.members.filter(function (m) { return !m.me; });
        var here = others.filter(function (m) { return m.online; }).length;
        if (here) return { live: true, text: here === 1 ? '1 here now' : here + ' here now' };
        if (r.status === 'waiting' && others.length === 0) return { live: false, text: 'Waiting for people' };
        var min = Math.round(r.idle_seconds / 60);
        if (min < 1) return { live: false, text: 'Active just now' };
        if (min < 60) return { live: false, text: 'Quiet for ' + min + ' min' };
        var h = Math.round(min / 60);
        return { live: false, text: 'Quiet for ' + h + (h === 1 ? ' hour' : ' hours') };
    }

    function roomCard(r) {
        var li = el('li', 'gs-roomcard');
        var st = roomStatus(r);

        var top = el('div', 'gs-roomcard__top');
        top.appendChild(el('span', 'gs-roomcard__status' + (st.live ? ' is-live' : ''), st.text));
        var n = r.members.length;
        top.appendChild(el('span', 'gs-roomcard__count', n === 1 ? '1 person' : n + ' people'));
        li.appendChild(top);

        li.appendChild(el('h3', 'gs-roomcard__topic', r.topic));
        li.appendChild(el('p', 'gs-roomcard__meta', (r.is_host ? 'Your room' : 'Joined') + ' · code ' + r.join_code));

        var foot = el('div', 'gs-roomcard__foot');
        var stack = el('span', 'gs-stack');
        stack.setAttribute('aria-label', r.members.map(function (m) { return m.me ? 'you' : m.name; }).join(', '));
        r.members.slice(0, 4).forEach(function (m, i) {
            var a = el('span', 'gs-avatar gs-stack__item' + (m.online && !m.me ? ' is-here' : ''), (m.name || '?').charAt(0).toUpperCase());
            a.setAttribute('aria-hidden', 'true');
            a.style.zIndex = String(5 - i); // earlier on top, so each presence dot isn't covered by the next disc
            stack.appendChild(a);
        });
        if (n > 4) {
            var more = el('span', 'gs-avatar gs-stack__item gs-stack__more', '+' + (n - 4));
            more.setAttribute('aria-hidden', 'true');
            stack.appendChild(more);
        }
        foot.appendChild(stack);

        var btn = el('button', 'ds-btn ds-btn--tertiary ds-btn--sm', r.i_left ? 'Rejoin' : 'Continue');
        btn.type = 'button';
        btn.setAttribute('aria-label', (r.i_left ? 'Rejoin ' : 'Continue ') + r.topic);
        btn.insertAdjacentHTML('beforeend', ' <svg class="ds-i ds-i-arrow" aria-hidden="true"><use href="#i-arrow"/></svg>');
        btn.addEventListener('click', function () {
            btn.disabled = true;
            api('open', { session_id: r.session_id }).then(function (res2) {
                if (res2.success) enterRoom(r.session_id);
                else { btn.disabled = false; lobbyError(res2.error); loadRejoin(); }
            }).catch(function () {
                btn.disabled = false;
                lobbyError('Couldn’t reach TutorMind. Check your connection and try again.');
            });
        });
        foot.appendChild(btn);
        li.appendChild(foot);
        return li;
    }

    // Server sends "seconds ago" (PHP/MySQL clocks disagree on timezone); the browser dates it
    function endedWhen(secondsAgo) {
        var then = new Date(Date.now() - secondsAgo * 1000);
        var today = new Date(); today.setHours(0, 0, 0, 0);
        var days = Math.round((today - new Date(then.getFullYear(), then.getMonth(), then.getDate())) / 864e5);
        if (days <= 0) return 'Today';
        if (days === 1) return 'Yesterday';
        if (days < 7) return days + ' days ago';
        return then.toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
    }

    function loadHistory() {
        api('history').then(function (res) {
            var rooms = (res.success && res.rooms) || [];
            els.historyList.innerHTML = '';
            rooms.forEach(function (r) {
                var li = el('li', 'gs-history__item');
                var text = el('div', 'gs-history__text');
                text.appendChild(el('span', 'gs-history__topic', r.topic));
                text.appendChild(el('span', 'gs-history__meta', endedWhen(r.ended_seconds_ago) + ' · '
                    + (r.people === 1 ? '1 person' : r.people + ' people') + ' · '
                    + (r.said === 1 ? '1 message' : r.said + ' messages')));
                li.appendChild(text);
                var btn = el('button', 'ds-btn ds-btn--tertiary ds-btn--sm', 'Read back');
                btn.type = 'button';
                btn.setAttribute('aria-label', 'Read back ' + r.topic);
                btn.addEventListener('click', function () { enterRoom(r.session_id); });
                li.appendChild(btn);
                els.historyList.appendChild(li);
            });
            els.history.hidden = rooms.length === 0;
        }).catch(function () {});
    }

    function loadRejoin() {
        loadHistory();
        api('mine').then(function (res) {
            var rooms = (res.success && res.rooms) || [];
            els.rejoinList.innerHTML = '';
            rooms.forEach(function (r) { els.rejoinList.appendChild(roomCard(r)); });
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
        stopFocusTick();
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
        state.more = false;
        state.room = null;
        state.pickers = [];
        state.sendError = '';
        state.picks = {};
        state.focusEndsAt = 0;
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
        // An ended room stops polling once its whole transcript is in (poll pages 200 at a time)
        if (state.room && state.room.status === 'completed' && !state.more) return;
        var delay = state.more ? 0 : state.fails ? Math.min(30000, POLL_MS * Math.pow(2, state.fails)) : POLL_MS;
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
            state.more = (res.messages || []).length >= 200;
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
            li.className = 'gs-person' + (p.left ? ' is-left' : ended ? ' is-past' : p.online ? ' is-here' : ' is-away');
            li.appendChild(avatar(p.display_name));
            var name = document.createElement('span');
            name.className = 'gs-person__name';
            name.textContent = p.display_name + (p.user_id === me ? ' (you)' : '');
            li.appendChild(name);
            var tag = document.createElement('span');
            tag.className = 'gs-person__tag';
            tag.textContent = p.left ? 'left' : ended ? '' : p.user_id === res.teacher_user_id && active ? 'teaching' : p.online ? '' : 'away';
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
        renderRounds(res, byId);
        renderReactions(res);
        renderBridge(res);
        renderFocus(res);

        // Host controls: one round at a time; the focus timer when none is running
        var openRound = (res.rounds || []).some(function (r) { return r.status === 'open'; });
        var isHost = me === res.host_user_id;
        els.roundBtn.hidden = !active || !isHost || openRound;
        els.focusBtn.hidden = !active || !isHost || !!res.focus;
        if (els.focusBtn.hidden) closeFocusMenu();

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

    var HANDOFF_RE = /(?:```[ \t]*(?:tm-chips|json)?[ \t]*\r?\n|(?:^|\n)[ \t]*tm-chips[ \t]*\r?\n)?[ \t]*(\{[^{}]*"options"[^{}]*\})[ \t]*\r?\n?(?:```)?\s*$/;

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
            if (isQ && m.round_id) {
                // A huddle round: Q's intro line, then the round's live card. (The
                // question and options also sit in the text, for Q's own context.)
                row.classList.add('has-round');
                richText(bubble, m.content.split(/\n\nQuestion:/)[0]);
                body.appendChild(bubble);
                var card = document.createElement('section');
                card.className = 'gs-round';
                card.dataset.roundId = m.round_id;
                card.setAttribute('aria-label', ROUND_NAME);
                body.appendChild(card);
            } else {
                // Tolerant on purpose: rows stored before the server normalised the
                // block can still have it unfenced or labelled ```json.
                var fence = isQ ? m.content.match(HANDOFF_RE) : null;
                richText(bubble, fence ? m.content.replace(fence[0], '').trim() : m.content);
                body.appendChild(bubble);
                if (fence) {
                    var picker = handoffPicker(fence[1], m);
                    if (picker) body.appendChild(picker);
                }
            }
            var reacts = document.createElement('div');
            reacts.className = 'gs-reacts';
            reacts.dataset.mine = mine ? '1' : '';
            body.appendChild(reacts);
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

    // ---------------------------------------------------------------- Rounds
    // Each card redraws only when something about it changed, so a pick or the
    // keyboard focus isn't wiped by the 3-second poll.
    function renderRounds(res, byId) {
        var rounds = {};
        (res.rounds || []).forEach(function (r) { rounds[r.id] = r; });
        var here = (res.participants || []).filter(function (p) { return !p.left && p.online; }).length;
        var recalling = false;
        els.transcript.querySelectorAll('.gs-round').forEach(function (card) {
            var r = rounds[Number(card.dataset.roundId)];
            if (!r) return;
            if (r.status === 'open' && r.source === 'timer' && r.my_choice === null) recalling = true;
            var sig = JSON.stringify([r.status, r.locked, r.my_choice, state.picks[r.id], here,
                res.me === res.host_user_id, res.status, Object.keys(byId).length]);
            if (card.dataset.sig === sig) return;
            card.dataset.sig = sig;
            var focusKey = card.contains(document.activeElement) ? document.activeElement.dataset.key : null;
            drawRound(card, r, res, byId, here);
            if (focusKey) { var again = card.querySelector('[data-key="' + focusKey + '"]'); if (again && !again.disabled) again.focus(); }
        });
        // Recall rounds are from memory: the conversation stays hidden until you've locked in
        els.transcript.classList.toggle('is-recalling', recalling && res.status === 'active');
    }

    function drawRound(card, r, res, byId, here) {
        var me = res.me;
        var open = r.status === 'open';
        var mine = r.my_choice;
        var pick = state.picks[r.id];
        var canAnswer = open && mine === null && res.status === 'active';
        card.innerHTML = '';
        card.classList.toggle('is-revealed', !open);

        var head = el('div', 'gs-round__head');
        var kind = el('span', 'gs-round__kind');
        kind.innerHTML = '<svg viewBox="0 0 40 30" width="18" height="14" aria-hidden="true"><path d="M5 27 C 5 16, 12 8, 20 8 C 28 8, 35 16, 35 27" stroke="currentColor" stroke-width="5" stroke-linecap="round" fill="none"/><circle cx="20" cy="3.5" r="3.5" fill="currentColor"/></svg>';
        kind.appendChild(document.createTextNode(r.source === 'timer' ? 'Recall round' : ROUND_NAME));
        head.appendChild(kind);
        var count = el('span', 'gs-round__count');
        if (open) {
            var total = Math.max(here, r.locked.length);
            count.appendChild(document.createTextNode(r.locked.length + ' of ' + total + ' locked in'));
            var dots = el('span', 'gs-round__dots');
            dots.setAttribute('aria-hidden', 'true');
            for (var i = 0; i < total; i++) dots.appendChild(el('span', i < r.locked.length ? 'is-on' : ''));
            count.appendChild(dots);
        } else {
            count.textContent = 'Revealed';
        }
        head.appendChild(count);
        card.appendChild(head);
        card.appendChild(el('p', 'gs-round__q', r.question));

        if (open) {
            if (r.source === 'timer' && canAnswer) card.appendChild(el('p', 'gs-round__note', 'From memory: the chat is hidden until you lock in.'));
            var opts = el('div', 'gs-round__opts');
            opts.setAttribute('role', 'group');
            opts.setAttribute('aria-label', 'Answers');
            r.options.forEach(function (text, i) {
                var chosen = mine !== null ? mine === i : pick === i;
                var b = el('button', 'gs-round__opt' + (chosen ? ' is-chosen' : '') + (mine !== null && !chosen ? ' is-dim' : ''));
                b.type = 'button';
                b.dataset.key = 'opt' + i;
                b.setAttribute('aria-pressed', String(chosen));
                b.disabled = !canAnswer;
                b.appendChild(el('span', 'gs-round__key', LETTERS[i]));
                b.appendChild(el('span', 'gs-round__text', text));
                if (mine !== null && chosen) b.insertAdjacentHTML('beforeend', icon(LOCK_ICON));
                b.addEventListener('click', function () {
                    state.picks[r.id] = i;
                    renderRounds(state.room, peopleById(state.room));
                });
                opts.appendChild(b);
            });
            card.appendChild(opts);

            var foot = el('div', 'gs-round__foot');
            var waitingOn = Math.max(0, here - r.locked.length);
            foot.appendChild(el('span', 'gs-round__hint', canAnswer
                ? (pick == null ? 'Pick one. Nobody sees it until everyone’s in.' : 'Sure? Once you lock in, it’s in.')
                : mine !== null ? (waitingOn ? 'Locked in. Waiting for ' + waitingOn + ' more…' : 'Everyone’s in. Revealing…')
                : 'The round is waiting on the room.'));
            var actions = el('span', 'gs-round__actions');
            if (me === res.host_user_id && r.locked.length > 0 && res.status === 'active') {
                var reveal = el('button', 'ds-btn ds-btn--tertiary ds-btn--sm', 'Reveal now');
                reveal.type = 'button';
                reveal.dataset.key = 'reveal';
                reveal.addEventListener('click', function () { roundAction('round_reveal', { round_id: r.id }, reveal); });
                actions.appendChild(reveal);
            }
            if (canAnswer) {
                var lock = el('button', 'ds-btn ds-btn--cta ds-btn--sm');
                lock.type = 'button';
                lock.dataset.key = 'lock';
                lock.innerHTML = icon(LOCK_ICON, 'gs-ico') + '<span>Lock in</span>';
                lock.disabled = pick == null;
                lock.addEventListener('click', function () {
                    if (state.picks[r.id] == null) return;
                    roundAction('round_answer', { round_id: r.id, choice: state.picks[r.id] }, lock);
                });
                actions.appendChild(lock);
            }
            foot.appendChild(actions);
            card.appendChild(foot);
            return;
        }

        // Revealed: everyone's answers at once
        var votes = r.options.map(function () { return []; });
        (r.answers || []).forEach(function (a) { if (votes[a.choice]) votes[a.choice].push(a.user_id); });
        var answered = (r.answers || []).length;
        var right = votes[r.correct] ? votes[r.correct].length : 0;
        card.appendChild(el('p', 'gs-round__split', right === answered && answered > 1 ? 'Everyone got it.'
            : right === 0 ? 'Nobody got this one. Good question, then.'
            : right + ' of ' + answered + ' got it. ' + (answered - right) + ' went another way.'));
        var results = el('div', 'gs-round__results');
        r.options.forEach(function (text, i) {
            var isRight = i === r.correct;
            var row = el('div', 'gs-round__result' + (isRight ? ' is-right' : '') + (mine === i ? ' is-mine' : ''));
            var line = el('div', 'gs-round__line');
            line.appendChild(el('span', 'gs-round__key', LETTERS[i]));
            line.appendChild(el('span', 'gs-round__text', text));
            var who = el('span', 'gs-round__voters');
            votes[i].slice(0, 6).forEach(function (uid) {
                var p = byId[uid];
                var a = el('span', 'gs-avatar gs-round__voter' + (uid === me ? ' is-me' : ''), p ? firstName(p.display_name).charAt(0).toUpperCase() : '?');
                a.title = uid === me ? 'You' : (p ? p.display_name : 'Someone');
                who.appendChild(a);
            });
            line.appendChild(who);
            line.appendChild(el('span', 'gs-round__n', String(votes[i].length)));
            if (isRight) line.insertAdjacentHTML('beforeend', icon(CHECK_ICON, 'gs-ico gs-round__check'));
            row.appendChild(line);
            var bar = el('div', 'gs-round__bar');
            var fill = el('span');
            bar.appendChild(fill);
            row.appendChild(bar);
            // Grow the bars in after the card lands (the reveal moment); a beat later
            // than the first paint, or the browser skips the transition
            setTimeout(function () { fill.style.width = (answered ? Math.round(votes[i].length / answered * 100) : 0) + '%'; }, 60);
            row.setAttribute('aria-label', LETTERS[i] + ': ' + text + '. ' + votes[i].length + (votes[i].length === 1 ? ' answer' : ' answers') + (isRight ? '. Correct.' : ''));
            results.appendChild(row);
        });
        card.appendChild(results);
        if (r.explanation) {
            var why = el('p', 'gs-round__why');
            why.appendChild(el('strong', null, 'Why: '));
            why.appendChild(document.createTextNode(r.explanation));
            card.appendChild(why);
        }
    }

    function peopleById(res) {
        var byId = {};
        ((res && res.participants) || []).forEach(function (p) { byId[p.user_id] = p; });
        return byId;
    }

    function roundAction(action, data, btn) {
        var sid = state.sessionId;
        if (btn) btn.disabled = true;
        api(action, Object.assign({ session_id: sid }, data)).then(function (res) {
            if (!res.success) { state.sendError = res.error || 'That didn’t go through.'; renderStatus(); if (btn) btn.disabled = false; }
            else if (action === 'round_answer') delete state.picks[data.round_id];
        }).catch(function () {
            state.sendError = 'Couldn’t reach TutorMind. Try again.'; renderStatus();
            if (btn) btn.disabled = false;
        }).then(function () { if (state.sessionId === sid) { stopPolling(); poll(); } });
    }

    els.roundBtn.addEventListener('click', function () {
        var sid = state.sessionId;
        var label = els.roundBtn.querySelector('span');
        els.roundBtn.disabled = true;
        label.textContent = 'Writing a question…';
        setTimeout(function () { if (state.sessionId === sid) { stopPolling(); poll(); } }, 400); // shows "Q is thinking"
        api('round_start', { session_id: sid, source: 'host' }).then(function (res) {
            if (!res.success) { state.sendError = res.error || 'Couldn’t start a round.'; renderStatus(); }
        }).catch(function () {
            state.sendError = 'Couldn’t reach TutorMind. Try again.'; renderStatus();
        }).then(function () {
            els.roundBtn.disabled = false;
            label.textContent = ROUND_NAME;
            if (state.sessionId === sid) { stopPolling(); poll(); }
        });
    });

    // ---------------------------------------------------------------- Reactions
    function renderReactions(res) {
        var all = res.reactions || {};
        var ended = res.status === 'completed';
        els.transcript.querySelectorAll('.gs-reacts').forEach(function (box) {
            var id = Number(box.closest('.gs-msg').dataset.id);
            var counts = all[id] || {};
            var own = box.dataset.mine === '1';
            var sig = JSON.stringify([counts, ended, box.classList.contains('is-picking')]);
            if (box.dataset.sig === sig) return;
            box.dataset.sig = sig;
            box.innerHTML = '';
            REACTIONS.forEach(function (rx) {
                var c = counts[rx.kind];
                if (!c || !c[0]) return;
                var b = el('button', 'gs-react' + (c[1] ? ' is-mine' : ''));
                b.type = 'button';
                b.innerHTML = icon(rx.icon) + '<span>' + rx.label + ' · ' + c[0] + '</span>';
                b.setAttribute('aria-pressed', String(!!c[1]));
                b.setAttribute('aria-label', rx.label + ', ' + c[0] + (c[1] ? ', including you' : ''));
                b.disabled = own || ended;
                b.addEventListener('click', function () { react(id, rx.kind); });
                box.appendChild(b);
            });
            if (own || ended) return;
            // Add a reaction: a small button that opens the four
            var add = el('button', 'gs-react gs-react--add');
            add.type = 'button';
            add.setAttribute('aria-label', 'React');
            add.setAttribute('aria-expanded', String(box.classList.contains('is-picking')));
            add.innerHTML = icon('<circle cx="12" cy="12" r="9"/><path d="M8.5 14.5a4.5 4.5 0 0 0 7 0M9 9.5h.01M15 9.5h.01"/>');
            add.addEventListener('click', function (e) {
                e.stopPropagation();
                var wasOpen = box.classList.contains('is-picking');
                els.transcript.querySelectorAll('.gs-reacts.is-picking').forEach(function (b) { b.classList.remove('is-picking'); b.dataset.sig = ''; });
                if (!wasOpen) box.classList.add('is-picking');
                renderReactions(state.room);
            });
            box.appendChild(add);
            if (box.classList.contains('is-picking')) {
                var pick = el('span', 'gs-reacts__picker');
                REACTIONS.forEach(function (rx) {
                    var b = el('button', 'gs-react');
                    b.type = 'button';
                    b.innerHTML = icon(rx.icon) + '<span>' + rx.label + '</span>';
                    b.addEventListener('click', function () {
                        box.classList.remove('is-picking');
                        react(id, rx.kind);
                    });
                    pick.appendChild(b);
                });
                box.appendChild(pick);
            }
        });
    }

    function react(messageId, kind) {
        var sid = state.sessionId;
        api('react', { session_id: sid, message_id: messageId, kind: kind }).then(function (res) {
            if (!res.success) { state.sendError = res.error || 'That didn’t go through.'; renderStatus(); }
        }).catch(function () {}).then(function () { if (state.sessionId === sid) { stopPolling(); poll(); } });
    }

    // ---------------------------------------------------------------- Bridge
    function renderBridge(res) {
        var b = res.bridge;
        els.bridge.hidden = !b || res.status === 'waiting';
        if (!b) return;
        var seg = 18, gap = 2.5, dash = [];
        for (var i = 0; i < b.stones; i++) dash.push(seg, gap);
        // pathLength 100 = 5 stones of 18 + 4 gaps of 2.5; the fill shows the laid ones
        els.bridgeFill.style.strokeDasharray = b.stones ? dash.join(' ') + ' 100' : '0 100';
        els.bridgeLabel.textContent = b.stones + ' of ' + b.per_bridge + ' stones' + (b.built ? ' · ' + b.built + (b.built === 1 ? ' bridge built' : ' bridges built') : '');
        els.bridge.setAttribute('aria-label', 'Room bridge: ' + els.bridgeLabel.textContent + '. A stone for every round the room settles together.');
        els.bridge.title = 'A stone for every round the room settles together';
    }

    // ---------------------------------------------------------------- Focus timer
    function renderFocus(res) {
        var f = res.status === 'active' ? res.focus : null;
        els.focus.hidden = !f;
        els.focusStop.hidden = !f || res.me !== res.host_user_id;
        if (!f) { stopFocusTick(); return; }
        // Re-sync from the server every poll (it owns the clock; seconds_left avoids timezone mix-ups)
        state.focusEndsAt = Date.now() + f.seconds_left * 1000;
        if (!state.focusTick) state.focusTick = setInterval(tickFocus, 1000);
        tickFocus();
    }
    function tickFocus() {
        var left = Math.max(0, Math.round((state.focusEndsAt - Date.now()) / 1000));
        els.focusTime.textContent = left > 0 ? Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0') : 'Time’s up';
        els.focus.classList.toggle('is-due', left <= 0);
        // Due: ask the server to fire it. Every client may ask; one claims it.
        if (left <= 0 && Date.now() - state.timerAskAt > 8000 && state.sessionId) {
            state.timerAskAt = Date.now();
            var sid = state.sessionId;
            api('round_start', { session_id: sid, source: 'timer' }).catch(function () {})
                .then(function () { if (state.sessionId === sid) { stopPolling(); poll(); } });
        }
    }
    function stopFocusTick() {
        clearInterval(state.focusTick);
        state.focusTick = null;
    }
    function closeFocusMenu() {
        els.focusMenu.hidden = true;
        els.focusBtn.setAttribute('aria-expanded', 'false');
    }
    els.focusBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        var open = els.focusMenu.hidden;
        closeMenus();
        closeFocusMenu();
        if (!open) return;
        els.focusMenu.hidden = false;
        els.focusBtn.setAttribute('aria-expanded', 'true');
        els.focusMenu.querySelector('button').focus();
    });
    els.focusMenu.querySelectorAll('[data-minutes]').forEach(function (b) {
        b.addEventListener('click', function () {
            closeFocusMenu();
            var sid = state.sessionId;
            api('focus_start', { session_id: sid, minutes: Number(b.dataset.minutes) }).then(function (res) {
                if (!res.success) { state.sendError = res.error; renderStatus(); }
            }).catch(function () {}).then(function () { if (state.sessionId === sid) { stopPolling(); poll(); } });
        });
    });
    els.focusStop.addEventListener('click', function () {
        var sid = state.sessionId;
        api('focus_stop', { session_id: sid }).catch(function () {}).then(function () { if (state.sessionId === sid) { stopPolling(); poll(); } });
    });

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
        if (!e.target.closest('.gs-menu-wrap')) { closeMenus(); closeFocusMenu(); }
        if (!e.target.closest('.gs-reacts')) {
            var picking = els.transcript.querySelectorAll('.gs-reacts.is-picking');
            if (picking.length) { picking.forEach(function (b) { b.classList.remove('is-picking'); b.dataset.sig = ''; }); if (state.room) renderReactions(state.room); }
        }
        // The phone's people panel closes on a tap outside it
        if (els.room.classList.contains('is-people-open') && !e.target.closest('.gs-people, #gsPeopleToggle')) closePeople();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeMenus(); closeFocusMenu(); closePeople(); els.endConfirm.hidden = true; }
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
        var text = content.replace(HANDOFF_RE, '').replace(/```[\s\S]*?```/g, '').replace(/\*/g, '').trim();
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
        els.codeInput.value = inviteCode;
        joinByCode(inviteCode);
    } else if (savedRoom) {
        enterRoom(savedRoom); // a refresh drops you back in the same room
    } else {
        loadRejoin();
    }
})();
