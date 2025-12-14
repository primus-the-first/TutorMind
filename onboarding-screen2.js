/**
 * Screen 2: Education Level & School Selection Logic
 * Refactored to be Screen 2 (previously Screen 4 logic + School Input)
 */

OnboardingWizard.prototype.initScreen2Education = function() {
  console.log('🎓 Initializing Education Screen (Screen 2)');
  
  // 1. Education Level Selection
  const educationCards = document.querySelectorAll('.education-card');
  educationCards.forEach(card => {
    card.addEventListener('click', () => {
      this.selectEducationLevel(card);
    });
  });

  // 1.5 Populate University Datalist
  const datalist = document.getElementById('university-list');
  if (datalist && typeof universityList !== 'undefined') {
      // Clear existing just in case
      datalist.innerHTML = ''; 
      universityList.forEach(uni => {
          const option = document.createElement('option');
          option.value = uni;
          datalist.appendChild(option);
      });
  }

  // 2. School/University Input
  const schoolInput = document.getElementById('school-name-input');
  if (schoolInput) {
    schoolInput.addEventListener('input', (e) => {
      this.profileData.schoolName = e.target.value;
      this.saveProgress();
      this.validateScreen2();
    });

    // Handle suggestion selection specifically if needed
    schoolInput.addEventListener('change', (e) => {
         this.profileData.schoolName = e.target.value;
         this.saveProgress();
    });
  }

  // Restore state
  if (this.profileData.educationLevel) {
    const savedCard = document.querySelector(`.education-card[data-level="${this.profileData.educationLevel}"]`);
    if (savedCard) {
        this.selectEducationLevel(savedCard, false); // false = don't scroll/animate excessively
    }
  }

  if (this.profileData.schoolName && schoolInput) {
      schoolInput.value = this.profileData.schoolName;
  }
};

OnboardingWizard.prototype.selectEducationLevel = function(selectedCard, animate = true) {
  // UI Update
  document.querySelectorAll('.education-card').forEach(card => card.classList.remove('selected'));
  selectedCard.classList.add('selected');
  
  // Save Data
  const level = selectedCard.dataset.level;
  this.profileData.educationLevel = level;
  this.saveProgress();
  
  // Show School Input Logic
  this.renderSchoolInput(level);

  // Clear error
  const errorMsg = document.getElementById('screen2-error');
  if (errorMsg) errorMsg.classList.remove('show');
};

OnboardingWizard.prototype.renderSchoolInput = function(level) {
    const container = document.getElementById('school-input-container');
    const input = document.getElementById('school-name-input');
    const label = document.getElementById('school-input-label');
    const datalist = document.getElementById('university-list');

    if (!container || !input) return;

    container.classList.remove('hidden');
    input.value = ''; // Clear previous input on level change? Or keep it? Let's clear to avoid "High School" having a Uni name.
    this.profileData.schoolName = '';

    // Configure Input based on Level
    if (level === 'college') {
        label.textContent = "Which university or college do you attend?";
        input.placeholder = "Start typing to search universities...";
        input.setAttribute('list', 'university-list'); // Enable Autocomplete
    } else if (level === 'high') {
        label.textContent = "What is the name of your school?";
        input.placeholder = "Enter your school name...";
        input.removeAttribute('list'); // Disable Autocomplete (Manual Entry)
    } else if (level === 'adult') {
         // Optional for adults?
        label.textContent = "Organization or Institution (Optional)";
        input.placeholder = "Company or School name...";
        input.removeAttribute('list');
    } else {
        label.textContent = "School or Institution Name";
        input.placeholder = "Enter name...";
        input.removeAttribute('list');
    }
};

OnboardingWizard.prototype.validateScreen2 = function() {
    const level = this.profileData.educationLevel;
    const school = this.profileData.schoolName;
    const errorMsg = document.getElementById('screen2-error');
    const nextBtn = document.getElementById('screen2-next-btn'); // If we want to disable/enable button

    if (!level) {
        if(errorMsg) {
            errorMsg.textContent = "Please select your education level.";
            errorMsg.classList.add('show');
        }
        return false;
    }

    // require school name for High School and College
    if ((level === 'high' || level === 'college') && (!school || school.trim() === '')) {
         // We might not show error immediately on typing, but on "Next" click
         return false; 
    }

    if (errorMsg) errorMsg.classList.remove('show');
    return true;
};

OnboardingWizard.prototype.saveEducationAndNext = function() {
    const level = this.profileData.educationLevel;
    const school = this.profileData.schoolName;
    const errorMsg = document.getElementById('screen2-error');

    // Validation
    if (!level) {
        errorMsg.textContent = "Please select an education level.";
        errorMsg.classList.add('show');
        return;
    }

    if ((level === 'high' || level === 'college') && (!school || school.trim() === '')) {
        errorMsg.textContent = level === 'college' ? "Please select or type your university name." : "Please enter your school name.";
        errorMsg.classList.add('show');
        document.getElementById('school-name-input').focus();
        return;
    }

    // Success
    console.log('✅ Screen 2 Complete:', { level, school });
    this.nextScreen();
};
