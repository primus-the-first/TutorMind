/**
 * Screen 7: Notifications
 * Configures study reminders
 */

OnboardingWizard.prototype.initScreen7Notifications = function() {
  console.log('🔔 Initializing Notifications Screen');
  
  if (!this.notificationState) {
    this.notificationState = {
      enabled: true,
      frequency: 'daily',
      time: '17:00' // Default 5 PM
    };
  }
  
  // Render initial state
  this.renderNotificationState();
  
  // Bind events
  this.bindNotificationEvents();
};

OnboardingWizard.prototype.bindNotificationEvents = function() {
  // Main Toggle
  const toggleBtn = document.getElementById('notification-toggle');
  if (toggleBtn) {
    toggleBtn.onclick = () => {
      this.notificationState.enabled = !this.notificationState.enabled;
      this.renderNotificationState();
    };
  }
  
  // Frequency Buttons
  document.querySelectorAll('.frequency-option').forEach(btn => {
    btn.onclick = (e) => {
      document.querySelectorAll('.frequency-option').forEach(b => b.classList.remove('selected'));
      e.currentTarget.classList.add('selected');
      this.notificationState.frequency = e.currentTarget.dataset.value;
    };
  });
  
  // Time Input
  const timeInput = document.getElementById('notification-time');
  if (timeInput) {
    timeInput.onchange = (e) => {
      this.notificationState.time = e.target.value;
    };
  }
  
  // Skip/Enable Buttons
  const mainBtn = document.getElementById('notifications-continue-btn');
  if (mainBtn) {
    mainBtn.onclick = () => this.saveNotificationsAndNext();
  }
  
  const skipBtn = document.getElementById('notifications-skip-btn');
  if (skipBtn) {
    skipBtn.onclick = () => {
        this.notificationState.enabled = false;
        this.saveNotificationsAndNext();
    };
  }
};

OnboardingWizard.prototype.renderNotificationState = function() {
  const container = document.getElementById('notification-settings-container');
  const toggleBtn = document.getElementById('notification-toggle');
  const toggleIcon = toggleBtn.querySelector('i');
  const toggleText = toggleBtn.querySelector('span');
  
  if (this.notificationState.enabled) {
    container.classList.remove('hidden');
    container.classList.add('fade-in');
    
    toggleBtn.classList.add('active');
    toggleIcon.className = 'fas fa-bell';
    toggleText.textContent = 'Reminders Enabled';
  } else {
    container.classList.add('hidden');
    
    toggleBtn.classList.remove('active');
    toggleIcon.className = 'fas fa-bell-slash';
    toggleText.textContent = 'Reminders Disabled';
  }
};

OnboardingWizard.prototype.saveNotificationsAndNext = function() {
  console.log('✅ Notifications Configured:', this.notificationState);
  
  // Log Screen 6/7 Transition explicitly as requested
  console.log('📍 Completed Screen 7, Moving to Screen 8');

  this.profileData.notificationsEnabled = this.notificationState.enabled;
  if (this.notificationState.enabled) {
    this.profileData.notificationFrequency = this.notificationState.frequency;
    this.profileData.notificationTime = this.notificationState.time;
    
    // Request browser permission if possible (non-blocking)
    if ('Notification' in window && Notification.permission !== 'granted' && Notification.permission !== 'denied') {
      Notification.requestPermission().then(permission => {
        console.log('Browser notification permission:', permission);
      });
    }
  }
  
  this.saveProgress();
  this.nextScreen();
};
