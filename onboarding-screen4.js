/**
 * Screen 4: Education Level & Country Selection Logic
 * Handles education level cards and country dropdown
 */

// Extend the wizard with Screen 4 methods
OnboardingWizard.prototype.initScreen4Education = function() {
  console.log('🎓 Initializing Education Screen');
  
  // Set up education card click handlers
  const educationCards = document.querySelectorAll('.education-card');
  educationCards.forEach(card => {
    card.addEventListener('click', () => {
      this.selectEducationLevel(card);
    });
  });
  
  // Set up country dropdown handler
  const countrySelect = document.getElementById('country-select');
  if (countrySelect) {
    countrySelect.addEventListener('change', (e) => {
      this.profileData.country = e.target.value;
      this.saveProgress();
      console.log('✅ Country selected:', this.profileData.country);
    });
  }
  
  // Load saved selections if any
  if (this.profileData.educationLevel) {
    const savedCard = document.querySelector(`.education-card[data-level="${this.profileData.educationLevel}"]`);
    if (savedCard) {
      savedCard.classList.add('selected');
      // Show country selector
      document.getElementById('country-selector').classList.remove('hidden');
    }
  }
  
  if (this.profileData.country) {
    const countrySelect = document.getElementById('country-select');
    if (countrySelect) {
      countrySelect.value = this.profileData.country;
    }
  }
};

OnboardingWizard.prototype.selectEducationLevel = function(selectedCard) {
  // Remove selection from all cards
  document.querySelectorAll('.education-card').forEach(card => {
    card.classList.remove('selected');
  });
  
  // Add selection to clicked card
  selectedCard.classList.add('selected');
  
  // Save to profile data
  this.profileData.educationLevel = selectedCard.dataset.level;
  this.saveProgress();
  
  console.log('✅ Education level selected:', this.profileData.educationLevel);
  
  // Show country selector
  const countrySelector = document.getElementById('country-selector');
  if (countrySelector) {
    countrySelector.classList.remove('hidden');
  }
  
  // Hide error message if shown
  const errorMsg = document.getElementById('education-error');
  if (errorMsg) {
    errorMsg.classList.remove('show');
  }
};

OnboardingWizard.prototype.validateEducation = function() {
  const errorMsg = document.getElementById('education-error');
  
  if (!this.profileData.educationLevel) {
    errorMsg.textContent = 'Please select your education level to continue.';
    errorMsg.classList.add('show');
    return false;
  }
  
  // Country is optional but recommended
  if (!this.profileData.country) {
    errorMsg.textContent = 'Tip: Selecting your country helps us align with your curriculum!';
    errorMsg.style.background = 'rgba(255, 183, 71, 0.1)';
    errorMsg.style.color = '#D97706';
    errorMsg.classList.add('show');
    
    // Still allow continuing, but show the tip
    setTimeout(() => {
      errorMsg.classList.remove('show');
      errorMsg.style.background = '';
      errorMsg.style.color = '';
    }, 4000);
  }
  
  errorMsg.classList.remove('show');
  return true;
};

OnboardingWizard.prototype.saveEducationAndNext = function() {
  if (this.validateEducation()) {
    console.log('✅ Education level saved:', this.profileData.educationLevel);
    console.log('✅ Country saved:', this.profileData.country || 'Not selected');
    this.nextScreen();
  } else {
    // Scroll to error message
    document.getElementById('education-error').scrollIntoView({ 
      behavior: 'smooth', 
      block: 'center' 
    });
  }
};
