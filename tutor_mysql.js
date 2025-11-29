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
        const existingMessages = chatMessages.querySelectorAll('.chat-message');
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
            
            // Save to database via settings manager
            if (window.settingsManager) {
                await window.settingsManager.debouncedSave({ dark_mode: isDark });
            }
        });
    }


    // --- Function to add a message to the chat window ---
    function addMessage(sender, messageHtml, animate = false) {
        const messageWrapper = document.createElement('div');
        messageWrapper.classList.add('chat-message', `${sender}-message`);

        const messageBubble = document.createElement('div');
        messageBubble.classList.add('message-bubble');
        
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
        const bubbles = chatMessages.querySelectorAll('.message-bubble');
        bubbles.forEach(bubble => {
            const wrapper = bubble.closest('.chat-message');
            const isAi = wrapper.classList.contains('ai-message');
            
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
    });

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
            if(chatContainer) chatContainer.scrollTop = chatContainer.scrollHeight;
            
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
        
        // Apply syntax highlighting
        if (window.hljs) {
            codeBlocks.forEach(block => {
                hljs.highlightElement(block);
            });
        }
        
        // Add copy buttons
        container.querySelectorAll('pre').forEach(pre => {
            // Don't add button if it already exists
            if (pre.querySelector('.copy-code-btn')) return;
            
            const button = document.createElement('button');
            button.className = 'copy-code-btn';
            button.textContent = 'Copy';
            button.addEventListener('click', () => {
                const code = pre.querySelector('code') || pre;
                const text = code.textContent;
                
                navigator.clipboard.writeText(text).then(() => {
                    button.textContent = 'Copied!';
                    setTimeout(() => {
                        button.textContent = 'Copy';
                    }, 2000);
                }).catch(() => {
                    button.textContent = 'Failed';
                    setTimeout(() => {
                        button.textContent = 'Copy';
                    }, 2000);
                });
            });
            
            pre.appendChild(button);
        });
    }

    // --- Text-to-Speech Functionality ---
    function handleReadAloud(button) {
        const messageBubble = button.closest('.message-bubble');
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
        const messageBubble = button.closest('.message-bubble');
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

    // --- Function to show/hide the typing indicator ---
    function showTypingIndicator(show) {
        let indicator = document.getElementById('typing-indicator');
        if (show) {
            if (!indicator) {
                const indicatorWrapper = document.createElement('div');
                indicatorWrapper.id = 'typing-indicator';
                indicatorWrapper.classList.add('chat-message', 'ai-message');
                indicatorWrapper.innerHTML = `
                    <div class="message-bubble">
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

    // --- Handle form submission ---
    tutorForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const question = questionInput.value.trim();
        if (!question && attachmentManager.files.length === 0) return;

        // --- The Fix: Capture FormData immediately ---
        // This ensures we have the question and file data before any other operations.
        const formData = new FormData(tutorForm);

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

        // Clear inputs immediately for better UX
        questionInput.value = '';
        attachmentManager.clear(); // Clear the manager state and UI

        try {
            // Disable form and show typing indicator
            submitBtn.disabled = true;
            questionInput.disabled = true;
            showTypingIndicator(true);
            // Use absolute path for fetch
            const fetchUrl = window.location.pathname.split('/chat')[0] + '/server_mysql.php';
            const response = await fetch(fetchUrl, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

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
            }
        }
        
        typeChar();
    }

    async function loadPersonalizedSuggestions() {
        // Only load if we are on the welcome screen
        const welcomeScreen = document.getElementById('welcome-screen');
        if (!welcomeScreen || welcomeScreen.style.display === 'none') return;

        try {
            const fetchUrl = window.location.pathname.split('/chat')[0] + '/server_mysql.php';
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
            const fetchUrl = window.location.pathname.split('/chat')[0] + '/server_mysql.php';
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
        // Update URL to /chat/{id}
        const baseUrl = window.location.pathname.split('/chat')[0];
        history.pushState({}, '', `${baseUrl}/chat/${id}`);

        try {
            // Use absolute path for fetch to avoid issues when current URL is /chat/123
            const fetchUrl = window.location.pathname.split('/chat')[0] + '/server_mysql.php';
            const response = await fetch(`${fetchUrl}?action=get_conversation&id=${id}`);
            const text = await response.text();
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

                    const response = await fetch('server_mysql.php', {
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

    // --- Sidebar Toggle (Desktop & Mobile) ---
    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    
    if (sidebar) {
        const overlay = document.getElementById('sidebar-overlay');
        
        // Restore state from localStorage on load (Desktop only)
        if (window.innerWidth > 768) {
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
            }
        }

        // Internal Sidebar Toggle (Desktop: Collapse, Mobile: Close)
        if (menuToggle) {
            menuToggle.addEventListener('click', () => {
                if (window.innerWidth > 768) {
                    // Desktop: Toggle collapse
                    sidebar.classList.toggle('collapsed');
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
            } else {
                sidebar.classList.remove('collapsed');
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
});