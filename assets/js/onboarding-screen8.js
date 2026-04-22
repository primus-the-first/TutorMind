/**
 * Screen 8: First Lesson
 * Interactive "Mini-Lesson" to demonstrate AI capabilities
 */

OnboardingWizard.prototype.initScreen8FirstLesson = function() {
  console.log('📖 Initializing First Lesson Screen');
  
  // Initialize state
  this.lessonState = {
    messages: [],
    currentProblem: null,
    isComplete: false,
    typing: false
  };
  
  // Clear previous chat if any
  const chatContainer = document.getElementById('lesson-chat-container');
  if (chatContainer) chatContainer.innerHTML = '';
  
  // Select a problem based on profile
  this.selectLessonProblem();
  
  // Start the "Lesson"
  setTimeout(() => {
    this.addAiMessage(`Hi ${this.getFirstName()}! Let's try a quick problem to see how I can help you.`);
  }, 500);
  
  setTimeout(() => {
    this.addAiMessage(this.lessonState.currentProblem.question);
    this.renderLessonOptions();
  }, 1500);
};

OnboardingWizard.prototype.getFirstName = function() {
  // Simple heuristic to get first name from display name or session
  // Assuming 'Thinking' is the default name in some contexts, but let's use what we have
  const nameElement = document.querySelector('h2'); // "Let's Get Started, [Name]!"
  if (nameElement) {
    const text = nameElement.textContent;
    const match = text.match(/Started, (.+?)!/);
    if (match) return match[1];
  }
  return 'there';
};

OnboardingWizard.prototype.selectLessonProblem = function() {
  const subject = this.profileData.primarySubject || 'general';
  
  // Simple Problem Bank
  const problems = {
    'mathematics': {
      question: "If you have a triangle with angles 90° and 45°, what is the third angle?",
      options: [
        { text: "45°", correct: true, feedback: "Correct! The sum of angles in a triangle is always 180°." },
        { text: "90°", correct: false, feedback: "Not quite. Remember, the sum of all angles must be 180°." },
        { text: "30°", correct: false, feedback: "Close, but check your subtraction from 180°." }
      ]
    },
    'science': {
      question: "What happens to water when it freezes?",
      options: [
        { text: "It expands", correct: true, feedback: "Spot on! Water expands by about 9% when it turns to ice." },
        { text: "It shrinks", correct: false, feedback: "Actually, water is unique! It takes up more space as ice." },
        { text: "Stays the same", correct: false, feedback: "Surprisingly no, the molecules form a crystal structure that takes up more space." }
      ]
    },
    'computer-science': {
      question: "Which of these is used to style a webpage?",
      options: [
        { text: "CSS", correct: true, feedback: "Perfect! CSS (Cascading Style Sheets) controls how elements look." },
        { text: "HTML", correct: false, feedback: "HTML provides the structure, but not the style." },
        { text: "Python", correct: false, feedback: "Python is great for logic, but usually runs on the server, not for styling." }
      ]
    },
    'languages': {
      question: "Which word is a verb?",
      options: [
        { text: "Run", correct: true, feedback: "Yes! 'Run' is an action word." },
        { text: "Blue", correct: false, feedback: "'Blue' describes something, so it's an adjective." },
        { text: "House", correct: false, feedback: "'House' is a thing, so it's a noun." }
      ]
    },
    'general': { // Fallback
      question: "Which of these helps you learn best?",
      options: [
        { text: "Practice", correct: true, feedback: "Exactly! Active recall is the most effective way to learn." },
        { text: "Re-reading", correct: false, feedback: "That helps familiarization, but active practice is stronger!" },
        { text: "Highlighting", correct: false, feedback: "It looks nice, but testing yourself works better." }
      ]
    }
  };
  
  // Normalize subject key
  let key = 'general';
  if (subject.includes('math')) key = 'mathematics';
  else if (subject.includes('science')) key = 'science';
  else if (subject.includes('computer') || subject.includes('web')) key = 'computer-science';
  else if (subject.includes('lang') || subject.includes('english')) key = 'languages';
  
  this.lessonState.currentProblem = problems[key] || problems['general'];
};

