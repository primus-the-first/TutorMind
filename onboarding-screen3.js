/**
 * Screen 3: Goal Selection Logic
 * Handles single-select goal cards
 */

// Extend the wizard with Screen 3 methods
OnboardingWizard.prototype.initScreen3Goals = function() {
  console.log('🎯 Initializing Goals Screen');
  
  // Set up goal card click handlers
  const goalCards = document.querySelectorAll('.goal-card');
  goalCards.forEach(card => {
    card.addEventListener('click', () => {
      this.selectGoal(card);
    });
  });
  
  // Load saved selection if any
  if (this.profileData.learningGoal) {
    const savedCard = document.querySelector(`.goal-card[data-goal="${this.profileData.learningGoal}"]`);
    if (savedCard) {
      savedCard.classList.add('selected');
    }
  }
};

OnboardingWizard.prototype.selectGoal = function(selectedCard) {
  // Remove selection from all cards
  document.querySelectorAll('.goal-card').forEach(card => {
    card.classList.remove('selected');
  });
  
  // Add selection to clicked card
  selectedCard.classList.add('selected');
  
  // Save to profile data
  this.profileData.learningGoal = selectedCard.dataset.goal;
  this.saveProgress();
  
  console.log('✅ Goal selected:', this.profileData.learningGoal);
  
  // Hide error message if shown
  const errorMsg = document.getElementById('goals-error');
  if (errorMsg) {
    errorMsg.classList.remove('show');
  }
};

OnboardingWizard.prototype.validateGoal = function() {
  const errorMsg = document.getElementById('goals-error');
  
  if (!this.profileData.learningGoal) {
    errorMsg.classList.add('show');
    return false;
  }
  
  errorMsg.classList.remove('show');
  return true;
};

OnboardingWizard.prototype.saveGoalAndNext = function() {
  if (this.validateGoal()) {
    console.log('✅ Goal saved:', this.profileData.learningGoal);
    this.nextScreen();
  } else {
    // Scroll to error message
    document.getElementById('goals-error').scrollIntoView({ 
      behavior: 'smooth', 
      block: 'center' 
    });
  }
};
