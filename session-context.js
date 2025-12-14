/**
 * Session Context Manager
 * 
 * Manages session state and context throughout conversations.
 * Handles session creation, context updates, and progress tracking.
 */

class SessionContextManager {
    constructor() {
        this.currentSession = null;
        this.saveTimeout = null;
        this.SAVE_DEBOUNCE_MS = 1000;
        
        // Load session from storage if available
        this.loadFromStorage();
    }
    
    /**
     * Create a new session with a learning goal
     * @param {string} goal - 'homework_help' | 'test_prep' | 'explore' | 'practice' | null
     * @param {object} initialContext - Optional initial context data
     * @returns {Promise<object>} The created session
     */
    async create(goal, initialContext = {}) {
        try {
            const response = await fetch('api/session_context.php?action=create_session', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    session_goal: goal,
                    title: this.getGoalTitle(goal),
                    context_data: initialContext
                })
            });
            
            const data = await response.json();
            
            if (data.success && data.conversation_id) {
                this.currentSession = {
                    id: data.conversation_id,
                    goal: goal,
                    context: initialContext,
                    progress: 0,
                    completed: false,
                    messageCount: 0,
                    startTime: new Date().toISOString()
                };
                
                this.saveToStorage();
                return this.currentSession;
            }
            
            throw new Error(data.error || 'Failed to create session');
        } catch (error) {
            console.error('Session creation failed:', error);
            
            // Fallback: create local session without server sync
            this.currentSession = {
                id: null,
                goal: goal,
                context: initialContext,
                progress: 0,
                completed: false,
                messageCount: 0,
                startTime: new Date().toISOString()
            };
            
            this.saveToStorage();
            return this.currentSession;
        }
    }
    
    /**
     * Load an existing session by ID
     * @param {string} sessionId - The conversation ID
     */
    async load(sessionId) {
        try {
            const response = await fetch(`server_mysql.php?action=get_conversation&id=${sessionId}`);
            const data = await response.json();
            
            if (data.success && data.conversation) {
                this.currentSession = {
                    id: sessionId,
                    goal: data.conversation.session_goal || null,
                    context: JSON.parse(data.conversation.context_data || '{}'),
                    progress: data.conversation.progress || 0,
                    completed: data.conversation.completed || false,
                    messageCount: data.conversation.chat_history?.length || 0,
                    title: data.conversation.title
                };
                
                this.saveToStorage();
                return this.currentSession;
            }
        } catch (error) {
            console.error('Failed to load session:', error);
        }
        
        return null;
    }
    
    /**
     * Update session context (debounced API save)
     * @param {object} contextData - Context fields to update
     */
    updateContext(contextData) {
        if (!this.currentSession) return;
        
        // Merge with existing context
        this.currentSession.context = {
            ...this.currentSession.context,
            ...contextData
        };
        
        this.saveToStorage();
        this.debouncedSave();
    }
    
    /**
     * Update session progress
     * @param {number} progress - Progress percentage (0-100)
     */
    updateProgress(progress) {
        if (!this.currentSession) return;
        
        this.currentSession.progress = Math.max(0, Math.min(100, progress));
        this.currentSession.completed = progress >= 95;
        
        this.saveToStorage();
        this.debouncedSave();
    }
    
    /**
     * Increment message count and update progress for practice mode
     */
    incrementMessageCount() {
        if (!this.currentSession) return;
        
        this.currentSession.messageCount = (this.currentSession.messageCount || 0) + 1;
        
        // Auto-calculate progress based on goal
        if (this.currentSession.goal === 'practice') {
            const targetProblems = this.currentSession.context.problemCount || 10;
            const problemsSolved = Math.floor(this.currentSession.messageCount / 2); // Rough estimate
            const progress = Math.min(100, (problemsSolved / targetProblems) * 100);
            this.currentSession.progress = progress;
        } else if (this.currentSession.goal === 'explore') {
            // Exploration progress based on conversation depth
            const progress = Math.min(100, (this.currentSession.messageCount / 20) * 100);
            this.currentSession.progress = progress;
        }
        
        this.saveToStorage();
    }
    
    /**
     * Extract context from user message using keyword detection
     * @param {string} message - The user's message
     */
    extractContextFromMessage(message) {
        if (!this.currentSession) return;
        
        const lowerMessage = message.toLowerCase();
        const contextUpdates = {};
        
        // Subject detection
        const subjectPatterns = {
            'Mathematics': /\b(math|algebra|calculus|geometry|trigonometry|statistics|arithmetic)\b/i,
            'Physics': /\b(physics|mechanics|thermodynamics|electricity|magnetism|optics|waves)\b/i,
            'Chemistry': /\b(chemistry|chemical|organic|inorganic|biochemistry|elements|reactions)\b/i,
            'Biology': /\b(biology|cells|genetics|evolution|ecology|anatomy|physiology)\b/i,
            'Computer Science': /\b(programming|coding|computer|algorithm|software|data structure|python|javascript|java)\b/i,
            'English': /\b(english|grammar|writing|essay|literature|poetry|reading)\b/i,
            'History': /\b(history|historical|ancient|medieval|modern|world war|civilization)\b/i
        };
        
        for (const [subject, pattern] of Object.entries(subjectPatterns)) {
            if (pattern.test(lowerMessage)) {
                contextUpdates.subject = subject;
                break;
            }
        }
        
        // Topic extraction (simple heuristic - look for "about X" or "on X")
        const topicPatterns = [
            /(?:about|on|studying|learning|help with|working on)\s+([a-z\s]+?)(?:\.|,|$|\?|!)/i,
            /(?:topic is|subject is)\s+([a-z\s]+?)(?:\.|,|$|\?|!)/i
        ];
        
        for (const pattern of topicPatterns) {
            const match = lowerMessage.match(pattern);
            if (match && match[1]) {
                const topic = match[1].trim();
                if (topic.length > 2 && topic.length < 50) {
                    contextUpdates.topic = this.capitalizeFirstLetter(topic);
                    break;
                }
            }
        }
        
        // Time/urgency detection for test prep
        if (this.currentSession.goal === 'test_prep') {
            const urgencyPatterns = [
                { pattern: /\btomorrow\b/i, days: 1 },
                { pattern: /\btoday\b/i, days: 0 },
                { pattern: /\bin\s+(\d+)\s+days?\b/i, daysMatch: true },
                { pattern: /\bnext\s+week\b/i, days: 7 }
            ];
            
            for (const { pattern, days, daysMatch } of urgencyPatterns) {
                const match = message.match(pattern);
                if (match) {
                    const daysRemaining = daysMatch ? parseInt(match[1]) : days;
                    const testDate = new Date();
                    testDate.setDate(testDate.getDate() + daysRemaining);
                    contextUpdates.testDate = testDate.toISOString();
                    contextUpdates.daysRemaining = daysRemaining;
                    break;
                }
            }
        }
        
        // Difficulty detection for practice mode
        if (this.currentSession.goal === 'practice') {
            if (/\beasy\b|\bbeginner\b|\bsimple\b/i.test(lowerMessage)) {
                contextUpdates.difficulty = 'easy';
            } else if (/\bhard\b|\bdifficult\b|\bchallenging\b|\badvanced\b/i.test(lowerMessage)) {
                contextUpdates.difficulty = 'hard';
            } else if (/\bmedium\b|\bmoderate\b|\bintermediate\b/i.test(lowerMessage)) {
                contextUpdates.difficulty = 'medium';
            }
            
            // Problem count detection
            const countMatch = lowerMessage.match(/(\d+)\s*(?:problems?|questions?)/i);
            if (countMatch) {
                contextUpdates.problemCount = parseInt(countMatch[1]);
            }
        }
        
        // Apply updates if any were found
        if (Object.keys(contextUpdates).length > 0) {
            this.updateContext(contextUpdates);
        }
        
        return contextUpdates;
    }
    
    /**
     * Get context for AI system prompt
     * @returns {object} Context object for building system prompt
     */
    getSystemPromptContext() {
        if (!this.currentSession) {
            return { goal: null, context: {} };
        }
        
        return {
            goal: this.currentSession.goal,
            context: this.currentSession.context,
            progress: this.currentSession.progress,
            messageCount: this.currentSession.messageCount
        };
    }
    
    /**
     * Debounced save to server
     */
    debouncedSave() {
        if (this.saveTimeout) {
            clearTimeout(this.saveTimeout);
        }
        
        this.saveTimeout = setTimeout(() => {
            this.saveToServer();
        }, this.SAVE_DEBOUNCE_MS);
    }
    
    /**
     * Save current session to server
     */
    async saveToServer() {
        if (!this.currentSession || !this.currentSession.id) return;
        
        try {
            // Update context
            await fetch('api/session_context.php?action=update_context', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    conversation_id: this.currentSession.id,
                    session_goal: this.currentSession.goal,
                    context_data: this.currentSession.context
                })
            });
            
            // Update progress
            await fetch('api/session_context.php?action=update_progress', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    conversation_id: this.currentSession.id,
                    progress: this.currentSession.progress
                })
            });
        } catch (error) {
            console.error('Failed to save session to server:', error);
        }
    }
    
    /**
     * Save to local storage for resilience
     */
    saveToStorage() {
        if (!this.currentSession) return;
        
        try {
            localStorage.setItem('tutormind_session', JSON.stringify(this.currentSession));
        } catch (error) {
            console.warn('Failed to save session to localStorage:', error);
        }
    }
    
    /**
     * Load from local storage
     */
    loadFromStorage() {
        try {
            const stored = localStorage.getItem('tutormind_session');
            if (stored) {
                this.currentSession = JSON.parse(stored);
            }
        } catch (error) {
            console.warn('Failed to load session from localStorage:', error);
        }
    }
    
    /**
     * Clear current session
     */
    clear() {
        this.currentSession = null;
        try {
            localStorage.removeItem('tutormind_session');
        } catch (error) {
            // Ignore storage errors
        }
    }
    
    /**
     * Get human-readable title for goal
     */
    getGoalTitle(goal) {
        const titles = {
            homework_help: 'Homework Help',
            test_prep: 'Test Preparation',
            explore: 'Exploring Topic',
            practice: 'Practice Session'
        };
        return titles[goal] || 'New Chat';
    }
    
    /**
     * Capitalize first letter of string
     */
    capitalizeFirstLetter(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    /**
     * Get current session
     */
    getSession() {
        return this.currentSession;
    }
    
    /**
     * Set conversation ID (for when session is created by quick-start)
     */
    setConversationId(id) {
        if (this.currentSession) {
            this.currentSession.id = id;
            this.saveToStorage();
        }
    }
}

// Create global instance
window.sessionContextManager = new SessionContextManager();

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SessionContextManager;
}
