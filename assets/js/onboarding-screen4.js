/**
 * Screen 4: Learning Goals Logic
 * Refactored to be Screen 4 (previously Screen 3 logic)
 */

OnboardingWizard.prototype.initScreen4Goals = function() {
    console.log('🎯 Initializing Goals Screen (Screen 4)');
    
    const goalCards = document.querySelectorAll('.goal-card');
    goalCards.forEach(card => {
        card.addEventListener('click', () => {
            this.selectGoal(card);
        });
    });

    // Restore state
    if (this.profileData.goals) {
        const savedCard = document.querySelector(`.goal-card[data-goal="${this.profileData.goals}"]`);
        if (savedCard) {
            savedCard.classList.add('selected');
        }
    }
};

OnboardingWizard.prototype.selectGoal = function(selectedCard) {
    // Single select for goals
    document.querySelectorAll('.goal-card').forEach(card => card.classList.remove('selected'));
    selectedCard.classList.add('selected');
    
    this.profileData.goals = selectedCard.dataset.goal;
    this.saveProgress();
    
    console.log('✅ Goal selected:', this.profileData.goals);
    
    // Clear error
    const errorMsg = document.getElementById('screen4-error');
    if (errorMsg) errorMsg.classList.remove('show');
};

OnboardingWizard.prototype.saveGoalAndNext = function() {
    const errorMsg = document.getElementById('screen4-error');

    if (!this.profileData.goals) {
        if(errorMsg) {
            errorMsg.textContent = "Please select a primary goal to continue.";
            errorMsg.classList.add('show');
        }
        return;
    }

    console.log('✅ Screen 4 Complete:', this.profileData.goals);
    this.nextScreen();
};
