/**
 * Quick Start Overlay Manager
 * 
 * Manages the Quick Start overlay that appears when users start a new chat.
 * Provides personalized suggestions, continue learning, and quick action buttons.
 */

class QuickStartManager {
    constructor() {
        this.overlay = document.getElementById('quick-start-overlay');
        this.continueCard = document.getElementById('continue-learning-card');
        this.testAlert = document.getElementById('upcoming-test-alert');
        this.dismissBtn = document.getElementById('quick-start-dismiss');
        this.quickActionCards = document.querySelectorAll('.quick-action-card');
        
        this.sessionData = null;
        this.testData = null;
        this.dismissed = false;
        
        this.init();
    }
    
    init() {
        if (!this.overlay) return;
        
        // Check if overlay should be shown
        const hasActiveChat = document.getElementById('conversation_id')?.value;
        if (hasActiveChat) {
            this.hide();
            return;
        }
        
        // Load recent activity
        this.loadRecentActivity();
        
        // Bind event listeners
        this.bindEvents();
        
        // Animate entrance if visible
        if (!this.overlay.classList.contains('hidden')) {
            this.animateEntrance();
        }
    }
    
    bindEvents() {
        // Quick action cards
        this.quickActionCards.forEach(card => {
            card.addEventListener('click', () => {
                const goal = card.dataset.goal;
                this.handleQuickActionClick(goal);
            });
        });
        
        // Continue learning card
        if (this.continueCard) {
            this.continueCard.addEventListener('click', () => {
                this.handleContinueLearningClick();
            });
        }
        
        // Test alert prepare button
        const prepareBtn = document.getElementById('test-alert-prepare-btn');
        if (prepareBtn) {
            prepareBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.handleTestPrepClick();
            });
        }
        
        // Test alert card (also clickable)
        if (this.testAlert) {
            this.testAlert.addEventListener('click', () => {
                this.handleTestPrepClick();
            });
        }
        
        // Dismiss button
        if (this.dismissBtn) {
            this.dismissBtn.addEventListener('click', () => {
                this.dismiss();
            });
        }
        
        // Also dismiss when user starts typing
        const questionInput = document.getElementById('question');
        if (questionInput) {
            questionInput.addEventListener('focus', () => {
                if (!this.dismissed) {
                    this.dismiss();
                }
            });
        }
    }
    
    async loadRecentActivity() {
        try {
            const response = await fetch('api/session_context.php?action=recent_activity');
            const data = await response.json();
            
            if (data.success) {
                // Show continue learning if incomplete session exists
                if (data.recentSessions && data.recentSessions.length > 0) {
                    this.sessionData = data.recentSessions[0];
                    this.renderContinueLearning(this.sessionData);
                }
                
                // Show upcoming test alert
                if (data.upcomingTests && data.upcomingTests.length > 0) {
                    this.testData = data.upcomingTests[0];
                    this.renderUpcomingTest(this.testData);
                }
            }
        } catch (error) {
            console.error('Failed to load recent activity:', error);
        }
    }
    
    renderContinueLearning(session) {
        if (!this.continueCard || !session) return;
        
        const topicEl = document.getElementById('continue-card-topic');
        if (topicEl) {
            const context = session.context_data || {};
            const topic = context.topic || session.title || 'Previous Session';
            const progress = session.progress || 0;
            topicEl.textContent = `${topic} • ${progress}% complete`;
        }
        
        this.continueCard.classList.remove('hidden');
        this.continueCard.dataset.sessionId = session.id;
        
        // Animate in
        if (typeof gsap !== 'undefined') {
            gsap.fromTo(this.continueCard, 
                { opacity: 0, y: 10 },
                { opacity: 1, y: 0, duration: 0.4, delay: 0.3 }
            );
        }
    }
    
    renderUpcomingTest(test) {
        if (!this.testAlert || !test) return;
        
        const infoEl = document.getElementById('test-alert-info');
        if (infoEl) {
            const subject = test.subject || 'Test';
            const days = test.daysRemaining;
            const daysText = days === 0 ? 'today' : days === 1 ? 'in 1 day' : `in ${days} days`;
            infoEl.textContent = `${subject} ${daysText}`;
        }
        
        this.testAlert.classList.remove('hidden');
        
        // Animate in
        if (typeof gsap !== 'undefined') {
            gsap.fromTo(this.testAlert, 
                { opacity: 0, y: 10 },
                { opacity: 1, y: 0, duration: 0.4, delay: 0.4 }
            );
        }
    }
    
    animateEntrance() {
        if (typeof gsap === 'undefined') return;
        
        const content = this.overlay.querySelector('.quick-start-content');
        const orb = this.overlay.querySelector('.quick-start-orb');
        const title = this.overlay.querySelector('.quick-start-title');
        const subtitle = this.overlay.querySelector('.quick-start-subtitle');
        const cards = this.overlay.querySelectorAll('.quick-action-card');
        const dismiss = this.overlay.querySelector('.quick-start-dismiss');
        
        // Timeline for orchestrated animation
        const tl = gsap.timeline();
        
        // Orb fades in and pulses
        if (orb) {
            tl.fromTo(orb, 
                { opacity: 0, scale: 0.5 },
                { opacity: 1, scale: 1, duration: 0.6, ease: 'back.out(1.5)' },
                0
            );
        }
        
        // Title slides up and fades
        if (title) {
            tl.fromTo(title,
                { opacity: 0, y: 20 },
                { opacity: 1, y: 0, duration: 0.5 },
                0.2
            );
        }
        
        // Subtitle
        if (subtitle) {
            tl.fromTo(subtitle,
                { opacity: 0, y: 15 },
                { opacity: 1, y: 0, duration: 0.4 },
                0.35
            );
        }
        
        // Cards stagger in
        if (cards.length > 0) {
            tl.fromTo(cards,
                { opacity: 0, y: 20, scale: 0.95 },
                { 
                    opacity: 1, 
                    y: 0, 
                    scale: 1, 
                    duration: 0.4, 
                    stagger: 0.08,
                    ease: 'power2.out'
                },
                0.5
            );
        }
        
        // Dismiss button
        if (dismiss) {
            tl.fromTo(dismiss,
                { opacity: 0 },
                { opacity: 1, duration: 0.3 },
                0.9
            );
        }
    }
    
    async handleQuickActionClick(goal) {
        // Create session with goal using SessionContextManager
        try {
            let conversationId = null;
            
            // Use SessionContextManager if available
            if (window.sessionContextManager) {
                const session = await window.sessionContextManager.create(goal);
                conversationId = session.id;
            } else {
                // Fallback to direct API call
                const response = await fetch('api/session_context.php?action=create_session', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        session_goal: goal,
                        title: this.getGoalTitle(goal)
                    })
                });
                
                const data = await response.json();
                if (data.success && data.conversation_id) {
                    conversationId = data.conversation_id;
                }
            }
            
            if (conversationId) {
                // Update hidden input
                const conversationInput = document.getElementById('conversation_id');
                if (conversationInput) {
                    conversationInput.value = conversationId;
                }
                
                // Store session context globally
                window.currentSessionGoal = goal;
                window.currentConversationId = conversationId;
                
                // Update session context manager with conversation ID
                if (window.sessionContextManager) {
                    window.sessionContextManager.setConversationId(conversationId);
                }
                
                // Dismiss overlay
                this.dismiss();
                
                // Trigger AI opening message
                this.addOpeningMessage(goal);
            }
        } catch (error) {
            console.error('Failed to create session:', error);
            this.dismiss();
        }
    }
    
    getGoalTitle(goal) {
        const titles = {
            homework_help: 'Homework Help',
            test_prep: 'Test Preparation',
            explore: 'Exploring Topic',
            practice: 'Practice Session'
        };
        return titles[goal] || 'New Chat';
    }
    
    addOpeningMessage(goal) {
        const messages = {
            homework_help: "I'm ready to help with your homework! 📚\n\nWhat subject and topic are you working on? You can also just describe the problem or upload a photo if that's easier.",
            
            test_prep: "Let's prepare you for your test! 🎯\n\nTell me:\n• What subject?\n• What topics will it cover?\n• When is it?",
            
            explore: "Let's explore something interesting! 💡\n\nWhat topic are you curious about? It can be anything - from your subjects or something completely new.",
            
            practice: "Let's get some practice in! ✏️\n\nWhat subject and topic do you want to practice? I can give you problems at any difficulty level."
        };
        
        const messageText = messages[goal] || "How can I help you today?";
        
        // Use the existing addMessage function if available
        if (typeof addMessage === 'function') {
            addMessage('ai', messageText);
        } else {
            // Fallback: directly add to chat
            this.appendAIMessage(messageText);
        }
        
        // Hide welcome screen and show chat
        const welcomeScreen = document.getElementById('welcome-screen');
        if (welcomeScreen) {
            welcomeScreen.style.display = 'none';
        }
        
        document.body.classList.remove('chat-empty');
    }
    
    appendAIMessage(text) {
        const chatContainer = document.getElementById('chat-container');
        if (!chatContainer) return;
        
        // Convert newlines to <br> and bullets to proper list items
        let html = text
            .replace(/•/g, '&bull;')
            .replace(/\n/g, '<br>');
        
        const messageDiv = document.createElement('div');
        messageDiv.className = 'chat-message ai-message';
        messageDiv.innerHTML = `
            <div class="message-bubble">
                <p>${html}</p>
            </div>
        `;
        
        chatContainer.appendChild(messageDiv);
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }
    
    handleContinueLearningClick() {
        if (!this.sessionData) return;
        
        const sessionId = this.sessionData.id;
        
        // Use existing loadConversation function if available
        if (typeof loadConversation === 'function') {
            loadConversation(sessionId);
        } else {
            // Navigate to conversation
            window.location.href = `chat/${sessionId}`;
        }
        
        this.dismiss();
    }
    
    handleTestPrepClick() {
        if (!this.testData) {
            // No test data, just start generic test prep
            this.handleQuickActionClick('test_prep');
            return;
        }
        
        // Create test prep session with pre-filled context
        this.createTestPrepSession(this.testData);
    }
    
    async createTestPrepSession(testData) {
        try {
            const response = await fetch('api/session_context.php?action=create_session', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    session_goal: 'test_prep',
                    title: `${testData.subject} Test Prep`,
                    context_data: {
                        subject: testData.subject,
                        topics: testData.topics,
                        testDate: testData.testDate,
                        daysRemaining: testData.daysRemaining
                    }
                })
            });
            
            const data = await response.json();
            
            if (data.success && data.conversation_id) {
                window.currentSessionGoal = 'test_prep';
                window.currentConversationId = data.conversation_id;
                
                const conversationInput = document.getElementById('conversation_id');
                if (conversationInput) {
                    conversationInput.value = data.conversation_id;
                }
                
                this.dismiss();
                
                // Custom opening message for scheduled test
                const daysText = testData.daysRemaining === 0 ? 'today' : 
                                 testData.daysRemaining === 1 ? 'tomorrow' : 
                                 `in ${testData.daysRemaining} days`;
                
                const topic = Array.isArray(testData.topics) ? testData.topics.join(', ') : testData.topics;
                const message = `Let's prepare for your ${testData.subject} test on ${topic} ${daysText}! 📝\n\nHow much time do you have to study right now?`;
                
                if (typeof addMessage === 'function') {
                    addMessage('ai', message);
                } else {
                    this.appendAIMessage(message);
                }
                
                // Hide welcome screen
                const welcomeScreen = document.getElementById('welcome-screen');
                if (welcomeScreen) {
                    welcomeScreen.style.display = 'none';
                }
                document.body.classList.remove('chat-empty');
            }
        } catch (error) {
            console.error('Failed to create test prep session:', error);
        }
    }
    
    dismiss() {
        if (this.dismissed) return;
        this.dismissed = true;
        
        if (typeof gsap !== 'undefined') {
            gsap.to(this.overlay, {
                opacity: 0,
                duration: 0.3,
                onComplete: () => {
                    this.overlay.classList.add('hidden');
                    this.overlay.style.opacity = '';
                }
            });
        } else {
            this.overlay.classList.add('hidden');
        }
        
        // Focus the input
        const questionInput = document.getElementById('question');
        if (questionInput) {
            setTimeout(() => questionInput.focus(), 100);
        }
    }
    
    show(chatAreaOnly = false) {
        this.dismissed = false;
        this.overlay.classList.remove('hidden');
        
        // Toggle between full-screen and chat-area-only modes
        if (chatAreaOnly) {
            this.overlay.classList.add('chat-area-only');
        } else {
            this.overlay.classList.remove('chat-area-only');
        }
        
        this.animateEntrance();
    }
    
    hide() {
        this.overlay.classList.add('hidden');
        this.dismissed = true;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.quickStartManager = new QuickStartManager();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = QuickStartManager;
}
