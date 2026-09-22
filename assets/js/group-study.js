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
        waitingAvatarStack: document.getElementById('gsWaitingAvatarStack'),
        copyCodeBtn: document.getElementById('gsCopyCodeBtn'),
        sessionTopic: document.getElementById('gsSessionTopic'),
        currentTeacher: document.getElementById('gsCurrentTeacher'),
        participants: document.getElementById('gsParticipants'),
        transcript: document.getElementById('gsTranscript'),
        typingIndicator: document.getElementById('gsTypingIndicator'),
        typingText: document.getElementById('gsTypingText'),
        messageInput: document.getElementById('gsMessageInput'),
        sendBtn: document.getElementById('gsSendBtn'),
        micBtn: document.getElementById('gsMicBtn'),
        composer: document.getElementById('gsComposer'),
        composerHint: document.getElementById('gsComposerHint'),
        exitBtns: document.querySelectorAll('[data-gs-exit]'),
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

    // ---- Copy join code ----
    if (els.copyCodeBtn) {
        els.copyCodeBtn.addEventListener('click', function () {
            var code = els.waitingCode.textContent.trim();
            if (!code || code === '------') return;
            var done = function () {
                els.copyCodeBtn.dataset.copied = 'true';
                els.copyCodeBtn.innerHTML = '<i class="fas fa-check"></i> Copied';
                setTimeout(function () {
                    els.copyCodeBtn.dataset.copied = 'false';
                    els.copyCodeBtn.innerHTML = '<i class="fas fa-copy"></i> Copy code';
                }, 1500);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(code).then(done).catch(function () {});
            } else {
                var tmp = document.createElement('textarea');
                tmp.value = code;
                tmp.style.position = 'fixed';
                tmp.style.opacity = '0';
                document.body.appendChild(tmp);
                tmp.select();
                try { document.execCommand('copy'); done(); } catch (e) {}
                document.body.removeChild(tmp);
            }
        });
    }

    // ---- TTS / Voice Controller ----
    var currentTTSAudio = null;
    var currentSpeakingBtn = null;

    function stopTTS() {
        if (currentTTSAudio) {
            currentTTSAudio.pause();
            currentTTSAudio = null;
        }
        if (window.speechSynthesis) {
            window.speechSynthesis.cancel();
        }
        if (currentSpeakingBtn) {
            currentSpeakingBtn.dataset.speaking = 'false';
            currentSpeakingBtn.innerHTML = '<i class="fas fa-volume-up"></i>';
            currentSpeakingBtn.title = 'Read aloud';
            currentSpeakingBtn = null;
        }
    }

    function base64ToBlob(base64, mimeType) {
        var byteChars = atob(base64);
        var byteNumbers = new Array(byteChars.length);
        for (var i = 0; i < byteChars.length; i++) {
            byteNumbers[i] = byteChars.charCodeAt(i);
        }
        var byteArray = new Uint8Array(byteNumbers);
        return new Blob([byteArray], { type: mimeType });
    }

    function speakText(text, btn) {
        if (!text || !text.trim()) return;

        // If clicking on the button that is already playing, toggle stop
        if (btn && btn.dataset.speaking === 'true') {
            stopTTS();
            return;
        }

        stopTTS();

        if (btn) {
            btn.dataset.speaking = 'true';
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.title = 'Loading voice...';
            currentSpeakingBtn = btn;
        }

        fetch('api/tts.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ text: text })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (btn !== currentSpeakingBtn) return;

            if (data.success && data.audio) {
                var audioBlob = base64ToBlob(data.audio, data.contentType || 'audio/mpeg');
                var audioUrl = URL.createObjectURL(audioBlob);
                currentTTSAudio = new Audio(audioUrl);

                currentTTSAudio.onplay = function () {
                    if (btn === currentSpeakingBtn) {
                        btn.innerHTML = '<i class="fas fa-stop"></i>';
                        btn.title = 'Stop reading';
                    }
                };
                currentTTSAudio.onended = function () {
                    stopTTS();
                };
                currentTTSAudio.onerror = function () {
                    fallbackBrowserSpeak(text, btn);
                };
                currentTTSAudio.play().catch(function () {
                    fallbackBrowserSpeak(text, btn);
                });
            } else {
                fallbackBrowserSpeak(data.text || text, btn);
            }
        })
        .catch(function (err) {
            console.error('Group Study TTS error:', err);
            fallbackBrowserSpeak(text, btn);
        });
    }

    function fallbackBrowserSpeak(text, btn) {
        if (!window.speechSynthesis) {
            stopTTS();
            return;
        }

        window.speechSynthesis.cancel();
        var utterance = new SpeechSynthesisUtterance(text);
        utterance.rate = 1.0;

        var voices = window.speechSynthesis.getVoices();
        var preferredVoice = voices.find(function (v) {
            return v.name.includes('Google') || v.name.includes('Microsoft') || v.name.includes('Samantha');
        });
        if (preferredVoice) utterance.voice = preferredVoice;

        utterance.onstart = function () {
            if (btn === currentSpeakingBtn) {
                btn.innerHTML = '<i class="fas fa-stop"></i>';
                btn.title = 'Stop reading';
            }
        };
        utterance.onend = function () {
            stopTTS();
        };
        utterance.onerror = function () {
            stopTTS();
        };

        window.speechSynthesis.speak(utterance);
    }

    // ---- Voice Input (Speech-to-Text) Controller ----
    var recognition = null;
    var isListening = false;
    var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

    if (SpeechRecognition) {
        recognition = new SpeechRecognition();
        recognition.continuous = true;
        recognition.interimResults = true;
        recognition.lang = 'en-US';

        var baseTranscript = '';

        recognition.onstart = function () {
            isListening = true;
            if (els.micBtn) {
                els.micBtn.dataset.listening = 'true';
                els.micBtn.title = 'Listening... Click to stop';
            }
            baseTranscript = els.messageInput.value ? els.messageInput.value.trim() + ' ' : '';
        };

        recognition.onresult = function (event) {
            var interim = '';
            for (var i = event.resultIndex; i < event.results.length; i++) {
                interim += event.results[i][0].transcript;
            }
            els.messageInput.value = baseTranscript + interim;
            els.messageInput.scrollTop = els.messageInput.scrollHeight;
        };

        recognition.onend = function () {
            isListening = false;
            if (els.micBtn) {
                els.micBtn.dataset.listening = 'false';
                els.micBtn.title = 'Voice typing (Speak your thoughts)';
            }
        };

        recognition.onerror = function (event) {
            console.warn('Speech recognition error:', event.error);
            isListening = false;
            if (els.micBtn) {
                els.micBtn.dataset.listening = 'false';
                els.micBtn.title = 'Voice typing (Speak your thoughts)';
            }
        };
    }

    if (els.micBtn) {
        els.micBtn.addEventListener('click', function () {
            if (!recognition) {
                alert('Voice input is not supported in this browser. Please try Chrome or Edge.');
                return;
            }
            if (isListening) {
                recognition.stop();
            } else {
                try {
                    recognition.start();
                } catch (e) {
                    console.error('Speech recognition start error:', e);
                }
            }
        });
    }

    // ---- Exit room ----
    // Deliberately non-destructive: only stops polling and clears local
    // state. Your participant row stays on the server, so rejoining with
    // the same code later picks the session back up with full history —
    // same behavior already confirmed for a plain page refresh.
    function exitRoom() {
        stopTTS();
        if (isListening && recognition) {
            try { recognition.stop(); } catch (e) {}
        }
        if (state.pollTimer) { clearInterval(state.pollTimer); state.pollTimer = null; }
        state.sessionId = null;
        state.lastMessageId = 0;
        els.transcript.innerHTML = '';
        els.messageInput.value = '';
        els.composer.hidden = false;
        els.composerHint.hidden = true;
        els.typingIndicator.hidden = true;
        lastTypingPingAt = 0;
        els.topicInput.value = '';
        els.joinCodeInput.value = '';
        setError('');
        showPanel('landing');
    }
    els.exitBtns.forEach(function (btn) {
        btn.addEventListener('click', exitRoom);
    });

    // ---- Teaching / messaging ----

    function sendMessage() {
        if (isListening && recognition) {
            try { recognition.stop(); } catch (e) {}
        }
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

    // Throttled typing ping — at most once every 2.5s while the user keeps
    // typing, enough to keep the server's 6s "is typing" window topped up
    // without pinging on every keystroke. No explicit "stopped typing"
    // signal; the field just goes stale and the indicator clears itself,
    // same eventual-consistency shape as the rest of this feature.
    var lastTypingPingAt = 0;
    els.messageInput.addEventListener('input', function () {
        if (!state.sessionId) return;
        var now = Date.now();
        if (now - lastTypingPingAt < 2500) return;
        lastTypingPingAt = now;
        post('typing', { session_id: state.sessionId });
    });

    function renderTyping(names) {
        if (!names || !names.length) { els.typingIndicator.hidden = true; return; }
        var text;
        if (names.length === 1) text = names[0] + ' is typing...';
        else if (names.length === 2) text = names[0] + ' and ' + names[1] + ' are typing...';
        else text = names.length + ' people are typing...';
        els.typingText.textContent = text;
        els.typingIndicator.hidden = false;
    }

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
            renderTyping(res.typing);

            res.messages.forEach(function (msg) {
                appendMessage(msg);
                if (msg.id > state.lastMessageId) state.lastMessageId = msg.id;
            });

            if (res.status === 'completed') {
                els.composer.hidden = true;
                els.composerHint.hidden = false;
                els.typingIndicator.hidden = true;
                clearInterval(state.pollTimer);
                state.pollTimer = null;
            }
        });
    }

    function renderParticipantChips(container, participants, teacherUserId) {
        if (!container) return;
        container.innerHTML = '';
        (participants || []).forEach(function (p) {
            var chip = document.createElement('span');
            chip.className = 'gs-participant-chip' + (p.user_id === teacherUserId ? ' gs-is-teacher' : '');
            chip.textContent = p.display_name;
            container.appendChild(chip);
        });
    }

    function renderAvatarStack(container, participants) {
        if (!container) return;
        container.innerHTML = '';
        (participants || []).forEach(function (p) {
            var avatar = document.createElement('div');
            avatar.className = 'gs-msg-avatar ' + avatarToneClass(p.display_name);
            avatar.title = p.display_name;
            avatar.textContent = p.display_name.charAt(0).toUpperCase();
            container.appendChild(avatar);
        });
    }

    function renderSessionMeta(res) {
        els.sessionTopic.textContent = res.topic;
        els.currentTeacher.textContent = res.current_teacher_name || '—';
        renderParticipantChips(els.participants, res.participants, res.current_teacher_user_id);
        renderAvatarStack(els.waitingAvatarStack, res.participants);
    }

    var AVATAR_TONE_COUNT = 4;
    function avatarToneClass(name) {
        var hash = 0;
        for (var i = 0; i < name.length; i++) hash = (hash * 31 + name.charCodeAt(i)) >>> 0;
        return 'gs-avatar-tone-' + (hash % AVATAR_TONE_COUNT);
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
            avatar.classList.add(avatarToneClass(msg.sender));
            avatar.textContent = msg.sender.charAt(0).toUpperCase();
        }

        var body = document.createElement('div');
        body.className = 'gs-msg-body';
        var sender = document.createElement('div');
        sender.className = 'gs-msg-sender';

        var senderName = document.createElement('span');
        senderName.textContent = msg.sender;
        sender.appendChild(senderName);

        // For AI messages, attach a "Read aloud" speaker button
        if (msg.is_ai) {
            var speakBtn = document.createElement('button');
            speakBtn.type = 'button';
            speakBtn.className = 'gs-msg-speak-btn';
            speakBtn.title = 'Read aloud';
            speakBtn.setAttribute('aria-label', 'Read message aloud');
            speakBtn.innerHTML = '<i class="fas fa-volume-up"></i>';

            // Clean prose for speaking (exclude code/chips blocks)
            var textToSpeak = msg.content.replace(/```[\s\S]*?```/g, '').trim();
            speakBtn.addEventListener('click', (function (text, button) {
                return function (e) {
                    e.stopPropagation();
                    speakText(text, button);
                };
            })(textToSpeak, speakBtn));

            sender.appendChild(speakBtn);
        }

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
