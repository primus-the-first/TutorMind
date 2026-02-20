/**
 * TutorMind Chat Interface - Enhanced Features
 * Includes: Dark mode, mobile sidebar, voice input, auto-resize, and more
 */

class TutorMindChat {
    constructor() {
        // Use same localStorage key as settings.js for consistency
        this.darkMode = localStorage.getItem('darkMode') === 'enabled' || document.body.classList.contains('dark-mode');
        this.sidebarCollapsed = window.innerWidth < 768;
        this.voiceRecognition = null;

        this.init();
    }

    init() {
        console.log('🧠 TutorMind Chat initialized');

        // Don't apply theme here - let settings.js handle it from database
        // Just sync our internal state with whatever is already applied
        this.darkMode = document.body.classList.contains('dark-mode');
        this.updateDarkModeIcon();

        this.setupEventListeners();
        this.initializeFeatures();
        this.setupMobileHandling();
    }

    /* ==================== SETUP ==================== */
    setupEventListeners() {
        // Dark mode toggle
        const darkModeBtn = document.getElementById('dark-mode-toggle');
        if (darkModeBtn) {
            darkModeBtn.addEventListener('click', () => this.toggleDarkMode());
        }

        // Sidebar toggle (mobile)
        const sidebarToggle = document.getElementById('sidebar-toggle');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => this.toggleSidebar());
        }

        // Auto-resize textarea
        const textarea = document.querySelector('.input-box textarea');
        if (textarea) {
            textarea.addEventListener('input', () => this.autoResizeTextarea(textarea));
            
            // Send on Enter (Shift+Enter for newline)
            textarea.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });
        }

        // Send button
        const sendBtn = document.querySelector('.send-btn');
        if (sendBtn) {
            sendBtn.addEventListener('click', () => this.sendMessage());
        }

        // Voice input
        const voiceBtn = document.querySelector('[data-action="voice"]');
        if (voiceBtn) {
            voiceBtn.addEventListener('click', () => this.startVoiceInput());
        }

        // Image upload
        const imageBtn = document.querySelector('[data-action="image"]');
        if (imageBtn) {
            imageBtn.addEventListener('click', () => this.handleImageUpload());
        }

        // Suggestion chips
        document.querySelectorAll('.suggestion-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                const textarea = document.querySelector('.input-box textarea');
                if (textarea) {
                    textarea.value = chip.textContent.trim();
                    this.autoResizeTextarea(textarea);
                    textarea.focus();
                }
            });
        });

        // Copy code buttons
        document.querySelectorAll('.copy-code-btn').forEach(btn => {
            btn.addEventListener('click', (e) => this.copyCode(e.target));
        });

        // New chat button
        const newChatBtn = document.querySelector('.new-chat-btn');
        if (newChatBtn) {
            newChatBtn.addEventListener('click', () => this.startNewChat());
        }
    }

    setupMobileHandling() {
        // Close sidebar when clicking outside on mobile
        if (window.innerWidth < 768) {
            document.addEventListener('click', (e) => {
                const sidebar = document.querySelector('.chat-sidebar');
                const toggle = document.getElementById('sidebar-toggle');
                
                if (sidebar && !sidebar.contains(e.target) && e.target !== toggle && !this.sidebarCollapsed) {
                    this.toggleSidebar();
                }
            });
        }

        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 768) {
                const sidebar = document.querySelector('.chat-sidebar');
                if (sidebar) {
                    sidebar.classList.remove('collapsed');
                    this.sidebarCollapsed = false;
                }
            }
        });
    }

    initializeFeatures() {
        // Scroll to bottom on load
        this.scrollToBottom();

        // Initialize syntax highlighting if messages exist
        if (typeof hljs !== 'undefined') {
            document.querySelectorAll('pre code').forEach((block) => {
                hljs.highlightElement(block);
            });
        }

        // Initialize MathJax if available
        if (typeof MathJax !== 'undefined') {
            MathJax.typesetPromise?.();
        }
    }

    /* ==================== DARK MODE ==================== */
    toggleDarkMode() {
        this.darkMode = !this.darkMode;
        document.body.classList.toggle('dark-mode', this.darkMode);
        localStorage.setItem('darkMode', this.darkMode ? 'enabled' : 'disabled');

        // Update header button icon
        this.updateDarkModeIcon();

        // Sync with sidebar checkbox toggle
        const sidebarToggle = document.getElementById('darkModeToggle');
        if (sidebarToggle) {
            sidebarToggle.checked = this.darkMode;
        }

        // Save to database via settings manager if available
        if (window.settingsManager) {
            window.settingsManager.debouncedSave({ dark_mode: this.darkMode });
        }

        console.log(`🌓 Dark mode: ${this.darkMode ? 'enabled' : 'disabled'}`);
    }

    updateDarkModeIcon() {
        const icon = document.querySelector('#dark-mode-toggle i');
        if (icon) {
            icon.className = this.darkMode ? 'fas fa-sun' : 'fas fa-moon';
        }
    }

    /* ==================== SIDEBAR ==================== */
    toggleSidebar() {
        const sidebar = document.querySelector('.chat-sidebar');
        if (sidebar) {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            sidebar.classList.toggle('collapsed');
            
            console.log(`📱 Sidebar: ${this.sidebarCollapsed ? 'collapsed' : 'expanded'}`);
        }
    }

    /* ==================== MESSAGE HANDLING ==================== */
    async sendMessage() {
        const textarea = document.querySelector('.input-box textarea');
        const message = textarea?.value.trim();
        
        if (!message) return;

        console.log('📤 Sending message:', message);

        // Add user message to UI
        this.addMessage('user', message);
        
        // Clear input
        textarea.value = '';
        this.autoResizeTextarea(textarea);

        // Show typing indicator
        this.showTypingIndicator();

        // Send to backend (integrate with existing API)
        try {
            const response = await this.sendToBackend(message);
            this.hideTypingIndicator();
            
            if (response && response.text) {
                this.addMessage('ai', response.text);
            }
        } catch (error) {
            console.error('❌ Error sending message:', error);
            this.hideTypingIndicator();
            this.addMessage('ai', 'Sorry, I encountered an error. Please try again.');
        }
    }

    addMessage(role, content) {
        const messagesContainer = document.querySelector('.chat-messages');
        if (!messagesContainer) return;

        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${role}`;
        
        const avatar = role === 'user' ? '👤' : '🤖';
        
        messageDiv.innerHTML = `
            <div class="message-avatar">${avatar}</div>
            <div class="message-content">
                ${this.formatMessage(content)}
            </div>
        `;

        messagesContainer.appendChild(messageDiv);
        this.scrollToBottom();

        // Re-initialize syntax highlighting and math
        if (typeof hljs !== 'undefined') {
            messageDiv.querySelectorAll('pre code').forEach(block => {
                hljs.highlightElement(block);
            });
        }

        if (typeof MathJax !== 'undefined') {
            MathJax.typesetPromise?.([messageDiv]);
        }
    }

    formatMessage(content) {
        // Simple markdown-like formatting
        // In production, use your existing Parsedown or marked.js
        let formatted = content;
        
        // Bold
        formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        
        // Inline code
        formatted = formatted.replace(/`(.*?)`/g, '<code>$1</code>');
        
        // Line breaks
        formatted = formatted.replace(/\n/g, '<br>');
        
        return `<p>${formatted}</p>`;
    }

    showTypingIndicator() {
        const messagesContainer = document.querySelector('.chat-messages');
        if (!messagesContainer) return;

        const typingDiv = document.createElement('div');
        typingDiv.className = 'message ai typing-message';
        typingDiv.id = 'typing-indicator';
        typingDiv.innerHTML = `
            <div class="message-avatar">🤖</div>
            <div class="message-content">
                <div class="typing-indicator">
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                </div>
            </div>
        `;

        messagesContainer.appendChild(typingDiv);
        this.scrollToBottom();
    }

    hideTypingIndicator() {
        const indicator = document.getElementById('typing-indicator');
        if (indicator) {
            indicator.remove();
        }
    }

    async sendToBackend(message) {
        // TODO: Integrate with your existing server_mysql.php
        // This is a placeholder - replace with your actual API call
        
        // Example integration:
        // const formData = new FormData();
        // formData.append('message', message);
        // formData.append('conversation_id', this.currentConversationId);
        // 
        // const response = await fetch('server_mysql.php', {
        //     method: 'POST',
        //     body: formData
        // });
        // 
        // return await response.json();

        // Mock response for demo
        return new Promise(resolve => {
            setTimeout(() => {
                resolve({
                    text: "I'm processing your message. Please integrate this with your backend API!"
                });
            }, 1500);
        });
    }

    /* ==================== VOICE INPUT ==================== */
    startVoiceInput() {
        if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
            alert('Voice input is not supported in your browser. Please try Chrome or Edge.');
            return;
        }

        // Show voice modal
        this.showVoiceModal();

        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        this.voiceRecognition = new SpeechRecognition();
        this.voiceRecognition.continuous = false;
        this.voiceRecognition.interimResults = false;
        this.voiceRecognition.lang = 'en-US';

        this.voiceRecognition.onresult = (event) => {
            const transcript = event.results[0][0].transcript;
            const textarea = document.querySelector('.input-box textarea');
            if (textarea) {
                textarea.value = transcript;
                this.autoResizeTextarea(textarea);
            }
            this.hideVoiceModal();
            console.log('🎤 Voice input:', transcript);
        };

        this.voiceRecognition.onerror = (event) => {
            console.error('Voice recognition error:', event.error);
            this.hideVoiceModal();
            alert('Voice recognition error: ' + event.error);
        };

        this.voiceRecognition.onend = () => {
            this.hideVoiceModal();
        };

        this.voiceRecognition.start();
        console.log('🎤 Voice input started');
    }

    showVoiceModal() {
        const modal = document.createElement('div');
        modal.className = 'voice-modal';
        modal.id = 'voice-modal';
        modal.innerHTML = `
            <div class="voice-modal-content">
                <div class="voice-animation">
                    <i class="fas fa-microphone"></i>
                </div>
                <h3>Listening...</h3>
                <p style="color: var(--text-secondary); margin-top: 0.5rem;">Speak now</p>
                <button class="btn-text mt-2" onclick="window.chatApp.stopVoiceInput()">Cancel</button>
            </div>
        `;
        document.body.appendChild(modal);
    }

    hideVoiceModal() {
        const modal = document.getElementById('voice-modal');
        if (modal) {
            modal.remove();
        }
    }

    stopVoiceInput() {
        if (this.voiceRecognition) {
            this.voiceRecognition.stop();
        }
        this.hideVoiceModal();
    }

    /* ==================== IMAGE UPLOAD ==================== */
    handleImageUpload() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        
        input.onchange = (e) => {
            const file = e.target.files[0];
            if (file) {
                console.log('📷 Image selected:', file.name);
                
                // Preview or upload logic here
                // For now, just log
                alert(`Image upload feature coming soon!\nSelected: ${file.name}`);
            }
        };
        
        input.click();
    }

    /* ==================== CODE COPY ==================== */
    copyCode(button) {
        const codeBlock = button.closest('.code-block');
        const code = codeBlock.querySelector('pre').textContent;
        
        navigator.clipboard.writeText(code).then(() => {
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i> Copied!';
            button.classList.add('copied');
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.classList.remove('copied');
            }, 2000);
            
            console.log('📋 Code copied to clipboard');
        }).catch(err => {
            console.error('Failed to copy code:', err);
            alert('Failed to copy code to clipboard');
        });
    }

    /* ==================== UTILITIES ==================== */
    autoResizeTextarea(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';
    }

    scrollToBottom() {
        const messagesContainer = document.querySelector('.chat-messages');
        if (messagesContainer) {
            // Only auto-scroll if user is near the bottom (not reading earlier messages)
            const distanceFromBottom = messagesContainer.scrollHeight - messagesContainer.scrollTop - messagesContainer.clientHeight;
            if (distanceFromBottom < 100) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        }
    }

    startNewChat() {
        if (confirm('Start a new conversation? Your current chat will be saved.')) {
            window.location.href = 'tutor_mysql.php';
            console.log('🆕 Starting new chat');
        }
    }
}

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', () => {
    window.chatApp = new TutorMindChat();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TutorMindChat;
}
