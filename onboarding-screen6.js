/**
 * Screen 6: Learning Preferences
 * Captures study schedule, session length, and explanation style
 */

// Extend the wizard with Screen 6 methods
OnboardingWizard.prototype.initScreen6Preferences = function() {
  console.log('⚙️ Initializing Preferences Screen');
  
  // Initialize state for screen 6 if not already
  if (!this.preferenceState) {
    this.preferenceState = {
      schedule: null,
      duration: null,
      style: null
    };
  }

  // Bind events for School Schedule options
  document.querySelectorAll('.schedule-option').forEach(btn => {
    btn.onclick = (e) => this.selectSchedule(e.currentTarget);
  });

  // Bind events for Session Duration options
  document.querySelectorAll('.duration-option').forEach(btn => {
    btn.onclick = (e) => this.selectDuration(e.currentTarget);
  });

  // Bind events for Explanation Style cards
  document.querySelectorAll('.style-card').forEach(card => {
    card.onclick = (e) => this.selectStyle(e.currentTarget);
  });
  
  // Check completion status on init
  this.checkPreferencesCompletion();
};

OnboardingWizard.prototype.selectSchedule = function(element) {
  // Update UI
  document.querySelectorAll('.schedule-option').forEach(btn => btn.classList.remove('selected'));
  element.classList.add('selected');
  
  // Update state
  this.preferenceState.schedule = element.dataset.value;
  
  // Validate
  this.checkPreferencesCompletion();
};

OnboardingWizard.prototype.selectDuration = function(element) {
  // Update UI
  document.querySelectorAll('.duration-option').forEach(btn => btn.classList.remove('selected'));
  element.classList.add('selected');
  
  // Update state
  this.preferenceState.duration = element.dataset.value;
  
  // Validate
  this.checkPreferencesCompletion();
};

OnboardingWizard.prototype.selectStyle = function(element) {
  // Update UI
  document.querySelectorAll('.style-card').forEach(card => card.classList.remove('selected'));
  element.classList.add('selected');
  
  // Update state
  this.preferenceState.style = element.dataset.value;
  
  // Validate
  this.checkPreferencesCompletion();
};

OnboardingWizard.prototype.checkPreferencesCompletion = function() {
  const { schedule, duration, style } = this.preferenceState;
  const continueBtn = document.getElementById('preferences-continue-btn');
  const errorMsg = document.getElementById('preferences-error');
  
  if (schedule && duration && style) {
    continueBtn.classList.remove('disabled');
    errorMsg.style.display = 'none';
    return true;
  } else {
    continueBtn.classList.add('disabled');
    return false;
  }
};

OnboardingWizard.prototype.savePreferencesAndNext = function() {
  if (!this.checkPreferencesCompletion()) {
    const errorMsg = document.getElementById('preferences-error');
    errorMsg.style.display = 'block';
    errorMsg.classList.add('show');
    return;
  }

  console.log('✅ Preferences Saved:', this.preferenceState);

  // Save to main profile data
  this.profileData.studySchedule = this.preferenceState.schedule;
  this.profileData.sessionLength = this.preferenceState.duration;
  this.profileData.explanationStyle = this.preferenceState.style;
  
  this.saveProgress();
  this.nextScreen();
};
