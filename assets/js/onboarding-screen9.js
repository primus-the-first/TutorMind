/**
 * Screen 9: Summary & Completion
 * Displays a recap and handles final submission
 */

OnboardingWizard.prototype.initScreen9Summary = function() {
  console.log('✅ Initializing Summary Screen');
  
  this.renderSummary();
};

OnboardingWizard.prototype.renderSummary = function() {
  const container = document.getElementById('summary-content');
  const p = this.profileData;
  
  if (!container) return;
  
  // Determine Main Focus Display
  let focusDisplay = '';
  
  if (p.educationLevel === 'college' && p.universityProgram) {
      focusDisplay = p.universityProgram;
      if (p.customSubjects && p.customSubjects.length > 0) {
          focusDisplay += ` <small>(${p.customSubjects.length} subjects)</small>`;
      }
  } else if (p.educationLevel === 'high' && p.shsProgram) {
      // Find SHS Program Name manually or lazily format slug
      const programName = this.formatSHSProgram(p.shsProgram); 
      focusDisplay = programName;
      if (p.shsElectives && p.shsElectives.length > 0) {
          focusDisplay += ` <small>(+${p.shsElectives.length} electives)</small>`;
      }
  } else {
      // Standard
      const primarySubject = this.formatSubject(p.primarySubject);
      const subjectsCount = p.subjects ? p.subjects.length : 0;
      focusDisplay = `${primarySubject} ${subjectsCount > 1 ? `<small>(+${subjectsCount-1} others)</small>` : ''}`;
  }

  // Format data for display
  const goal = this.formatGoal(p.learningGoal);
  const level = this.formatLevel(p.educationLevel);
  const schedule = this.formatSchedule(p.studySchedule);
  
  container.innerHTML = `
    <div class="summary-card">
      <div class="summary-header">
        <div class="summary-avatar">🎓</div>
        <h3>Your Learning Profile</h3>
      </div>
      
      <div class="summary-grid">
        <div class="summary-item">
          <span class="label">Main Focus</span>
          <span class="value">${focusDisplay}</span>
        </div>
        
        <div class="summary-item">
          <span class="label">Goal</span>
          <span class="value">${goal}</span>
        </div>
        
        <div class="summary-item">
          <span class="label">Level</span>
          <span class="value">${level}</span>
        </div>
        
        <div class="summary-item">
          <span class="label">Schedule</span>
          <span class="value">${schedule}</span>
        </div>
      </div>
    </div>
  `;
};

// Helper formatters
OnboardingWizard.prototype.formatSHSProgram = function(slug) {
    if (!slug) return slug;
    // Simple formatter since we don't have the object mapping here without importing it
    return slug.split('-').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
};

OnboardingWizard.prototype.formatSubject = function(slug) {
  if (!slug) return 'General';
  return slug.split('-').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
};

OnboardingWizard.prototype.formatGoal = function(slug) {
  const map = {
    'homework_help': 'Homework Help',
    'exam_prep': 'Exam Prep',
    'concept_mastery': 'Master Concepts',
    'get_ahead': 'Get Ahead',
    'catch_up': 'Catch Up',
    'general_learning': 'Curiosity'
  };
  return map[slug] || 'Learning';
};

OnboardingWizard.prototype.formatLevel = function(slug) {
  const map = {
    'elementary': 'Elementary',
    'middle': 'Middle School',
    'high': 'High School',
    'college': 'University',
    'adult': 'Adult / Pro'
  };
  return map[slug] || 'Standard';
};

OnboardingWizard.prototype.formatSchedule = function(slug) {
  if (!slug) return 'Flexible';
  return slug.charAt(0).toUpperCase() + slug.slice(1);
};
