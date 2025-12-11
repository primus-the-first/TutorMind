/**
 * Screen 2: Subject Selection Logic
 * Handles multi-select, search, and primary subject selection
 */

// Extend the wizard with Screen 2 methods
OnboardingWizard.prototype.initScreen2Subjects = function() {
  console.log('📚 Initializing Subjects Screen');
  
  // Set up subject card click handlers
  const subjectCards = document.querySelectorAll('.subject-card');
  subjectCards.forEach(card => {
    const header = card.querySelector('.subject-card-header');
    
    header.addEventListener('click', () => {
      this.toggleSubjectCard(card);
    });
  });
  
  // Set up subcategory checkbox handlers
  const subcategoryCheckboxes = document.querySelectorAll('.subcategories input[type="checkbox"]');
  subcategoryCheckboxes.forEach(checkbox => {
    checkbox.addEventListener('change', (e) => {
      e.stopPropagation(); // Prevent card toggle
      this.updateSubjectSelection();
    });
  });
  
  // Set up search functionality
  const searchInput = document.getElementById('subject-search');
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      this.filterSubjects(e.target.value);
    });
  }
  
  // Load saved selections if any
  this.loadSavedSubjects();
};

OnboardingWizard.prototype.toggleSubjectCard = function(card) {
  const subcategories = card.querySelector('.subcategories');
  const checkmark = card.querySelector('.checkmark');
  
  // Toggle subcategories visibility
  if (subcategories.classList.contains('hidden')) {
    subcategories.classList.remove('hidden');
    subcategories.classList.add('visible');
  } else {
    // Check if any subcategories are selected
    const selectedSubcategories = subcategories.querySelectorAll('input[type="checkbox"]:checked');
    if (selectedSubcategories.length === 0) {
      subcategories.classList.add('hidden');
      subcategories.classList.remove('visible');
      card.classList.remove('selected');
      checkmark.classList.add('hidden');
    }
  }
};

OnboardingWizard.prototype.updateSubjectSelection = function() {
  const selectedSubjects = new Map();
  
  // Collect all selected subcategories grouped by parent
  const allCheckboxes = document.querySelectorAll('.subcategories input[type="checkbox"]:checked');
  
  allCheckboxes.forEach(checkbox => {
    const parent = checkbox.dataset.parent;
    const value = checkbox.value;
    
    if (!selectedSubjects.has(parent)) {
      selectedSubjects.set(parent, []);
    }
    selectedSubjects.get(parent).push(value);
  });
  
  // Update card states and checkmarks
  document.querySelectorAll('.subject-card').forEach(card => {
    const subject = card.dataset.subject;
    const checkmark = card.querySelector('.checkmark');
    
    if (selectedSubjects.has(subject)) {
      card.classList.add('selected');
      checkmark.classList.remove('hidden');
    } else {
      card.classList.remove('selected');
      checkmark.classList.add('hidden');
      
      // Collapse if no selections
      const subcategories = card.querySelector('.subcategories');
      subcategories.classList.add('hidden');
      subcategories.classList.remove('visible');
    }
  });
  
  // Show/hide primary subject selector
  this.updatePrimarySubjectSelector(selectedSubjects);
  
  // Update profile data
  this.profileData.subjects = Array.from(selectedSubjects.entries()).map(([parent, subs]) => ({
    category: parent,
    subcategories: subs
  }));
  
  this.saveProgress();
};