OnboardingWizard.prototype.addAiMessage = function(text, delay = 0) {
  const container = document.getElementById('lesson-chat-container');
  
  // Typing indicator
  this.showTypingIndicator();
  
  setTimeout(() => {
    this.removeTypingIndicator();
    
    const msgDiv = document.createElement('div');
    msgDiv.className = 'chat-message ai fade-in';
    msgDiv.innerHTML = `
      <div class="chat-avatar">🤖</div>
      <div class="chat-bubble">${text}</div>
    `;
    
    container.appendChild(msgDiv);
    this.scrollToBottom();
  }, 1000 + delay);
};

OnboardingWizard.prototype.addUserMessage = function(text) {
  const container = document.getElementById('lesson-chat-container');
  
  const msgDiv = document.createElement('div');
  msgDiv.className = 'chat-message user fade-in';
  msgDiv.innerHTML = `
    <div class="chat-bubble">${text}</div>
  `;
  
  container.appendChild(msgDiv);
  this.scrollToBottom();
};

OnboardingWizard.prototype.showTypingIndicator = function() {
  const container = document.getElementById('lesson-chat-container');
  
  // Remove existing if any
  this.removeTypingIndicator();
  
  const typingDiv = document.createElement('div');
  typingDiv.id = 'typing-indicator';
  typingDiv.className = 'chat-message ai fade-in';
  typingDiv.innerHTML = `
    <div class="chat-avatar">🤖</div>
    <div class="chat-bubble typing">
      <span></span><span></span><span></span>
    </div>
  `;
  
  container.appendChild(typingDiv);
  this.scrollToBottom();
};

OnboardingWizard.prototype.removeTypingIndicator = function() {
  const indicator = document.getElementById('typing-indicator');
  if (indicator) indicator.remove();
};

OnboardingWizard.prototype.scrollToBottom = function() {
  const container = document.getElementById('lesson-chat-container');
  container.scrollTop = container.scrollHeight;
};

OnboardingWizard.prototype.renderLessonOptions = function() {
  const optionsContainer = document.getElementById('lesson-options');
  optionsContainer.innerHTML = '';
  optionsContainer.classList.remove('hidden');
  
  this.lessonState.currentProblem.options.forEach(opt => {
    const btn = document.createElement('button');
    btn.className = 'lesson-option-btn';
    btn.textContent = opt.text;
    btn.onclick = () => this.handleLessonAnswer(opt);
    optionsContainer.appendChild(btn);
  });
};

OnboardingWizard.prototype.handleLessonAnswer = function(option) {
  // Hide interactions temporarily
  const optionsContainer = document.getElementById('lesson-options');
  optionsContainer.classList.add('hidden');
  
  // Add user message
  this.addUserMessage(option.text);
  
  // AI Response
  setTimeout(() => {
    this.addAiMessage(option.feedback);
    
    if (option.correct) {
        this.completeLesson();
    } else {
        // Show options again after feedback
        setTimeout(() => {
            optionsContainer.classList.remove('hidden');
        }, 2000);
    }
  }, 500);
};

OnboardingWizard.prototype.completeLesson = function() {
  setTimeout(() => {
    this.addAiMessage("Great job! You're ready to start your journey.");
    
    // Show Continue Button
    const continueBtn = document.getElementById('lesson-continue-btn');
    continueBtn.classList.remove('hidden');
    continueBtn.classList.remove('disabled'); // Just in case
    continueBtn.classList.add('pop-in');
    
    // Celebration confetti
    this.fireConfetti();
  }, 1500);
};

OnboardingWizard.prototype.fireConfetti = function() {
  // Simple CSS implementation or library call if available
  // For now, we'll just log
  console.log('🎉 Confetti!');
};

OnboardingWizard.prototype.finishLessonAndNext = function() {
  console.log('✅ Lesson Completed');
  this.profileData.firstLessonCompleted = true;
  this.saveProgress();
  this.nextScreen();
};
