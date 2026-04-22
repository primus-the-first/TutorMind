/**
 * Screen 5: AI Knowledge Assessment
 * Simulates AI-generated questions based on profile
 */

// Extend the wizard with Screen 5 methods
OnboardingWizard.prototype.initScreen5Assessment = function() {
  console.log('🤖 Initializing AI Assessment');
  
  // Show loading state first
  const loadingState = document.getElementById('assessment-loading');
  const contentState = document.getElementById('assessment-content');
  
  loadingState.classList.remove('hidden');
  contentState.classList.add('hidden');
  
  // Generate questions based on previous choices
  this.assessmentState = {
    questions: this.generateMockQuestions(),
    currentIndex: 0,
    answers: []
  };
  
  // Simulate AI processing delay (1.5 seconds)
  setTimeout(() => {
    loadingState.classList.add('hidden');
    contentState.classList.remove('hidden');
    this.renderQuestion();
  }, 1500);
};

OnboardingWizard.prototype.generateMockQuestions = function() {
  const level = this.profileData.educationLevel || 'high';
  const subjects = this.profileData.subjects || [];
  const primarySubject = this.profileData.primarySubject || (subjects[0]?.category) || 'mathematics';
  
  console.log(`Generating questions for ${level} ${primarySubject}`);
  
  // Mock Question Bank
  const questionBank = {
    'mathematics': {
      'elementary': [
        { text: "What is 12 × 4?", options: ["48", "36", "44", "40"], correct: 0 },
        { text: "Which fraction is bigger?", options: ["1/2", "1/4", "3/8", "I'm not sure"], correct: 0 },
        { text: "Solve for x: x + 5 = 12", options: ["7", "6", "17", "I need a hint"], correct: 0 }
      ],
      'middle': [
        { text: "What is the square root of 144?", options: ["12", "14", "10", "16"], correct: 0 },
        { text: "Solve: 2x - 4 = 10", options: ["7", "6", "5", "8"], correct: 0 },
        { text: "What is 25% of 80?", options: ["20", "25", "15", "18"], correct: 0 }
      ],
      'high': [
        { text: "Factor: x² - 9", options: ["(x-3)(x+3)", "(x-3)²", "(x+9)(x-1)", "I'm not sure"], correct: 0 },
        { text: "What is the slope of y = 2x + 5?", options: ["2", "5", "x", "Undefined"], correct: 0 },
        { text: "Solve for x: 2ˣ = 32", options: ["5", "16", "6", "4"], correct: 0 }
      ]
    },
    'science': {
      'elementary': [
        { text: "What do plants need to grow?", options: ["Sunlight & Water", "Just rocks", "Candy", "Moonlight"], correct: 0 },
        { text: "Which is a planet?", options: ["Mars", "Moon", "Sun", "Pluto"], correct: 0 }
      ],
      'high': [
        { text: "What is the atomic number of Carbon?", options: ["6", "12", "14", "8"], correct: 0 },
        { text: "Which is a noble gas?", options: ["Helium", "Oxygen", "Chlorine", "Iron"], correct: 0 },
        { text: "Powerhouse of the cell?", options: ["Mitochondria", "Nucleus", "Ribosome", "Golgi"], correct: 0 }
      ]
    }
  };
  
  // Fallback if specific combo not found
  let questions = [];
  
  // Try to get specific questions
  if (questionBank[primarySubject] && questionBank[primarySubject][level]) {
    questions = questionBank[primarySubject][level];
  } else if (questionBank['mathematics'][level]) {
    questions = questionBank['mathematics'][level];
  } else {
    // Default to high school math if all else fails
    questions = questionBank['mathematics']['high'];
  }
  
  // Add an "Assessment Complete" dummy step logic if needed, 
  // but for now we just return these 3 questions
  return questions;
};

OnboardingWizard.prototype.renderQuestion = function() {
  const state = this.assessmentState;
  const question = state.questions[state.currentIndex];
  
  // Update Header
  document.getElementById('current-q-num').textContent = state.currentIndex + 1;
  document.getElementById('total-q-num').textContent = state.questions.length;
  
  // Update Question Text
  const questionText = document.getElementById('question-text');
  questionText.style.opacity = 0;
  setTimeout(() => {
    questionText.textContent = question.text;
    questionText.style.opacity = 1;
  }, 200);
  
  // Render Options
  const container = document.getElementById('options-container');
  container.innerHTML = ''; // Clear previous
  
  question.options.forEach((opt, index) => {
    const btn = document.createElement('button');
    btn.className = 'option-btn';
    btn.innerHTML = `<span class="option-marker">${String.fromCharCode(65 + index)}</span> ${opt}`;
    
    btn.onclick = () => this.selectAnswer(index);
    
    // Animation stagger
    btn.style.animation = `fadeIn 0.3s ease ${index * 0.1}s backwards`;
    
    container.appendChild(btn);
  });
  
  // Disable Next button
  document.getElementById('assessment-continue-btn').classList.add('disabled');
};

OnboardingWizard.prototype.selectAnswer = function(index) {
  // Update visual state
  const buttons = document.querySelectorAll('.option-btn');
  buttons.forEach(btn => btn.classList.remove('selected'));
  buttons[index].classList.add('selected');
  
  // Enable Next button
  document.getElementById('assessment-continue-btn').classList.remove('disabled');
  
  // Store temporary answer
  this.assessmentState.tempAnswer = index;
};

OnboardingWizard.prototype.nextQuestionOnly = function() {
  const state = this.assessmentState;
  
  // Save answer
  state.answers.push({
    questionIdx: state.currentIndex,
    answerIdx: state.tempAnswer,
    questionText: state.questions[state.currentIndex].text,
    selectedOption: state.questions[state.currentIndex].options[state.tempAnswer]
  });
  
  // Check if more questions
  if (state.currentIndex < state.questions.length - 1) {
    state.currentIndex++;
    this.renderQuestion();
  } else {
    // Finished
    this.finishAssessment();
  }
};

OnboardingWizard.prototype.finishAssessment = function() {
  console.log('✅ Assessment Complete:', this.assessmentState.answers);
  
  // Save to main profile data
  this.profileData.assessmentResults = this.assessmentState.answers;
  this.saveProgress();
  
  // Move to next screen
  this.nextScreen();
};

OnboardingWizard.prototype.skipAssessment = function() {
  console.log('⏭️ Skipping Assessment');
  this.profileData.skippedAssessment = true;
  this.saveProgress();
  this.nextScreen();
};
