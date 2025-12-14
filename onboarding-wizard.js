/**
 * OnboardingWizard - Core Navigation and State Management
 * Manages 9-screen interactive onboarding flow
 */

class OnboardingWizard {
  constructor() {
    this.currentScreen = 1;
    this.totalScreens = 9;
    this.profileData = {
      // Screen 3: Subjects
      subjects: [], // Standard
      primarySubject: null,
      
      // Screen 3: High School (SHS)
      shsProgram: null,
      shsElectives: [],
      
      // Screen 3: University
      universityProgram: null,
      customSubjects: [], // List of subjects

      // Screen 4: Goal
      learningGoal: null,
      
      // Screen 2: Education
      educationLevel: null,
      schoolName: null,
      country: null,
      curriculumSystem: null,
      
      // Screen 5: Assessment
      assessmentResults: {},
      baselineMasteryLevel: null,
      
      // Screen 6: Preferences
      studySchedule: null,
      sessionLength: null,
      language: 'en',
      explanationStyle: null,
      
      // Screen 7: Notifications
      notificationsEnabled: true,
      notificationFrequency: null,
      notificationTime: null,
      
      // Screen 8: First Lesson
      firstLessonCompleted: false,
      firstLessonData: null
    };
    
    this.assessmentState = {
      currentQuestion: 0,
      totalQuestions: 8,
      difficulty: 5, // Start mid-level
      correctCount: 0,
      answers: []
    };
    
    this.lessonState = {
      problem: null,
      conversation: [],
      hintsGiven: 0,
      completed: false
    };
    
    this.init();
  }
  
  init() {
    console.log('🧠 TutorMind Onboarding Wizard initialized');
    
    // Load saved progress if exists
    this.loadProgress();
    
    // Set up event listeners
    this.setupEventListeners();
    
    // Show current screen
    this.showScreen(this.currentScreen);
  }
  
  // ==================== NAVIGATION ====================
  
  nextScreen() {
    if (this.currentScreen < this.totalScreens) {
      this.currentScreen++;
      this.showScreen(this.currentScreen);
      this.saveProgress();
    }
  }
  
  previousScreen() {
    if (this.currentScreen > 1) {
      this.currentScreen--;
      this.showScreen(this.currentScreen);
    }
  }
  
  goToScreen(screenNumber) {
    if (screenNumber >= 1 && screenNumber <= this.totalScreens) {
      this.currentScreen = screenNumber;
      this.showScreen(screenNumber);
      this.saveProgress();
    }
  }
  
  showScreen(screenNumber) {
    // Hide all screens
    document.querySelectorAll('.screen').forEach(screen => {
      screen.classList.remove('active');
    });
    
    // Show target screen
    const targetScreen = document.getElementById(`screen${screenNumber}`);
    if (targetScreen) {
      targetScreen.classList.add('active');
      
      // Update progress indicator
      this.updateProgress(screenNumber);
      
      // Scroll to top
      window.scrollTo(0, 0);
      
      // Screen-specific initialization
      this.initializeScreen(screenNumber);
    }
  }
  
  updateProgress(screenNumber) {
    const progressBar = document.getElementById('wizard-progress-bar');
    if (progressBar) {
      const percentage = (screenNumber / this.totalScreens) * 100;
      progressBar.style.width = `${percentage}%`;
    }
    
    const progressText = document.getElementById('wizard-progress-text');
    if (progressText) {
      progressText.textContent = `${screenNumber} / ${this.totalScreens}`;
    }
  }
  
  initializeScreen(screenNumber) {
    switch(screenNumber) {
      case 1:
        this.initScreen1Welcome();
        break;
      case 2:
        this.initScreen2Education();
        break;
      case 3:
        this.initScreen3Subjects();
        break;
      case 4:
        this.initScreen4Goals();
        break;
      case 5:
        this.initScreen5Assessment();
        break;
      case 6:
        this.initScreen6Preferences();
        break;
      case 7:
        this.initScreen7Notifications();
        break;
      case 8:
        this.initScreen8FirstLesson();
        break;
      case 9:
        this.initScreen9Summary();
        break;
    }
  }
  
  // ==================== PROGRESS PERSISTENCE ====================
  
  saveProgress() {
    const progressData = {
      currentScreen: this.currentScreen,
      profileData: this.profileData,
      timestamp: Date.now(),
      version: '1.0'
    };
    
    try {
      localStorage.setItem('tutormind_onboarding_progress', JSON.stringify(progressData));
      console.log('💾 Progress saved:', this.currentScreen);
    } catch (e) {
      console.error('Failed to save progress:', e);
    }
  }
  
