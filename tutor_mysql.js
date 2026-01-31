document.addEventListener('DOMContentLoaded', async () => {
    const tutorForm = document.getElementById('tutorForm');
    const questionInput = document.getElementById('question');
    const chatMessages = document.getElementById('chat-container');
    const conversationIdInput = document.getElementById('conversation_id');
    const submitBtn = document.getElementById('ai-submit-btn');
    const fileInput = document.getElementById('file-attachment');
    const attachmentPreviewArea = document.getElementById('attachment-preview-area');
    const newChatBtn = document.getElementById('newChatBtn');
    const chatHistoryContainer = document.getElementById('chat-history-container');
    const menuToggle = document.getElementById('menu-toggle');
    const sidebar = document.getElementById('sidebar');
    const userAccountTrigger = document.getElementById('user-account-trigger');
    const userAccountMenu = document.getElementById('user-account-menu');
    const userAccountChevron = document.getElementById('user-account-chevron');
    const darkModeToggle = document.getElementById('darkModeToggle');
    const welcomeScreen = document.getElementById('welcome-screen');
    const conversationTitleEl = document.getElementById('conversation-title');

    // --- Apply legibility from localStorage immediately (before settings load) ---
    const savedLegibility = localStorage.getItem('legibility');
    if (savedLegibility) {
        const scale = parseInt(savedLegibility) / 100;
        document.documentElement.style.setProperty('--legibility-scale', scale);
        document.documentElement.style.setProperty('--legibility-line-height', `${1.5 + (scale - 1) * 0.4}`);
    }

    // --- Initialize Settings Manager FIRST ---
    // This ensures settings (especially dark mode) are applied before page renders
    window.settingsManager = new SettingsManager();
    window.settingsManager.init();

    // Load settings immediately and wait for them to apply
    try {
        await window.settingsManager.loadSettings();
        console.log('Settings applied successfully on page load');
    } catch (error) {
        console.error('Failed to load settings on page load:', error);
    }

    // --- Load chat history on page load ---
    // --- Load chat history on page load ---
    // Check if history is already SSR rendered
    if (chatHistoryContainer.children.length === 0) {
        loadChatHistory();
    }

    // --- Initial State ---
    // If there's an active conversation ID (from URL/PHP), load it.
    if (conversationIdInput.value) {
        // Check if messages are already SSR rendered
        const existingMessages = chatMessages.querySelectorAll('.message');
        if (existingMessages.length > 0) {
            // Hydrate existing messages (attach listeners)
            console.log('Hydrating SSR messages...');
            hydrateMessages();
            // Scroll to bottom
            chatMessages.scrollTop = chatMessages.scrollHeight;
        } else {
            loadConversation(conversationIdInput.value);
        }
    } else {
        // If there's no active conversation, show the welcome screen and hide title.
        welcomeScreen.style.display = 'flex';
        document.body.classList.add('chat-empty');
        if (conversationTitleEl) conversationTitleEl.style.display = 'none';
    }

    // --- Dark Mode Logic ---
    // Note: Dark mode is now managed by the SettingsManager and loaded from the database.
    // The toggle in the user menu will update both the UI and the database.

    // Listen for dark mode toggle changes in the main UI
    if (darkModeToggle) {
        darkModeToggle.addEventListener('change', async () => {
            const isDark = darkModeToggle.checked;
            document.body.classList.toggle('dark-mode', isDark);
            localStorage.setItem('darkMode', isDark ? 'enabled' : 'disabled');

            // Update header button icon to stay in sync
            const headerIcon = document.querySelector('#dark-mode-toggle i');
            if (headerIcon) {
                headerIcon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
            }

            // Update TutorMindChat instance state if it exists
            if (window.chatApp) {
                window.chatApp.darkMode = isDark;
            }

            // Save to database via settings manager
            if (window.settingsManager) {
                await window.settingsManager.debouncedSave({ dark_mode: isDark });
            }
        });
    }

    // --- Helper function to get base path for fetch URLs ---
    function getBasePath() {
        const baseTag = document.querySelector('base');
        if (baseTag && baseTag.href) {
            const baseUrl = new URL(baseTag.href);
            return baseUrl.pathname.replace(/\/$/, '');
        }
        // Fallback: calculate from pathname
        const pathParts = window.location.pathname.split('/');
        if (pathParts[pathParts.length - 1].includes('.') || 
            pathParts[pathParts.length - 1] === 'chat' ||
            /^\d+$/.test(pathParts[pathParts.length - 1])) {
            pathParts.pop();
        }
        if (pathParts[pathParts.length - 1] === 'chat') {
            pathParts.pop();
        }
        return pathParts.join('/');
    }


    // --- Function to add a message to the chat window ---
    function addMessage(sender, messageHtml, animate = false) {
        const messageWrapper = document.createElement('div');
        messageWrapper.classList.add('message', sender); // 'message ai' or 'message user'

        // Add avatar
        const avatar = document.createElement('div');
        avatar.classList.add('message-avatar');
        avatar.textContent = sender === 'ai' ? '🤖' : '👤';
        messageWrapper.appendChild(avatar);

        const messageBubble = document.createElement('div');
        messageBubble.classList.add('message-content');

        messageWrapper.appendChild(messageBubble);
        chatMessages.appendChild(messageWrapper);

        if (animate && sender === 'ai') {
            // Use typewriter effect for AI messages
            typeWriter(messageBubble, messageHtml, 10, () => {
                // Callback after typing finishes
                finalizeMessage(messageBubble);
            });
        } else {
            // Instant render for user messages or history
            messageBubble.innerHTML = messageHtml;
            finalizeMessage(messageBubble);
        }

        // Scroll to the bottom
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // --- Function to hydrate SSR messages ---
    function hydrateMessages() {
        const bubbles = chatMessages.querySelectorAll('.message-content');
        bubbles.forEach(bubble => {
            const wrapper = bubble.closest('.message');
            const isAi = wrapper.classList.contains('ai');

            // For AI messages, ensure footer buttons exist
            if (isAi && !bubble.querySelector('.message-footer')) {
                const footer = document.createElement('div');
                footer.className = 'message-footer';
                footer.innerHTML = `
                    <button class="read-aloud-btn" title="Read aloud">
                        <i class="fas fa-volume-up"></i>
                    </button>
                    <button class="copy-btn" title="Copy response">
                        <i class="fas fa-copy"></i>
                    </button>
                    <div class="feedback-btns">
                        <button class="feedback-btn feedback-positive" title="Good response" data-rating="positive">
                            <i class="fas fa-thumbs-up"></i>
                        </button>
                        <button class="feedback-btn feedback-negative" title="Bad response" data-rating="negative">
                            <i class="fas fa-thumbs-down"></i>
                        </button>
                    </div>
                `;
                bubble.appendChild(footer);
            }

            finalizeMessage(bubble);
        });
    }

    // --- Helper to finalize message (highlighting, mathjax, etc) ---
    function finalizeMessage(messageBubble) {
        // Add copy buttons to code blocks and trigger syntax highlighting
        addCopyButtonsToCodeBlocks(messageBubble);

        // Scroll to the bottom one last time
        chatMessages.scrollTop = chatMessages.scrollHeight;

        // Force MathJax to re-render the new content
        if (window.MathJax) {
            // MathJax v3
            if (MathJax.typesetPromise) {
                MathJax.typesetPromise([messageBubble]).then(() => {
                    // Scroll again after math rendering might have changed height
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }).catch((err) => {
                    console.error('MathJax typeset failed:', err);
                });
            }
            // MathJax v2 fallback
            else if (MathJax.Hub && MathJax.Hub.Queue) {
                MathJax.Hub.Queue(["Typeset", MathJax.Hub, messageBubble]);
            }
        }
    }

    // --- Event Delegation for Chat Buttons ---
    // This replaces attachMessageEventListeners and works for all current and future buttons
    chatMessages.addEventListener('click', (e) => {
        const readAloudBtn = e.target.closest('.read-aloud-btn');
        if (readAloudBtn) {
            handleReadAloud(readAloudBtn);
            return;
        }

        const copyBtn = e.target.closest('.copy-btn');
        if (copyBtn) {
            handleCopyClick(copyBtn);
            return;
        }
        
        const feedbackBtn = e.target.closest('.feedback-btn');
        if (feedbackBtn) {
            handleFeedbackClick(feedbackBtn);
            return;
        }
    });

    // --- Handle Read Aloud button click ---
    function handleReadAloud(button) {
        const messageBubble = button.closest('.message-content');
        if (!messageBubble) return;
        
        // Get the text content, excluding the footer buttons
        const messageContent = messageBubble.cloneNode(true);
        const footer = messageContent.querySelector('.message-footer');
        if (footer) footer.remove();
        
        // Get plain text, stripping HTML
        const text = messageContent.innerText.trim();
        
        if (text && window.VoiceManager) {
            // Update button to show it's playing
            button.innerHTML = '<i class="fas fa-stop"></i>';
            button.title = 'Stop reading';
            
            // Speak the text
            window.VoiceManager.speak(text).then(() => {
                // Reset button when done
                button.innerHTML = '<i class="fas fa-volume-up"></i>';
                button.title = 'Read aloud';
            }).catch(() => {
                button.innerHTML = '<i class="fas fa-volume-up"></i>';
                button.title = 'Read aloud';
            });
        }
    }

    // --- Text Selection Clarification Popup ---
    let selectionPopup = null;
    let selectedTextForClarification = '';
    let quoteReference = null; // The visual quote element above input
    
    // Create the popup element once
    function createSelectionPopup() {
        if (selectionPopup) return;
        selectionPopup = document.createElement('div');
        selectionPopup.className = 'selection-popup';
        selectionPopup.innerHTML = `
            <button class="selection-popup-btn" title="Quote and reference this text">
                <i class="fas fa-quote-left"></i> Quote & Ask
            </button>
        `;
        selectionPopup.style.display = 'none';
        document.body.appendChild(selectionPopup);
        
        // Handle quote button click
        selectionPopup.querySelector('.selection-popup-btn').addEventListener('click', () => {
            if (!selectedTextForClarification) return;
            
            // Save the text before hiding (hideSelectionPopup clears selectedTextForClarification)
            const textToQuote = selectedTextForClarification;
            
            hideSelectionPopup();
            showQuoteReference(textToQuote);
            
            // Clear selection and focus input
            window.getSelection().removeAllRanges();
            questionInput.focus();
        });
    }
    
    // Show the quoted text as a reference above the input
    function showQuoteReference(text) {
        // Remove existing quote if any
        hideQuoteReference();
        
        // Truncate long quotes for display
        const displayText = text.length > 150 ? text.substring(0, 150) + '...' : text;
        
        quoteReference = document.createElement('div');
        quoteReference.className = 'quote-reference';
        quoteReference.innerHTML = `
            <div class="quote-reference-content">
                <i class="fas fa-quote-left quote-icon"></i>
                <span class="quote-text">${displayText}</span>
                <button class="quote-remove" title="Remove quote">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        // Store full text as data attribute for sending
        quoteReference.dataset.fullQuote = text;
        
        // Insert above the form (tutorForm is defined at module scope)
        tutorForm.parentElement.insertBefore(quoteReference, tutorForm);
        
        // Handle remove button
        quoteReference.querySelector('.quote-remove').addEventListener('click', hideQuoteReference);
    }
    
    function hideQuoteReference() {
        if (quoteReference) {
            quoteReference.remove();
            quoteReference = null;
        }
    }
    
    // Expose for use in form submission
    window.getQuoteReference = function() {
        if (quoteReference) {
            return quoteReference.dataset.fullQuote;
        }
        return null;
    };
    
    window.clearQuoteReference = hideQuoteReference;
    
    function showSelectionPopup(x, y) {
        if (!selectionPopup) createSelectionPopup();
        
        // Position popup near selection
        const popupWidth = 140;
        const popupHeight = 40;
        
        // Keep popup within viewport
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        
        let posX = x - popupWidth / 2;
        let posY = y - popupHeight - 10; // Above selection
        
        // Clamp to viewport
        posX = Math.max(10, Math.min(posX, viewportWidth - popupWidth - 10));
        if (posY < 10) posY = y + 25; // Below selection if no room above
        
        selectionPopup.style.left = posX + 'px';
        selectionPopup.style.top = posY + 'px';
        selectionPopup.style.display = 'block';
    }
    
    function hideSelectionPopup() {
        if (selectionPopup) {
            selectionPopup.style.display = 'none';
        }
        selectedTextForClarification = '';
    }
    
    // Listen for text selection in AI messages
    document.addEventListener('mouseup', (e) => {
        // Ignore if clicking the popup itself
        if (selectionPopup && selectionPopup.contains(e.target)) return;
        
        const selection = window.getSelection();
        const selectedText = selection.toString().trim();
        
        // Check if selection is within an AI message bubble
        if (selectedText.length > 3 && selectedText.length < 500) {
            const anchorNode = selection.anchorNode;
            const focusNode = selection.focusNode;
            
            // Find if selection is within a message bubble
            const bubble = anchorNode?.parentElement?.closest('.message.ai .message-content') 
                        || focusNode?.parentElement?.closest('.message.ai .message-content');
            
            if (bubble) {
                selectedTextForClarification = selectedText;
                
                // Get selection position
                const range = selection.getRangeAt(0);
                const rect = range.getBoundingClientRect();
                
                showSelectionPopup(rect.left + rect.width / 2, rect.top);
                return;
            }
        }
        
        // Hide popup if no valid selection
        hideSelectionPopup();
    });
    
    // Hide popup when clicking elsewhere
    document.addEventListener('mousedown', (e) => {
        if (selectionPopup && !selectionPopup.contains(e.target)) {
            // Small delay to allow selection to complete
            setTimeout(() => {
                const selection = window.getSelection();
                if (!selection.toString().trim()) {
                    hideSelectionPopup();
                }
            }, 10);
        }
    });

    // --- Voice Input/Output Manager ---
    const VoiceManager = {
        recognition: null,
        isListening: false,
        autoReadEnabled: localStorage.getItem('autoReadEnabled') === 'true',
        currentAudio: null,
        
        init() {
            // Check for Speech Recognition support
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            
            if (SpeechRecognition) {
                this.recognition = new SpeechRecognition();
                this.recognition.continuous = true; // Keep listening until manually stopped
                this.recognition.interimResults = true;
                this.recognition.lang = 'en-US';
                this.recognition.maxAlternatives = 1;
                
                this.recognition.onresult = (event) => {
                    console.log('Speech result received:', event);
                    let transcript = '';
                    for (let i = event.resultIndex; i < event.results.length; i++) {
                        transcript += event.results[i][0].transcript;
                    }
                    console.log('Transcript:', transcript);
                    questionInput.value = transcript;
                };
                
                this.recognition.onstart = () => {
                    console.log('Speech recognition started');
                    this.isListening = true;
                    this.updateMicButton();
                };
                
                this.recognition.onend = () => {
                    console.log('Speech recognition ended');
                    this.isListening = false;
                    this.updateMicButton();
                };
                
                this.recognition.onerror = (event) => {
                    console.error('Speech recognition error:', event.error, event);
                    this.isListening = false;
                    this.updateMicButton();
                    
                    if (event.error === 'not-allowed') {
                        alert('Microphone access denied. Please enable it in your browser settings.');
                    } else if (event.error === 'no-speech') {
                        console.log('No speech detected. Try speaking louder or closer to the mic.');
                    } else if (event.error === 'network') {
                        alert('Network error. Speech recognition requires an internet connection.');
                    }
                };
                
                console.log('Speech recognition initialized successfully');
            } else {
                console.warn('Speech Recognition API not supported in this browser');
            }
            
            // Set up mic button
            const voiceBtn = document.querySelector('.voice-btn');
            if (voiceBtn) {
                // FORCE SHOW button for debugging
                voiceBtn.style.display = 'flex'; 
                
                voiceBtn.addEventListener('click', () => {
                    if (!this.recognition) {
                         alert('Voice input is not supported in this browser. Please use Chrome, Edge, or Safari.');
                         return;
                    }
                    this.toggleListening();
                });
            }
        },
        
        toggleListening() {
            if (!this.recognition) return;
            
            if (this.isListening) {
                this.recognition.stop();
            } else {
                // Stop any playing audio first
                this.stopAudio();
                this.recognition.start();
            }
        },
        
        updateMicButton() {
            const voiceBtn = document.querySelector('.voice-btn');
            if (!voiceBtn) return;
            
            if (this.isListening) {
                voiceBtn.classList.add('recording');
                voiceBtn.innerHTML = '<i class="fas fa-microphone-slash"></i> <span>Stop</span>';
                voiceBtn.title = 'Stop listening';
            } else {
                voiceBtn.classList.remove('recording');
                voiceBtn.innerHTML = '<i class="fas fa-microphone-lines"></i> <span>Voice</span>';
                voiceBtn.title = 'Voice input';
            }
        },
        
        async speak(text) {
            if (!text || text.trim().length === 0) return;
            
            // Stop any current audio
            this.stopAudio();
            
            try {
                // Try ElevenLabs TTS first
                const response = await fetch('api/tts.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ text })
                });
                
                const data = await response.json();
                
                if (data.success && data.audio) {
                    // Play ElevenLabs audio
                    const audioBlob = this.base64ToBlob(data.audio, data.contentType);
                    const audioUrl = URL.createObjectURL(audioBlob);
                    this.currentAudio = new Audio(audioUrl);
                    this.currentAudio.play();
                } else if (data.fallback) {
                    // Fallback to browser TTS
                    this.browserSpeak(data.text || text);
                }
            } catch (error) {
                console.error('TTS error:', error);
                // Fallback to browser TTS
                this.browserSpeak(text);
            }
        },
        
        browserSpeak(text) {
            if (!window.speechSynthesis) return;
            
            window.speechSynthesis.cancel();
            
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.rate = 1.0;
            utterance.pitch = 1.0;
            
            // Try to use a better voice if available
            const voices = window.speechSynthesis.getVoices();
            const preferredVoice = voices.find(v => 
                v.name.includes('Google') || 
                v.name.includes('Microsoft') || 
                v.name.includes('Samantha')
            );
            if (preferredVoice) utterance.voice = preferredVoice;
            
            window.speechSynthesis.speak(utterance);
        },
        
        stopAudio() {
            if (this.currentAudio) {
                this.currentAudio.pause();
                this.currentAudio = null;
            }
            if (window.speechSynthesis) {
                window.speechSynthesis.cancel();
            }
        },
        
        base64ToBlob(base64, contentType) {
            const byteCharacters = atob(base64);
            const byteArrays = [];
            
            for (let offset = 0; offset < byteCharacters.length; offset += 512) {
                const slice = byteCharacters.slice(offset, offset + 512);
                const byteNumbers = new Array(slice.length);
                for (let i = 0; i < slice.length; i++) {
                    byteNumbers[i] = slice.charCodeAt(i);
                }
                const byteArray = new Uint8Array(byteNumbers);
                byteArrays.push(byteArray);
            }
            
            return new Blob(byteArrays, { type: contentType });
        },
        
        toggleAutoRead() {
            this.autoReadEnabled = !this.autoReadEnabled;
            localStorage.setItem('autoReadEnabled', this.autoReadEnabled);
            return this.autoReadEnabled;
        }
    };
    
    // Initialize voice manager
    VoiceManager.init();
    
    // Expose for use in other parts of the app
    window.VoiceManager = VoiceManager;

    // --- Voice Mode Manager (ChatGPT-style full conversation mode) ---
    const VoiceModeManager = {
        state: 'idle', // idle | listening | processing | speaking
        overlay: null,
        circle: null,
        statusEl: null,
        transcriptEl: null,
        recognition: null,
        currentTranscript: '',
        conversationHistory: [],
        isActive: false,
        silenceTimeout: null,
        
        init() {
            this.overlay = document.getElementById('voice-mode-overlay');
            this.circle = document.getElementById('voice-mode-circle');
            this.statusEl = document.getElementById('voice-mode-status');
            this.transcriptEl = document.getElementById('voice-mode-transcript');
            
            // Set up trigger button
            const triggerBtn = document.getElementById('voice-mode-trigger');
            if (triggerBtn) {
                triggerBtn.addEventListener('click', () => this.open());
            }
            
            // Set up close button
            const closeBtn = document.getElementById('voice-mode-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => this.close());
            }
            
            // Escape key to close
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isActive) {
                    this.close();
                }
            });
            
            // Click on circle to toggle listening
            if (this.circle) {
                this.circle.addEventListener('click', () => {
                    if (this.state === 'idle') {
                        this.startListening();
                    } else if (this.state === 'listening') {
                        this.stopListening();
                    } else if (this.state === 'speaking') {
                        // Stop speaking and go to listening
                        VoiceManager.stopAudio();
                        this.startListening();
                    }
                });
            }
            
            // Set up speech recognition
            this.setupRecognition();
        },
        
        setupRecognition() {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SpeechRecognition) {
                console.warn('Speech Recognition not supported');
                return;
            }
            
            this.recognition = new SpeechRecognition();
            this.recognition.continuous = true;
            this.recognition.interimResults = true;
            this.recognition.lang = 'en-US';
            
            this.recognition.onresult = (event) => {
                let interimTranscript = '';
                let finalTranscript = '';
                
                for (let i = event.resultIndex; i < event.results.length; i++) {
                    const transcript = event.results[i][0].transcript;
                    if (event.results[i].isFinal) {
                        finalTranscript += transcript;
                    } else {
                        interimTranscript += transcript;
                    }
                }
                
                // Show interim results
                this.currentTranscript = finalTranscript + interimTranscript;
                this.statusEl.textContent = this.currentTranscript || 'Listening...';
                
                // Reset silence detection on any speech
                this.resetSilenceTimeout();
                
                // If we got a final result, process after brief pause
                if (finalTranscript) {
                    this.handleFinalTranscript(finalTranscript);
                }
            };
            
            this.recognition.onstart = () => {
                console.log('Voice Mode: Recognition started');
                this.setState('listening');
                this.resetSilenceTimeout();
            };
            
            this.recognition.onend = () => {
                console.log('Voice Mode: Recognition ended');
                // If still listening state, it ended unexpectedly - restart
                if (this.state === 'listening' && this.isActive) {
                    console.log('Voice Mode: Restarting recognition');
                    setTimeout(() => {
                        if (this.isActive && this.state === 'listening') {
                            this.recognition.start();
                        }
                    }, 100);
                }
            };
            
            this.recognition.onerror = (event) => {
                console.error('Voice Mode: Recognition error:', event.error);
                if (event.error === 'not-allowed') {
                    this.statusEl.textContent = 'Microphone access denied';
                    this.setState('idle');
                }
            };
        },
        
        resetSilenceTimeout() {
            if (this.silenceTimeout) {
                clearTimeout(this.silenceTimeout);
            }
            // If no speech for 2 seconds after final result, submit
            this.silenceTimeout = setTimeout(() => {
                if (this.currentTranscript && this.state === 'listening') {
                    this.processInput();
                }
            }, 2000);
        },
        
        handleFinalTranscript(text) {
            // User said something - wait a moment then process
            console.log('Voice Mode: Final transcript:', text);
        },
        
        open() {
            if (!this.overlay) return;
            
            this.isActive = true;
            this.overlay.classList.remove('hidden');
            this.conversationHistory = [];
            this.transcriptEl.innerHTML = '';
            this.setState('idle');
            this.statusEl.textContent = 'Tap to speak';
            
            // Auto-start listening after a brief delay
            setTimeout(() => {
                if (this.isActive) {
                    this.startListening();
                }
            }, 500);
        },
        
        close() {
            if (!this.overlay) return;
            
            this.isActive = false;
            this.overlay.classList.add('hidden');
            this.stopListening();
            VoiceManager.stopAudio();
            this.setState('idle');
            this.currentTranscript = '';
            
            if (this.silenceTimeout) {
                clearTimeout(this.silenceTimeout);
            }
        },
        
        startListening() {
            if (!this.recognition) {
                this.statusEl.textContent = 'Speech not supported';
                return;
            }
            
            this.currentTranscript = '';
            this.statusEl.textContent = 'Listening...';
            
            try {
                this.recognition.start();
            } catch (e) {
                // Already started - ignore
            }
        },
        
        stopListening() {
            if (this.recognition) {
                try {
                    this.recognition.stop();
                } catch (e) {
                    // Already stopped - ignore
                }
            }
            
            if (this.silenceTimeout) {
                clearTimeout(this.silenceTimeout);
            }
        },
        
        setState(newState) {
            this.state = newState;
            
            // Update circle state classes
            if (this.circle) {
                this.circle.className = 'voice-mode-circle ' + newState;
            }
            
            // Update status text based on state
            switch (newState) {
                case 'idle':
                    if (this.isActive) {
                        this.statusEl.textContent = 'Tap to speak';
                    }
                    break;
                case 'listening':
                    this.statusEl.textContent = 'Listening...';
                    break;
                case 'processing':
                    this.statusEl.textContent = 'Thinking...';
                    break;
                case 'speaking':
                    this.statusEl.textContent = 'Speaking...';
                    break;
            }
        },
        
        async processInput() {
            if (!this.currentTranscript.trim()) {
                this.startListening();
                return;
            }
            
            const userMessage = this.currentTranscript.trim();
            this.currentTranscript = '';
            
            // Stop listening during processing
            this.stopListening();
            this.setState('processing');
            
            // Add user message to transcript
            this.addTranscriptMessage(userMessage, 'user');
            
            try {
                // Build form data like the main form
                const formData = new FormData();
                formData.append('question', userMessage);
                formData.append('learningLevel', document.getElementById('learningLevel')?.value || 'Understand');
                
                const conversationId = document.getElementById('conversation_id')?.value;
                if (conversationId) {
                    formData.append('conversation_id', conversationId);
                }
                
                // Submit to server
                const fetchUrl = getBasePath() + '/server_mysql.php';
                const response = await fetch(fetchUrl, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Extract plain text from answer (strip HTML)
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = result.answer;
                    const plainText = tempDiv.textContent || tempDiv.innerText;
                    
                    // Update conversation ID if new
                    if (result.conversation_id) {
                        const convInput = document.getElementById('conversation_id');
                        if (convInput) convInput.value = result.conversation_id;
                    }
                    
                    // Add AI response to transcript
                    this.addTranscriptMessage(plainText.substring(0, 200) + (plainText.length > 200 ? '...' : ''), 'ai');
                    
                    // Also add to main chat (so it's not lost)
                    addMessage('user', userMessage);
                    const messageContent = `
                        ${result.answer}
                        <div class="message-footer">
                            <button class="read-aloud-btn" title="Read aloud">
                                <i class="fas fa-volume-up"></i>
                            </button>
                            <button class="copy-btn" title="Copy response">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    `;
                    addMessage('ai', messageContent, false);
                    
                    // Speak the response
                    await this.speakResponse(plainText);
                } else {
                    this.statusEl.textContent = 'Error: ' + (result.error || 'Unknown error');
                    this.addTranscriptMessage('Error: ' + (result.error || 'Unknown error'), 'ai');
                    setTimeout(() => this.startListening(), 2000);
                }
            } catch (error) {
                console.error('Voice Mode: Fetch error:', error);
                this.statusEl.textContent = 'Connection error';
                setTimeout(() => this.startListening(), 2000);
            }
        },
        
        async speakResponse(text) {
            this.setState('speaking');
            
            try {
                // Try ElevenLabs first via existing API
                const response = await fetch(getBasePath() + '/api/tts.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ text })
                });
                
                const data = await response.json();
                
                if (data.success && data.audio) {
                    // Play ElevenLabs audio
                    const audioBlob = VoiceManager.base64ToBlob(data.audio, data.contentType);
                    const audioUrl = URL.createObjectURL(audioBlob);
                    const audio = new Audio(audioUrl);
                    
                    await new Promise((resolve) => {
                        audio.onended = resolve;
                        audio.onerror = resolve;
                        audio.play();
                    });
                } else {
                    // Browser TTS fallback
                    await this.browserSpeak(text);
                }
            } catch (error) {
                console.error('Voice Mode: TTS error:', error);
                await this.browserSpeak(text);
            }
            
            // After speaking, return to listening
            if (this.isActive) {
                this.startListening();
            }
        },
        
        browserSpeak(text) {
            return new Promise((resolve) => {
                if (!window.speechSynthesis) {
                    resolve();
                    return;
                }
                
                window.speechSynthesis.cancel();
                
                const utterance = new SpeechSynthesisUtterance(text);
                utterance.rate = 1.0;
                utterance.pitch = 1.0;
                utterance.onend = resolve;
                utterance.onerror = resolve;
                
                // Try to use a better voice
                const voices = window.speechSynthesis.getVoices();
                const preferredVoice = voices.find(v => 
                    v.name.includes('Google') || 
                    v.name.includes('Microsoft') || 
                    v.name.includes('Samantha')
                );
                if (preferredVoice) utterance.voice = preferredVoice;
                
                window.speechSynthesis.speak(utterance);
            });
        },
        
        addTranscriptMessage(text, sender) {
            const msg = document.createElement('div');
            msg.className = `voice-mode-transcript-message ${sender}`;
            msg.textContent = text;
            this.transcriptEl.appendChild(msg);
            this.transcriptEl.scrollTop = this.transcriptEl.scrollHeight;
        }
    };
    
    // Initialize Voice Mode
    VoiceModeManager.init();
    window.VoiceModeManager = VoiceModeManager;

    // --- Auto-Resize Textarea Logic ---
    function setupAutoResizeTextarea() {
        if (!questionInput || questionInput.tagName !== 'TEXTAREA') return;

        const MIN_HEIGHT = 24; // Approximate height of one line (1.5rem line-height at 16px font)

        function resize() {
            // Reset height to allow shrinking
            questionInput.style.height = 'auto';
            
            // Set new height based on scrollHeight
            // We subtract padding if box-sizing is border-box, but here it seems simple enough
            const newHeight = questionInput.scrollHeight;
            questionInput.style.height = `${newHeight}px`;
            
            // Optional: Toggle scrolling if max-height is reached
            if (newHeight > 200) {
                questionInput.style.overflowY = 'auto';
            } else {
                questionInput.style.overflowY = 'hidden';
            }
        }

        questionInput.addEventListener('input', resize);
        
        // Initial resize in case there's content (e.g. from speech recognition)
        resize();
        
        // Handle Enter key
        questionInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                // Trigger form submit
                // we trigger the button click to ensure any button-bound logic runs
                submitBtn.click();
            }
        });
        
        // Hook into VoiceManager to resize after speech
        const originalOnResult = VoiceManager.recognition ? VoiceManager.recognition.onresult : null;
        if (VoiceManager.recognition) {
            VoiceManager.recognition.onresult = (event) => {
                if (originalOnResult) originalOnResult(event);
                resize(); // Resize after text insertion
            };
        }
    }
    
    // Call it immediately
    setupAutoResizeTextarea();

    // --- Typewriter Effect Function (DOM-based) ---
    function typeWriter(element, html, speed, callback) {
        // 1. Parse the HTML into a virtual DOM
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;

        const queue = [];

        // 2. Traverse and build an action queue
        function traverse(node) {
            if (node.nodeType === Node.TEXT_NODE) {
                const text = node.textContent;
                for (let char of text) {
                    queue.push({ type: 'text', content: char });
                }
            } else if (node.nodeType === Node.ELEMENT_NODE) {
                const tagName = node.tagName.toLowerCase();
                const attributes = {};
                for (let attr of node.attributes) {
                    attributes[attr.name] = attr.value;
                }
                queue.push({ type: 'open', tagName, attributes });

                for (let child of node.childNodes) {
                    traverse(child);
                }

                queue.push({ type: 'close' });
            }
        }

        for (let child of tempDiv.childNodes) {
            traverse(child);
        }

        // 3. Process the queue to animate
        let currentParent = element;
        const parentStack = [element];

        function processQueue() {
            const chunkSize = 4; // Characters per tick
            let processed = 0;

            while (queue.length > 0) {
                const item = queue[0]; // Peek

                if (item.type === 'open') {
                    queue.shift();
                    const el = document.createElement(item.tagName);
                    for (let [key, val] of Object.entries(item.attributes)) {
                        el.setAttribute(key, val);
                    }
                    currentParent.appendChild(el);
                    currentParent = el;
                    parentStack.push(el);
                    // Opening tags are instant, don't count as processed chars
                } else if (item.type === 'close') {
                    queue.shift();
                    parentStack.pop();
                    currentParent = parentStack[parentStack.length - 1];
                    // Closing tags are instant
                } else if (item.type === 'text') {
                    if (processed >= chunkSize) break; // Stop if chunk full
                    queue.shift();

                    if (currentParent.lastChild && currentParent.lastChild.nodeType === Node.TEXT_NODE) {
                        currentParent.lastChild.nodeValue += item.content;
                    } else {
                        currentParent.appendChild(document.createTextNode(item.content));
                    }
                    processed++;
                }
            }

            // Scroll to bottom
            const chatContainer = document.getElementById('chat-container');
            if (chatContainer) chatContainer.scrollTop = chatContainer.scrollHeight;

            if (queue.length > 0) {
                setTimeout(processQueue, speed);
            } else {
                if (callback) callback();
            }
        }

        processQueue();
    }

    // --- Function to add copy buttons to code blocks ---
    function addCopyButtonsToCodeBlocks(container) {
        const codeBlocks = container.querySelectorAll('pre code');

        // Apply syntax highlighting using lazy loader
        if (codeBlocks.length > 0 && window.syntaxHighlighter) {
            window.syntaxHighlighter.highlight(container).catch(err => {
                console.warn('Syntax highlighting failed:', err);
            });
        }

        // Wrap pre elements in code-block structure with header
        container.querySelectorAll('pre').forEach(pre => {
            // Don't process if already wrapped
            if (pre.parentElement?.classList.contains('code-block')) return;

            // Detect language from code element class
            const codeEl = pre.querySelector('code');
            let language = 'Code';
            if (codeEl) {
                const langClass = Array.from(codeEl.classList).find(c => c.startsWith('language-'));
                if (langClass) {
                    language = langClass.replace('language-', '').toUpperCase();
                }
            }

            // Create wrapper structure
            const wrapper = document.createElement('div');
            wrapper.className = 'code-block';

            // Create header with language and copy button
            const header = document.createElement('div');
            header.className = 'code-header';

            const langSpan = document.createElement('span');
            langSpan.className = 'code-language';
            langSpan.textContent = language;

            const copyBtn = document.createElement('button');
            copyBtn.className = 'copy-code-btn';
            copyBtn.innerHTML = '<i class="fas fa-copy"></i> Copy';
            copyBtn.addEventListener('click', () => {
                const code = pre.querySelector('code') || pre;
                const text = code.textContent;

                navigator.clipboard.writeText(text).then(() => {
                    copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                    copyBtn.classList.add('copied');
                    setTimeout(() => {
                        copyBtn.innerHTML = '<i class="fas fa-copy"></i> Copy';
                        copyBtn.classList.remove('copied');
                    }, 2000);
                }).catch(() => {
                    copyBtn.textContent = 'Failed';
                    setTimeout(() => {
                        copyBtn.innerHTML = '<i class="fas fa-copy"></i> Copy';
                    }, 2000);
                });
            });

            header.appendChild(langSpan);
            header.appendChild(copyBtn);

            // Wrap the pre element
            pre.parentNode.insertBefore(wrapper, pre);
            wrapper.appendChild(header);
            wrapper.appendChild(pre);
        });
    }

    // --- Text-to-Speech Functionality ---
    function handleReadAloud(button) {
        const messageBubble = button.closest('.message-content');
        // Clone the node to manipulate it without affecting the display
        const contentClone = messageBubble.cloneNode(true);
        // Remove the button from the clone so its text isn't read
        const footerClone = contentClone.querySelector('.message-footer');
        if (footerClone) footerClone.remove();
        const textToRead = contentClone.textContent.trim();

        if ('speechSynthesis' in window) {
            if (button.dataset.speaking === 'true') {
                speechSynthesis.cancel();
                button.dataset.speaking = 'false';
                button.innerHTML = '<i class="fas fa-volume-up"></i>';
            } else {
                // Stop any current speech
                speechSynthesis.cancel();

                // Reset all other buttons
                document.querySelectorAll('.read-aloud-btn[data-speaking="true"]').forEach(btn => {
                    btn.dataset.speaking = 'false';
                    btn.innerHTML = '<i class="fas fa-volume-up"></i>';
                });

                const utterance = new SpeechSynthesisUtterance(textToRead);
                utterance.onstart = () => {
                    button.dataset.speaking = 'true';
                    button.innerHTML = '<i class="fas fa-stop-circle"></i>';
                };
                utterance.onend = () => {
                    button.dataset.speaking = 'false';
                    button.innerHTML = '<i class="fas fa-volume-up"></i>';
                };
                speechSynthesis.speak(utterance);
            }
        }
    }

    // --- Copy-to-Clipboard Functionality ---
    function handleCopyClick(button) {
        const messageBubble = button.closest('.message-content');
        if (!messageBubble) return;
        // Clone the node to avoid modifying the DOM
        const contentClone = messageBubble.cloneNode(true);
        // Remove the footer so buttons aren't copied
        const footerClone = contentClone.querySelector('.message-footer');
        if (footerClone) footerClone.remove();
        const textToCopy = contentClone.textContent.trim();

        if (!textToCopy) return;

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(textToCopy).then(() => {
                showCopyToast('Copied to clipboard');
            }).catch(() => {
                fallbackCopyText(textToCopy);
            });
        } else {
            fallbackCopyText(textToCopy);
        }
    }

    function fallbackCopyText(text) {
        const ta = document.createElement('textarea');
        ta.value = text;
        // Avoid visual jump
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            const ok = document.execCommand('copy');
            if (ok) showCopyToast('Copied to clipboard');
            else showCopyToast('Could not copy');
        } catch (err) {
            showCopyToast('Could not copy');
        }
        document.body.removeChild(ta);
    }

    function showCopyToast(msg) {
        const toast = document.getElementById('copy-toast');
        if (!toast) return;
        toast.textContent = msg;
        toast.style.display = 'block';
        toast.style.opacity = '1';
        setTimeout(() => {
            toast.style.transition = 'opacity 250ms ease-out';
            toast.style.opacity = '0';
            setTimeout(() => { toast.style.display = 'none'; toast.style.transition = ''; }, 300);
        }, 1100);
    }

    // --- Feedback Button Handler ---
    async function handleFeedbackClick(button) {
        const rating = button.dataset.rating; // 'positive' or 'negative'
        const messageWrapper = button.closest('.message');
        const feedbackBtns = button.closest('.feedback-btns');

        // Get message index (position in chat)
        const allMessages = Array.from(chatMessages.querySelectorAll('.message.ai'));
        const messageIndex = allMessages.indexOf(messageWrapper);
        
        // Check if already rated
        if (feedbackBtns.classList.contains('rated')) {
            showCopyToast('Already rated');
            return;
        }
        
        // Visual feedback immediately
        button.classList.add('selected');
        feedbackBtns.classList.add('rated');
        
        // Show comment modal for negative feedback
        let comment = null;
        if (rating === 'negative') {
            comment = await showFeedbackModal();
        }
        
        // Submit to server
        try {
            const formData = new FormData();
            formData.append('action', 'submit_feedback');
            formData.append('type', 'message_rating');
            formData.append('rating', rating);
            formData.append('conversation_id', conversationIdInput.value || '');
            formData.append('message_index', messageIndex);
            if (comment) {
                formData.append('comment', comment);
            }
            
            const fetchUrl = window.location.pathname.split('/chat')[0] + '/server_mysql.php';
            const response = await fetch(fetchUrl, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            if (result.success) {
                showCopyToast(rating === 'positive' ? 'Thanks for the feedback!' : 'Feedback recorded');
            } else {
                console.error('Feedback error:', result.error);
                // Revert visual state
                button.classList.remove('selected');
                feedbackBtns.classList.remove('rated');
            }
        } catch (error) {
            console.error('Feedback submission failed:', error);
            button.classList.remove('selected');
            feedbackBtns.classList.remove('rated');
        }
    }
    
    // --- Feedback Comment Modal ---
    function showFeedbackModal() {
        return new Promise((resolve) => {
            // Create modal
            const modal = document.createElement('div');
            modal.className = 'feedback-modal-overlay';
            modal.innerHTML = `
                <div class="feedback-modal">
                    <h3>What went wrong?</h3>
                    <p>Your feedback helps us improve TutorMind.</p>
                    <textarea id="feedback-comment" placeholder="Tell us what could be better..." rows="4"></textarea>
                    <div class="feedback-modal-actions">
                        <button class="feedback-modal-skip">Skip</button>
                        <button class="feedback-modal-submit">Submit</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Focus textarea
            setTimeout(() => modal.querySelector('textarea').focus(), 100);
            
            // Handle actions
            modal.querySelector('.feedback-modal-skip').addEventListener('click', () => {
                document.body.removeChild(modal);
                resolve(null);
            });
            
            modal.querySelector('.feedback-modal-submit').addEventListener('click', () => {
                const comment = modal.querySelector('textarea').value.trim();
                document.body.removeChild(modal);
                resolve(comment || null);
            });
            
            // Close on overlay click
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    document.body.removeChild(modal);
                    resolve(null);
                }
            });
        });
    }

    // --- Function to show/hide the typing indicator ---
    function showTypingIndicator(show) {
        let indicator = document.getElementById('typing-indicator');
        if (show) {
            if (!indicator) {
                const indicatorWrapper = document.createElement('div');
                indicatorWrapper.id = 'typing-indicator';
                indicatorWrapper.classList.add('message', 'ai');
                indicatorWrapper.innerHTML = `
                    <div class="message-avatar">🤖</div>
                    <div class="message-content">
                        <div class="typing-indicator">
                            <span></span><span></span><span></span>
                        </div>
                    </div>
                `;
                chatMessages.appendChild(indicatorWrapper);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        } else {
            if (indicator) {
                indicator.remove();
            }
        }
    }

    // --- File Attachment Manager ---
    class FileAttachmentManager {
        constructor(fileInput, previewArea, maxFiles = 10) {
            this.fileInput = fileInput;
            this.previewArea = previewArea;
            this.maxFiles = maxFiles;
            this.files = []; // Store File objects here

            this.init();
        }

        init() {
            // Handle file selection via input
            this.fileInput.addEventListener('change', (e) => {
                this.handleFiles(Array.from(e.target.files));
                // Do NOT clear the input here, as it wipes out the files we just set in updateFileInput()
                // The input's files are managed by updateFileInput() which is called by handleFiles()
            });
        }

        handleFiles(newFiles) {
            const totalFiles = this.files.length + newFiles.length;
            if (totalFiles > this.maxFiles) {
                alert(`You can only upload a maximum of ${this.maxFiles} files.`);
                return;
            }

            // Add new files to our array
            this.files = [...this.files, ...newFiles];
            this.updatePreview();
            this.updateFileInput();
        }

        removeFile(index) {
            this.files.splice(index, 1);
            this.updatePreview();
            this.updateFileInput();
        }

        clear() {
            this.files = [];
            this.updatePreview();
            this.updateFileInput();
        }

        updateFileInput() {
            // Create a new DataTransfer object to update the file input's files property
            const dataTransfer = new DataTransfer();
            this.files.forEach(file => dataTransfer.items.add(file));
            this.fileInput.files = dataTransfer.files;
        }

        updatePreview() {
            this.previewArea.innerHTML = '';

            if (this.files.length === 0) {
                this.previewArea.classList.remove('has-files');
                return;
            }
            this.previewArea.classList.add('has-files');

            const list = document.createElement('div');
            list.className = 'attachment-preview-list';

            this.files.forEach((file, index) => {
                const card = document.createElement('div');
                card.className = 'attachment-card';

                // Thumbnail
                const thumbnail = document.createElement('div');
                thumbnail.className = 'attachment-thumbnail';

                if (file.type.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.src = URL.createObjectURL(file);
                    img.onload = () => URL.revokeObjectURL(img.src); // Free memory
                    thumbnail.appendChild(img);
                } else {
                    const icon = document.createElement('i');
                    icon.className = this.getFileIcon(file.type);
                    thumbnail.appendChild(icon);
                }

                // Info (Filename)
                const info = document.createElement('div');
                info.className = 'attachment-info';
                info.textContent = file.name;
                info.title = file.name;

                // Remove Button
                const removeBtn = document.createElement('button');
                removeBtn.className = 'remove-attachment-btn';
                removeBtn.innerHTML = '<i class="fas fa-times"></i>';
                removeBtn.title = 'Remove file';
                removeBtn.onclick = (e) => {
                    e.preventDefault(); // Prevent form submit
                    this.removeFile(index);
                };

                card.appendChild(thumbnail);
                card.appendChild(info);
                card.appendChild(removeBtn);
                list.appendChild(card);
            });

            this.previewArea.appendChild(list);
        }

        getFileIcon(mimeType) {
            if (mimeType.includes('pdf')) return 'fas fa-file-pdf';
            if (mimeType.includes('word') || mimeType.includes('document')) return 'fas fa-file-word';
            if (mimeType.includes('presentation') || mimeType.includes('powerpoint')) return 'fas fa-file-powerpoint';
            if (mimeType.includes('text')) return 'fas fa-file-alt';
            return 'fas fa-file';
        }
    }

    // Initialize the manager
    const attachmentManager = new FileAttachmentManager(fileInput, attachmentPreviewArea);

    // --- Enter Key Handler (Fix for file upload issue) ---
    // Explicitly handle Enter key to ensure form submission works correctly with files
    questionInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            // Trigger the same submit handler
            tutorForm.requestSubmit(submitBtn);
        }
    });

    // --- Handle form submission ---
    tutorForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        let question = questionInput.value.trim();
        if (!question && attachmentManager.files.length === 0) return;

        // --- Quote Reference Integration ---
        // If there's a quoted reference, prepend it to the question
        const quoteText = window.getQuoteReference ? window.getQuoteReference() : null;
        if (quoteText) {
            question = `Regarding this passage: "${quoteText}"\n\n${question || 'Please explain this in simpler terms.'}`;
            if (window.clearQuoteReference) window.clearQuoteReference();
        }

        // --- Session Context Integration ---
        // Extract context from the user's message
        if (window.sessionContextManager) {
            window.sessionContextManager.extractContextFromMessage(question);
            window.sessionContextManager.incrementMessageCount();
        }

        // --- The Fix: Capture FormData and explicitly add files ---
        // Using FormData(tutorForm) may not reliably pick up files set via DataTransfer.
        // We explicitly add files from the attachment manager to guarantee they're included.
        const formData = new FormData(tutorForm);
        
        // Override the question field with the potentially modified question (with quote)
        formData.set('question', question);
        // Remove any potentially empty/stale attachment entries from native serialization
        formData.delete('attachment[]');
        
        // Explicitly add files from the manager
        attachmentManager.files.forEach(file => {
            formData.append('attachment[]', file);
        });

        // Add session context to the request
        if (window.sessionContextManager) {
            const sessionContext = window.sessionContextManager.getSystemPromptContext();
            if (sessionContext.goal) {
                formData.append('session_goal', sessionContext.goal);
            }
            if (Object.keys(sessionContext.context).length > 0) {
                formData.append('session_context', JSON.stringify(sessionContext.context));
            }
        }

        // --- Build and display the user's message ---
        let userMessageHtml = '';
        // If files are attached, create a small display for them.
        if (attachmentManager.files.length > 0) {
            userMessageHtml += `<div class="message-attachments-grid" style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 8px;">`;
            attachmentManager.files.forEach(file => {
                const iconClass = attachmentManager.getFileIcon(file.type);
                userMessageHtml += `
                    <div class="message-attachment-pill" style="background: rgba(123, 63, 242, 0.1); padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; display: flex; align-items: center; gap: 6px;">
                        <i class="${iconClass}"></i>
                        <span style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${file.name}</span>
                    </div>
                `;
            });
            userMessageHtml += `</div>`;
        }
        const escapedQuestion = question.replace(/</g, "&lt;").replace(/>/g, "&gt;");
        addMessage('user', userMessageHtml + escapedQuestion);

        // Hide welcome screen on first message and show title
        if (welcomeScreen) welcomeScreen.style.display = 'none';
        document.body.classList.remove('chat-empty');
        if (conversationTitleEl) conversationTitleEl.style.display = 'block';

        // Hide Quick Start overlay if visible
        const quickStartOverlay = document.getElementById('quick-start-overlay');
        if (quickStartOverlay && !quickStartOverlay.classList.contains('hidden')) {
            quickStartOverlay.classList.add('hidden');
        }

        // Clear inputs immediately for better UX
        questionInput.value = '';
        attachmentManager.clear(); // Clear the manager state and UI

        try {
            // Disable form and show typing indicator
            submitBtn.disabled = true;
            questionInput.disabled = true;
            showTypingIndicator(true);
            // Use getBasePath helper for consistent URL construction
            const fetchUrl = getBasePath() + '/server_mysql.php';
            console.log('Fetching from:', fetchUrl);
            const response = await fetch(fetchUrl, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

            // Check if response is actually JSON before parsing
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Expected JSON but got:', text.substring(0, 200));
                throw new Error('Server returned non-JSON response. Check server logs.');
            }

            const result = await response.json();

            if (result.success) {
                // Add the AI message with a "Read Aloud" button
                const messageContent = `
                    ${result.answer}
                    <div class="message-footer">
                        <button class="read-aloud-btn" title="Read aloud">
                            <i class="fas fa-volume-up"></i>
                        </button>
                        <button class="copy-btn" title="Copy response">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                `;
                addMessage('ai', messageContent, true);

                // Event listeners are now attached in finalizeMessage after typing is complete

                // Handle conversation ID and title updates
                if (result.conversation_id) {
                    conversationIdInput.value = result.conversation_id;

                    // Update URL if we aren't already on this conversation's page
                    if (!window.location.href.includes(`chat/${result.conversation_id}`)) {
                        const baseUrl = window.location.pathname.split('/chat')[0];
                        history.pushState({}, '', `${baseUrl}/chat/${result.conversation_id}`);
                    }

                    // If server generated a title, update the conversation in the sidebar
                    if (result.generated_title) {
                        console.log('AI generated title:', result.generated_title);
                        // Refresh chat history to show the new conversation with AI-generated title
                        await loadChatHistory();
                        highlightActiveConversation(result.conversation_id);
                    } else {
                        // Not a new chat, just highlight if needed
                        const existingLink = chatHistoryContainer.querySelector(`[data-conversation-id="${result.conversation_id}"]`);
                        if (!existingLink) {
                            await loadChatHistory();
                            highlightActiveConversation(result.conversation_id);
                        }
                    }
                }

                // Update session context with server-side progress data (hybrid progress with milestones)
                if (result.progress && window.sessionContextManager) {
                    window.sessionContextManager.updateFromServerProgress(result.progress);
                    console.log('Progress updated:', result.progress.percentage + '%',
                        `(${result.progress.milestonesCompleted}/${result.progress.milestonesTotal} milestones)`);
                }
            } else {
                addMessage('ai', `<p style="color: red;">Error: ${result.error || 'An unknown error occurred.'}</p>`);
            }
        } catch (error) {
            console.error('Fetch Error:', error);
            addMessage('ai', `<p style="color: red;">Sorry, I couldn't connect to the server. Please try again later.</p>`);
        } finally {
            showTypingIndicator(false);
            submitBtn.disabled = false;
            questionInput.disabled = false;
            questionInput.focus();
        }
    });



    // --- Handle "New Chat" button ---
    newChatBtn.addEventListener('click', () => {
        // Update URL to /chat
        const baseUrl = window.location.pathname.split('/chat')[0];
        history.pushState({}, '', `${baseUrl}/chat`);

        // Clear old messages and show the welcome screen
        chatMessages.innerHTML = '';
        chatMessages.appendChild(welcomeScreen);
        welcomeScreen.style.display = 'flex';
        conversationIdInput.value = '';
        highlightActiveConversation(null);

        // Reset conversation title to default and hide it
        if (conversationTitleEl) {
            conversationTitleEl.textContent = 'TutorMind';
            conversationTitleEl.style.display = 'none';
        }
        document.body.classList.add('chat-empty');

        // Clear session context
        if (window.sessionContextManager) {
            window.sessionContextManager.clear();
        }
    });

    // Initialize
    loadChatHistory();
    loadPersonalizedSuggestions();
    typeWelcomeGreeting();

    // --- Functions ---

    function typeWelcomeGreeting() {
        const greetingElement = document.getElementById('welcome-greeting');
        if (!greetingElement) return;

        const username = greetingElement.dataset.username || 'Friend';
        const hour = new Date().getHours();
        let timeGreeting = "Hello";

        if (hour >= 5 && hour < 12) {
            timeGreeting = "Good morning";
        } else if (hour >= 12 && hour < 18) {
            timeGreeting = "Good afternoon";
        } else {
            timeGreeting = "Good evening";
        }

        const greetings = [
            timeGreeting,
            "Hello",
            "Hi there",
            "Welcome back",
            "Good to see you",
            "Greetings",
            "Hey",
            "Ready when you are",
            "Ready to lock in",
        ];

        const randomGreeting = greetings[Math.floor(Math.random() * greetings.length)];
        const fullText = `${randomGreeting}, ${username}!`;

        // Custom variable-speed typing for a smoother, more natural feel
        let i = 0;
        greetingElement.textContent = ''; // Clear initial content

        function typeChar() {
            if (i < fullText.length) {
                greetingElement.textContent += fullText.charAt(i);
                i++;
                // Random delay between 50ms and 150ms for natural variation
                const delay = Math.random() * 100 + 50;
                setTimeout(typeChar, delay);
            } else {
                // Typing done - hide the cursor after a brief pause
                setTimeout(() => {
                    greetingElement.classList.add('typing-done');
                }, 1500);
            }
        }

        typeChar();
    }

    async function loadPersonalizedSuggestions() {
        // Only load if we are on the welcome screen
        const welcomeScreen = document.getElementById('welcome-screen');
        if (!welcomeScreen || welcomeScreen.style.display === 'none') return;

        try {
            const fetchUrl = getBasePath() + '/server_mysql.php';
            const response = await fetch(`${fetchUrl}?action=generate_suggestions`);
            const data = await response.json();

            if (data.success && data.suggestions) {
                const pills = document.querySelectorAll('.suggestion-pill');

                // Update pills based on the returned keys
                pills.forEach(pill => {
                    const iconText = pill.querySelector('.pill-icon').nextSibling.textContent.trim().toLowerCase();
                    let key = '';

                    if (iconText.includes('explain')) key = 'explain';
                    else if (iconText.includes('write')) key = 'write';
                    else if (iconText.includes('build')) key = 'build';
                    else if (iconText.includes('research')) key = 'research';

                    if (key && data.suggestions[key]) {
                        pill.dataset.prompt = data.suggestions[key];
                        // Optional: Add a subtle animation to show update
                        pill.style.opacity = '0.5';
                        setTimeout(() => pill.style.opacity = '1', 300);
                    }
                });
            }
        } catch (error) {
            console.error('Failed to load suggestions:', error);
        }
    }

    // --- Function to load chat history from server ---
    async function loadChatHistory() {
        try {
            const fetchUrl = getBasePath() + '/server_mysql.php';
            const response = await fetch(`${fetchUrl}?action=history`);
            const result = await response.json();

            if (result.success && Array.isArray(result.history)) {
                chatHistoryContainer.innerHTML = ''; // Clear existing history
                result.history.forEach(convo => {
                    const historyItem = document.createElement('div');
                    historyItem.classList.add('history-item', 'flex', 'justify-between', 'items-center');

                    const link = document.createElement('a');
                    link.href = '#';
                    link.textContent = convo.title;
                    link.title = convo.title; // Add title attribute for full text on hover
                    link.dataset.conversationId = convo.id;
                    link.classList.add('flex-1', 'truncate'); // 'truncate' class will be styled by new CSS
                    link.addEventListener('click', (e) => {
                        e.preventDefault();
                        loadConversation(convo.id);
                    });

                    const deleteBtn = document.createElement('button');
                    deleteBtn.innerHTML = '<i class="fas fa-trash-alt"></i>';
                    deleteBtn.classList.add('text-gray-400', 'hover:text-white', 'ml-2');
                    deleteBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        deleteConversation(convo.id);
                    });

                    const editBtn = document.createElement('button');
                    editBtn.innerHTML = '<i class="fas fa-pencil-alt"></i>';
                    editBtn.classList.add('text-gray-400', 'hover:text-white', 'ml-2');
                    editBtn.title = 'Rename';
                    editBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        enableRename(historyItem, convo.id, convo.title);
                    });

                    historyItem.appendChild(link);
                    historyItem.appendChild(editBtn);
                    historyItem.appendChild(deleteBtn);
                    chatHistoryContainer.appendChild(historyItem);
                });
            }
        } catch (error) {
            console.error('Error loading chat history:', error);
        }
    }

    // --- Function to load a specific conversation ---
    async function loadConversation(id) {
        // Get base path for URL construction
        const basePath = getBasePath();
        // Update URL to /chat/{id}
        history.pushState({}, '', `${basePath}/chat/${id}`);

        try {
            // Use absolute path for fetch
            const fetchUrl = basePath + '/server_mysql.php';
            const response = await fetch(`${fetchUrl}?action=get_conversation&id=${id}`, {
                method: 'GET',
                credentials: 'include', // Ensure cookies are sent
                headers: {
                    'Accept': 'application/json'
                }
            });

            // Check if response is OK
            if (!response.ok) {
                console.error('Server returned error:', response.status, response.statusText);
                throw new Error(`Server error: ${response.status}`);
            }

            const text = await response.text();
            console.log('Raw response:', text.substring(0, 200)); // Debug log

            let result;
            try {
                result = JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse JSON:', text);
                throw new Error('Invalid server response');
            }

            if (result.success) {
                // Hide welcome screen and clear any existing messages
                // Hide welcome screen and clear any existing messages
                welcomeScreen.style.display = 'none';
                document.body.classList.remove('chat-empty');
                chatMessages.innerHTML = '';
                conversationIdInput.value = id;
                result.conversation.chat_history.forEach(item => {
                    // The user's message in history includes file context, which we don't want to re-display.
                    // We'll just show the question part.
                    if (item.role === 'user') {
                        let userMessageHtml = '';
                        let userQuestion = '';

                        // Check if parts is an array (new format) or object (old format/single text)
                        const parts = Array.isArray(item.parts) ? item.parts : [item.parts];

                        // Build attachment pills container
                        const attachmentPills = [];

                        parts.forEach(part => {
                            if (part.inline_data) {
                                // Check if the data was stripped for performance
                                if (part.inline_data._removed || !part.inline_data.data) {
                                    // Show a lightweight placeholder pill instead of the full image
                                    attachmentPills.push(`
                                        <div class="message-attachment-pill" style="background: rgba(123, 63, 242, 0.1); padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 6px; margin-right: 4px; margin-bottom: 4px;">
                                            <i class="fas fa-image"></i>
                                            <span>Image Attached</span>
                                        </div>
                                    `);
                                } else {
                                    // Full image data available - show thumbnail
                                    const mimeType = part.inline_data.mime_type;
                                    const base64Data = part.inline_data.data;
                                    attachmentPills.push(`
                                        <div class="history-image-container" style="width: 100px; height: 100px; border-radius: 8px; overflow: hidden; border: 1px solid var(--border);">
                                            <img src="data:${mimeType};base64,${base64Data}" style="width: 100%; height: 100%; object-fit: cover;" alt="Attached Image">
                                        </div>
                                    `);
                                }
                            } else if (part.text) {
                                if (part.text.startsWith("Context from uploaded file")) {
                                    // Document attachment - show pill ONLY, hide the context
                                    const match = part.text.match(/Context from uploaded file '([^']+)':/);
                                    const filename = match ? match[1] : "Document";
                                    attachmentPills.push(`
                                        <div class="message-attachment-pill" style="background: rgba(123, 63, 242, 0.1); padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 6px; margin-right: 4px; margin-bottom: 4px;">
                                            <i class="fas fa-file-alt"></i>
                                            <span style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${filename}</span>
                                        </div>
                                    `);
                                    // DO NOT add this to userQuestion - it's context, not the question
                                } else {
                                    // This is the actual user question
                                    userQuestion += part.text;
                                }
                            }
                        });

                        // Assemble the final message HTML
                        if (attachmentPills.length > 0) {
                            userMessageHtml += '<div class="message-attachments-grid" style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 8px;">';
                            userMessageHtml += attachmentPills.join('');
                            userMessageHtml += '</div>';
                        }

                        // Add the user's question (escaped for safety)
                        addMessage('user', userMessageHtml + userQuestion.replace(/</g, "&lt;").replace(/>/g, "&gt;"));
                    } else {
                        // Add the AI message with a "Read Aloud" button when loading from history
                        const messageContent = `
                            ${item.parts[0].text}
                            <div class="message-footer">
                                    <button class="read-aloud-btn" title="Read aloud">
                                        <i class="fas fa-volume-up"></i>
                                    </button>
                                    <button class="copy-btn" title="Copy response">
                                        <i class="fas fa-copy"></i>
                                    </button>
                            </div>
                        `;
                        addMessage('ai', messageContent);
                        // Attach event listener to the new button
                        const newButton = chatMessages.lastElementChild.querySelector('.read-aloud-btn');
                        if (newButton) newButton.addEventListener('click', handleReadAloud);
                        const newCopyButton = chatMessages.lastElementChild.querySelector('.copy-btn');
                        if (newCopyButton) newCopyButton.addEventListener('click', handleCopyClick);
                    }
                });
                highlightActiveConversation(id);

                // Update the conversation title in the header and show it
                if (conversationTitleEl && result.conversation.title) {
                    conversationTitleEl.textContent = result.conversation.title;
                    conversationTitleEl.style.display = 'block';
                }

            }
        } catch (error) {
            console.error('Error loading conversation:', error);
        }
    }

    // --- Function to delete a conversation ---
    async function deleteConversation(id) {
        if (!confirm('Are you sure you want to delete this chat?')) return;

        try {
            const fetchUrl = window.location.pathname.split('/chat')[0] + '/server_mysql.php';
            const response = await fetch(`${fetchUrl}?action=delete_conversation&id=${id}`);
            const result = await response.json();

            if (result.success) {
                if (conversationIdInput.value === id) {
                    newChatBtn.click(); // Start a new chat if the active one was deleted
                }
                loadChatHistory(); // Refresh the history list
            }
        } catch (error) {
            console.error('Error deleting conversation:', error);
        }
    }

    // --- Function to enable renaming a conversation ---
    function enableRename(historyItem, id, currentTitle) {
        const link = historyItem.querySelector('a');
        const buttons = historyItem.querySelectorAll('button');

        // Hide link and buttons
        link.style.display = 'none';
        buttons.forEach(btn => btn.style.display = 'none');

        const input = document.createElement('input');
        input.type = 'text';
        input.value = currentTitle;
        input.className = 'bg-gray-700 text-white w-full rounded px-2 py-1 focus:outline-none focus:ring-2 focus:ring-indigo-500';

        const finishEditing = async () => {
            const newTitle = input.value.trim();

            // Restore UI
            link.style.display = '';
            buttons.forEach(btn => btn.style.display = '');
            input.remove();

            if (newTitle && newTitle !== currentTitle) {
                link.textContent = newTitle; // Optimistic update
                link.title = newTitle; // Update tooltip as well

                // Send update to server
                try {
                    const formData = new FormData();
                    formData.append('action', 'rename_conversation');
                    formData.append('id', id);
                    formData.append('title', newTitle);

                    const fetchUrl = window.location.pathname.split('/chat')[0] + '/server_mysql.php';
                    const response = await fetch(fetchUrl, {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    if (!result.success) {
                        alert('Error renaming conversation: ' + result.error);
                        link.textContent = currentTitle; // Revert on error
                        link.title = currentTitle;
                    }
                } catch (error) {
                    console.error('Rename failed:', error);
                    alert('Could not connect to the server to rename.');
                    link.textContent = currentTitle; // Revert on error
                    link.title = currentTitle;
                }
            }
        };

        input.addEventListener('blur', finishEditing);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                input.blur(); // Trigger the blur event to save
            } else if (e.key === 'Escape') {
                input.removeEventListener('blur', finishEditing); // Prevent saving on escape
                input.blur();
            }
        });

        historyItem.prepend(input);
        input.focus();
        input.select();
    }

    // --- Function to highlight the active chat in the sidebar ---
    function highlightActiveConversation(id) {
        const allLinks = chatHistoryContainer.querySelectorAll('a');
        allLinks.forEach(link => link.classList.remove('active'));
        if (id) {
            const activeLink = chatHistoryContainer.querySelector(`[data-conversation-id="${id}"]`);
            if (activeLink) activeLink.classList.add('active');
        }
    }

    // --- Expose functions to global scope for SSR onclick handlers ---
    window.loadConversation = loadConversation;
    window.deleteConversation = deleteConversation;
    window.enableRename = enableRename;

    // --- Sidebar Toggle (Desktop & Mobile) ---
    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');

    if (sidebar) {
        const overlay = document.getElementById('sidebar-overlay');

        // Restore state from localStorage on load (Desktop only)
        if (window.innerWidth > 768) {
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
                document.body.classList.add('sidebar-collapsed');
            }
        }

        // Internal Sidebar Toggle (Desktop: Collapse, Mobile: Close)
        if (menuToggle) {
            menuToggle.addEventListener('click', () => {
                if (window.innerWidth > 768) {
                    // Desktop: Toggle collapse
                    sidebar.classList.toggle('collapsed');
                    document.body.classList.toggle('sidebar-collapsed');
                    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
                } else {
                    // Mobile: Close sidebar
                    sidebar.classList.remove('open');
                    overlay.classList.add('hidden');
                }
            });
        }

        // Header Toggle (Mobile Only: Open)
        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', () => {
                sidebar.classList.add('open');
                overlay.classList.remove('hidden');
            });
        }

        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.add('hidden');
        });

        // Handle resize to reset states if needed
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('open');
                overlay.classList.add('hidden');
                // Restore collapsed state
                const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                sidebar.classList.toggle('collapsed', isCollapsed);
                document.body.classList.toggle('sidebar-collapsed', isCollapsed);
            } else {
                sidebar.classList.remove('collapsed');
                document.body.classList.remove('sidebar-collapsed');
            }
        });
    }

    // --- User Account Dropdown Logic ---
    if (userAccountTrigger && userAccountMenu) {
        userAccountTrigger.addEventListener('click', (e) => {
            e.stopPropagation(); // Prevent the document click listener from closing it immediately
            const isHidden = userAccountMenu.classList.toggle('hidden');
            if (userAccountChevron) {
                userAccountChevron.classList.toggle('rotate-180', !isHidden);
            }
        });

        // Close dropdown if clicking outside
        document.addEventListener('click', (e) => {
            const dropdown = document.getElementById('user-account-dropdown');
            if (dropdown && !dropdown.contains(e.target)) {
                userAccountMenu.classList.add('hidden');
            }
        });
    }

    // --- Suggestion Pill Click Logic ---
    document.querySelectorAll('.suggestion-card, .suggestion-pill').forEach(btn => {
        btn.addEventListener('click', () => {
            const promptText = btn.dataset.prompt;
            questionInput.value = promptText;
            questionInput.focus();
        });
        // Add keyboard accessibility
        btn.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                btn.click();
            }
        });
    });


    // --- Settings Modal Trigger ---
    const openSettingsBtn = document.getElementById('open-settings-btn');
    if (openSettingsBtn) {
        openSettingsBtn.addEventListener('click', (e) => {
            e.preventDefault();
            // The settingsManager is created and attached to the window by settings.js
            if (window.settingsManager) {
                window.settingsManager.open();
            }
        });
    }

    // --- General Feedback Modal Trigger ---
    const openFeedbackBtn = document.getElementById('open-feedback-btn');
    if (openFeedbackBtn) {
        openFeedbackBtn.addEventListener('click', (e) => {
            e.preventDefault();
            showGeneralFeedbackModal();
        });
    }

    // --- General Feedback Modal ---
    async function showGeneralFeedbackModal() {
        const modal = document.createElement('div');
        modal.className = 'feedback-modal-overlay';
        modal.innerHTML = `
            <div class="feedback-modal general-feedback-modal">
                <h3><i class="fas fa-comment-dots"></i> Send Feedback</h3>
                <p>Help us improve TutorMind! Share your thoughts, suggestions, or report issues.</p>
                
                <div class="feedback-category-select">
                    <label>Category</label>
                    <select id="general-feedback-category">
                        <option value="suggestion">💡 Suggestion</option>
                        <option value="bug">🐛 Bug Report</option>
                        <option value="feature">✨ Feature Request</option>
                        <option value="other">📝 Other</option>
                    </select>
                </div>
                
                <div class="feedback-rating-select">
                    <label>How would you rate your experience?</label>
                    <div class="rating-options">
                        <button type="button" class="rating-option" data-rating="positive" title="Great">😊</button>
                        <button type="button" class="rating-option" data-rating="neutral" title="Okay">😐</button>
                        <button type="button" class="rating-option" data-rating="negative" title="Needs improvement">😞</button>
                    </div>
                </div>
                
                <textarea id="general-feedback-comment" placeholder="Tell us more..." rows="4"></textarea>
                
                <div class="feedback-modal-actions">
                    <button class="feedback-modal-skip">Cancel</button>
                    <button class="feedback-modal-submit">Send Feedback</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        
        // Handle rating selection
        let selectedRating = 'neutral';
        modal.querySelectorAll('.rating-option').forEach(btn => {
            btn.addEventListener('click', () => {
                modal.querySelectorAll('.rating-option').forEach(b => b.classList.remove('selected'));
                btn.classList.add('selected');
                selectedRating = btn.dataset.rating;
            });
        });
        
        // Focus textarea
        setTimeout(() => modal.querySelector('textarea').focus(), 100);
        
        // Handle cancel
        modal.querySelector('.feedback-modal-skip').addEventListener('click', () => {
            document.body.removeChild(modal);
        });
        
        // Handle submit
        modal.querySelector('.feedback-modal-submit').addEventListener('click', async () => {
            const category = modal.querySelector('#general-feedback-category').value;
            const comment = modal.querySelector('#general-feedback-comment').value.trim();
            
            if (!comment) {
                modal.querySelector('textarea').focus();
                modal.querySelector('textarea').placeholder = 'Please enter your feedback...';
                return;
            }
            
            // Submit to server
            try {
                const formData = new FormData();
                formData.append('action', 'submit_feedback');
                formData.append('type', 'general');
                formData.append('rating', selectedRating);
                formData.append('comment', comment);
                formData.append('category', category);
                formData.append('page_url', window.location.href);
                
                const fetchUrl = window.location.pathname.split('/chat')[0] + '/server_mysql.php';
                const response = await fetch(fetchUrl, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                document.body.removeChild(modal);
                
                if (result.success) {
                    showCopyToast('Thank you for your feedback!');
                } else {
                    showCopyToast('Could not submit feedback');
                }
            } catch (error) {
                console.error('Feedback submission failed:', error);
                document.body.removeChild(modal);
                showCopyToast('Could not submit feedback');
            }
        });
        
        // Close on overlay click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                document.body.removeChild(modal);
            }
        });
    }

    // --- Tools Dropdown Menu ---
    const toolsBtn = document.getElementById('tools-btn');
    const toolsMenu = document.getElementById('tools-menu');

    if (toolsBtn && toolsMenu) {
        // Toggle menu on button click
        toolsBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isHidden = toolsMenu.classList.toggle('hidden');
            toolsBtn.setAttribute('aria-expanded', !isHidden);
        });

        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!toolsMenu.contains(e.target) && !toolsBtn.contains(e.target)) {
                toolsMenu.classList.add('hidden');
                toolsBtn.setAttribute('aria-expanded', 'false');
            }
        });

        // Handle menu item clicks
        const toolsMenuItems = toolsMenu.querySelectorAll('.tools-menu-item');

        // Prompt templates for each goal
        const promptTemplates = {
            homework_help: "Help me with my homework on ",
            test_prep: "I have a test coming up on ",
            explore: "I want to learn more about ",
            practice: "Give me practice problems on "
        };

        toolsMenuItems.forEach(item => {
            item.addEventListener('click', () => {
                const goal = item.dataset.goal;

                // Set the session goal using SessionContextManager
                if (window.sessionContextManager) {
                    window.sessionContextManager.create(goal).then(session => {
                        if (session.id) {
                            conversationIdInput.value = session.id;
                        }
                    });
                }

                // Insert prompt template into input
                const template = promptTemplates[goal] || '';
                questionInput.value = template;

                // Close the menu
                toolsMenu.classList.add('hidden');

                // Focus the input and place cursor at the end
                questionInput.focus();
                questionInput.setSelectionRange(template.length, template.length);
            });
        });
    }
});