OnboardingWizard.prototype.updatePrimarySubjectSelector = function(selectedSubjects) {
  const selector = document.getElementById('primary-subject-selector');
  const buttonsContainer = document.getElementById('primary-subject-buttons');
  
  if (selectedSubjects.size > 1) {
    // Show primary subject selector
    selector.classList.remove('hidden');
   
    // Clear existing buttons
    buttonsContainer.innerHTML = '';
    
    // Create button for each selected subject
    selectedSubjects.forEach((subs, parent) => {
      const btn = document.createElement('button');
      btn.className = 'primary-subject-btn';
      btn.textContent = this.formatSubjectName(parent);
      btn.dataset.subject = parent;
      
      // Check if this is already the primary subject
      if (this.profileData.primarySubject === parent) {
        btn.classList.add('selected');
      }
      
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        this.selectPrimarySubject(parent);
      });
      
      buttonsContainer.appendChild(btn);
    });
  } else if (selectedSubjects.size === 1) {
    // Auto-select the only subject as primary
    const [primarySubject] = selectedSubjects.keys();
    this.profileData.primarySubject = primarySubject;
    selector.classList.add('hidden');
  } else {
    // No subjects selected
    selector.classList.add('hidden');
    this.profileData.primarySubject = null;
  }
};

OnboardingWizard.prototype.selectPrimarySubject = function(subject) {
  this.profileData.primarySubject = subject;
  
  // Update button states
  document.querySelectorAll('.primary-subject-btn').forEach(btn => {
    if (btn.dataset.subject === subject) {
      btn.classList.add('selected');
    } else {
      btn.classList.remove('selected');
    }
  });
  
  this.saveProgress();
};

OnboardingWizard.prototype.formatSubjectName = function(subject) {
  return subject
    .split('-')
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
};

OnboardingWizard.prototype.filterSubjects = function(searchTerm) {
  const term = searchTerm.toLowerCase().trim();
  const cards = document.querySelectorAll('.subject-card');
  
  cards.forEach(card => {
    const subjectName = card.querySelector('h3').textContent.toLowerCase();
    const subcategoryLabels = Array.from(card.querySelectorAll('.subcategories label'))
      .map(label => label.textContent.toLowerCase());
    
    const matches = subjectName.includes(term) || 
                    subcategoryLabels.some(text => text.includes(term));
    
    if (matches || term === '') {
      card.style.display = 'block';
    } else {
      card.style.display = 'none';
    }
  });
};

OnboardingWizard.prototype.loadSavedSubjects = function() {
  if (this.profileData.subjects && this.profileData.subjects.length > 0) {
    // Restore checkbox states
    this.profileData.subjects.forEach(({ category, subcategories }) => {
      subcategories.forEach(sub => {
        const checkbox = document.querySelector(
          `input[type="checkbox"][value="${sub}"][data-parent="${category}"]`
        );
        if (checkbox) {
          checkbox.checked = true;
        }
      });
      
      // Expand the card
      const card = document.querySelector(`.subject-card[data-subject="${category}"]`);
      if (card) {
        const subcategoriesDiv = card.querySelector('.subcategories');
        subcategoriesDiv.classList.remove('hidden');
        subcategoriesDiv.classList.add('visible');
      }
    });
    
    // Update UI states
    this.updateSubjectSelection();
  }
};

OnboardingWizard.prototype.validateSubjects = function() {
  const errorMsg = document.getElementById('subjects-error');
  
  if (!this.profileData.subjects || this.profileData.subjects.length === 0) {
    errorMsg.classList.add('show');
    return false;
  }
  
  // Check if primary subject is selected when multiple subjects chosen
  if (this.profileData.subjects.length > 1 && !this.profileData.primarySubject) {
    errorMsg.textContent = 'Please select which subject you\'d like to start with.';
    errorMsg.classList.add('show');
    return false;
  }
  
  errorMsg.classList.remove('show');
  return true;
};

OnboardingWizard.prototype.saveSubjectsAndNext = function() {
  if (this.validateSubjects()) {
    console.log('✅ Subjects saved:', this.profileData.subjects);
    console.log('✅ Primary subject:', this.profileData.primarySubject);
    this.nextScreen();
  } else {
    // Scroll to error message
    document.getElementById('subjects-error').scrollIntoView({ 
      behavior: 'smooth', 
      block: 'center' 
    });
  }
};