  loadProgress() {
    // TEMPORARILY DISABLED for testing - re-enable later
    console.log('⚠️ Progress loading disabled for testing');
    return false;
    
    /* Original code - restore after testing
    try {
      const saved = localStorage.getItem('tutormind_onboarding_progress');
      if (saved) {
        const progressData = JSON.parse(saved);
        
        // Check if saved within last 7 days
        const daysSince = (Date.now() - progressData.timestamp) / (1000 * 60 * 60 * 24);
        if (daysSince < 7) {
          this.currentScreen = progressData.currentScreen || 1;
          this.profileData = { ...this.profileData, ...progressData.profileData };
          console.log('📂 Progress loaded from screen:', this.currentScreen);
          return true;
        }
      }
    } catch (e) {
      console.error('Failed to load progress:', e);
    }
    return false;
    */
  }
  
  clearProgress() {
    localStorage.removeItem('tutormind_onboarding_progress');
    console.log('🗑️ Progress cleared');
  }
  
  // ==================== SCREEN 1: WELCOME ====================
  
  initScreen1Welcome() {
    console.log('🎉 Initializing Welcome Screen');
    
    // Trigger animation (function defined in onboarding-animations.js)
    if (typeof window.initWelcomeAnimation === 'function') {
      window.initWelcomeAnimation();
    }
    
    // Set up the Get Started button
    const getStartedBtn = document.getElementById('get-started-btn');
    if (getStartedBtn) {
      getStartedBtn.onclick = () => this.nextScreen();
    }
  }
  
  // ==================== SCREEN-SPECIFIC METHODS (Placeholders) ====================
  
  initScreen2Education() {
    console.log('🎓 Initializing Education Screen');
    // Will implement in next iteration
  }
  
  initScreen3Subjects() {
    console.log('📚 Initializing Subjects Screen');
    // Will implement in next iteration
  }
  
  initScreen4Goals() {
    console.log('🎯 Initializing Goals Screen');
    // Will implement in next iteration
  }
  
  initScreen5Assessment() {
    console.log('📊 Initializing Assessment Screen');
    // Will implement in next iteration
  }
  
  initScreen6Preferences() {
    console.log('⚙️ Initializing Preferences Screen');
    // Will implement in next iteration
  }
  
  initScreen7Notifications() {
    console.log('🔔 Initializing Notifications Screen');
    // Will implement in next iteration
  }
  
  initScreen8FirstLesson() {
    console.log('📖 Initializing First Lesson Screen');
    // Will implement in next iteration
  }
  
  initScreen9Summary() {
    console.log('✅ Initializing Summary Screen');
    // Will implement in next iteration
  }
  
  // ==================== EVENT LISTENERS ====================
  
  setupEventListeners() {
    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
      // Allow Escape to go back (except on assessment/lesson screens)
      if (e.key === 'Escape' && this.currentScreen > 1 && 
          this.currentScreen !== 5 && this.currentScreen !== 8) {
        this.previousScreen();
      }
    });
    
    // Prevent accidental page refresh during onboarding
    window.addEventListener('beforeunload', (e) => {
      if (this.currentScreen > 1 && this.currentScreen < this.totalScreens) {
        e.preventDefault();
        e.returnValue = 'Your progress will be saved. Are you sure you want to leave?';
        return e.returnValue;
      }
    });
  }
  
  // ==================== API INTEGRATION ====================
  
  async saveToDatabase() {
    try {
      const response = await fetch('api/user_onboarding.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(this.profileData)
      });
      
      const result = await response.json();
      
      if (result.success) {
        console.log('✅ Profile saved to database');
        this.clearProgress();
        return true;
      } else {
        console.error('❌ Failed to save profile:', result.error);
        return false;
      }
    } catch (error) {
      console.error('❌ API error:', error);
      return false;
    }
  }
  
  async completeOnboarding() {
    console.log('🎓 Completing onboarding...');
    
    const saved = await this.saveToDatabase();
    
    if (saved) {
      // Redirect to main app
      window.location.href = 'chat';
    } else {
      alert('There was an error saving your profile. Please try again.');
    }
  }
}

// Initialize wizard when DOM is ready
// Initialize wizard when DOM is ready
window.wizard = null;
document.addEventListener('DOMContentLoaded', () => {
  window.wizard = new OnboardingWizard();
});
