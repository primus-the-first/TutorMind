/**
 * Quick Start Overlay Manager
 * 
 * Manages the Quick Start overlay that appears when users start a new chat.
 * Provides personalized suggestions, continue learning, and quick action buttons.
 */

class QuickStartManager {
    constructor() {
        // We no longer have a separate overlay wrapper
        this.welcomeScreen = document.getElementById('welcome-screen');
        // The container of the quick start content (optional to track)
        this.contentContainer = document.querySelector('.quick-start-content');
        
        this.continueCard = document.getElementById('continue-learning-card');
        this.testAlert = document.getElementById('upcoming-test-alert');
        this.quickActionCards = document.querySelectorAll('.quick-action-card');

        this.sessionData = null;
        this.testData = null;

        this.init();
    }

    init() {
        // If no welcome screen, likely not on the correct page
        if (!this.welcomeScreen) return;

        // Check if we are active (welcome screen visible)
        // If conversation already exists, welcome screen is hidden by CSS/PHP
        
        // Load recent activity
        this.loadRecentActivity();

        // Bind event listeners
        this.bindEvents();

        // Animate entrance? 
        // We can still animate the content inside welcome screen
        this.animateEntrance();
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
    }

    async loadRecentActivity() {
        try {
            const response = await fetch('api/session_context.php?action=recent_activity');
            const data = await response.json();

            if (data.success) {
                // Check if we have a recent active session to continue
                // Logic: if last session was < 24h ago and not marked 'completed'
                if (data.lastSession) {
                    this.sessionData = data.lastSession;
                    this.renderContinueCard(data.lastSession);
                }

                // Check for upcoming test
                if (data.upcomingTest) {
                    this.testData = data.upcomingTest;
                    this.renderUpcomingTest(data.upcomingTest);
                }
            }
        } catch (error) {
            // Silently fail - just don't show dynamic cards
            console.warn('Could not load recent activity', error);
        }
    }

    renderContinueCard(session) {
        if (!this.continueCard || !session) return;

        const topicEl = document.getElementById('continue-card-topic');
        if (topicEl) {
            const context = session.context_data || {};
            const topic = context.topic || session.title || 'Previous Session';

            // Show milestones covered instead of percentage
            const milestonesCompleted = context.milestonesCompleted || 0;
            const milestonesTotal = context.milestonesTotal || 0;

            if (milestonesTotal > 0) {
                // Show milestone progress: "Topic • 3 of 7 milestones covered"
                topicEl.textContent = `${topic} • ${milestonesCompleted} of ${milestonesTotal} covered`;
            } else {
                // Fallback for sessions without milestones yet
                const msgCount = context.messageCount || session.message_count || 0;
                if (msgCount > 0) {
                    topicEl.textContent = `${topic} • ${msgCount} ${msgCount === 1 ? 'message' : 'messages'}`;
                } else {
                    topicEl.textContent = topic;
                }
            }
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
        if (typeof gsap === 'undefined' || !this.contentContainer) return;

        const content = this.contentContainer; // Use the container we found
        const orb = content.querySelector('.quick-start-orb');
        const title = content.querySelector('.quick-start-title');
        const subtitle = content.querySelector('.quick-start-subtitle');
        const cards = content.querySelectorAll('.quick-action-card');

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
        // Just focus the input
        const questionInput = document.getElementById('question');
        if (questionInput) {
            setTimeout(() => questionInput.focus(), 100);
        }
    }

    show(chatAreaOnly = false) {
        // No longer applicable in the same way, but ensuring welcome screen is visible
        if (this.welcomeScreen) {
            this.welcomeScreen.style.display = 'flex';
            this.animateEntrance();
        }
    }

    hide() {
        if (this.welcomeScreen) {
            this.welcomeScreen.style.display = 'none';
        }
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
