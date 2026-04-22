/**
 * Screen 3: Subjects Selection Logic (Dynamic)
 * Adapts to Education Level: 'high' (SHS), 'college' (University), or Standard
 */

// Ghana SHS Programs Data
const shsPrograms = {
    'general-science': {
      name: 'General Science',
      icon: 'fa-microscope',
      description: 'Physics, Chemistry, Biology, Elective Maths',
      styleClass: 'science',
      electives: [
        { id: 'physics', name: 'Physics' },
        { id: 'chemistry', name: 'Chemistry' },
        { id: 'biology', name: 'Biology' },
        { id: 'elective-maths', name: 'Elective Mathematics' }
      ]
    },
    'general-arts': {
      name: 'General Arts',
      icon: 'fa-book-open',
      description: 'Literature, History, Geography, Languages',
      styleClass: 'arts',
      electives: [
        { id: 'literature', name: 'Literature in English' },
        { id: 'history', name: 'History' },
        { id: 'geography', name: 'Geography' },
        { id: 'government', name: 'Government' },
        { id: 'french', name: 'French' },
        { id: 'akan', name: 'Ghanaian Language' }
      ]
    },
    'business': {
      name: 'Business',
      icon: 'fa-briefcase',
      description: 'Accounting, Economics, Business Management',
      styleClass: 'business',
      electives: [
        { id: 'financial-accounting', name: 'Financial Accounting' },
        { id: 'business-management', name: 'Business Management' },
        { id: 'economics', name: 'Economics' },
        { id: 'elective-maths', name: 'Elective Mathematics' },
        { id: 'cost-accounting', name: 'Cost Accounting' }
      ]
    },
    'visual-arts': {
      name: 'Visual Arts',
      icon: 'fa-palette',
      description: 'Graphics, Sculpture, Painting, Textiles',
      styleClass: 'visual-arts',
      electives: [
        { id: 'graphic-design', name: 'Graphic Design' },
        { id: 'picture-making', name: 'Picture Making' },
        { id: 'sculpture', name: 'Sculpture' },
        { id: 'textiles', name: 'Textiles' },
        { id: 'ceramics', name: 'Ceramics' }
      ]
    },
    'home-economics': {
      name: 'Home Economics',
      icon: 'fa-home',
      description: 'Food & Nutrition, Textiles, Management',
      styleClass: 'home-eco',
      electives: [
        { id: 'food-nutrition', name: 'Food & Nutrition' },
        { id: 'textiles', name: 'Textiles' },
        { id: 'management-living', name: 'Management in Living' },
        { id: 'general-knowledge', name: 'General Knowledge in Art' }
      ]
    },
    'agriculture': {
      name: 'Agriculture',
      icon: 'fa-leaf',
      description: 'Crop Science, Animal Husbandry, Economics',
      styleClass: 'agric',
      electives: [
        { id: 'general-agriculture', name: 'General Agriculture' },
        { id: 'animal-husbandry', name: 'Animal Husbandry' },
        { id: 'crop-husbandry', name: 'Crop Husbandry' },
        { id: 'agricultural-economics', name: 'Agricultural Economics' }
      ]
    },
    'technical': {
      name: 'Technical/Vocational',
      icon: 'fa-tools',
      description: 'Building, Engineering, Electronics',
      styleClass: 'technical',
      electives: [
        { id: 'building-construction', name: 'Building Construction' },
        { id: 'technical-drawing', name: 'Technical Drawing' },
        { id: 'metalwork', name: 'Metalwork' },
        { id: 'woodwork', name: 'Woodwork' },
        { id: 'electronics', name: 'Electronics' },
        { id: 'auto-mechanics', name: 'Auto Mechanics' }
      ]
    }
};

OnboardingWizard.prototype.initScreen3Subjects = function() {
    console.log('📚 Initializing Subjects Screen (Screen 3) - Mode Check');
    const level = this.profileData.educationLevel;

    // Reset UI visibility
    document.getElementById('subject-grid').classList.add('hidden');
    document.getElementById('subject-search').parentElement.classList.add('hidden'); // Hide search bar too for custom screens
    document.getElementById('university-custom-form').classList.add('hidden');
    document.getElementById('shs-program-selector').classList.add('hidden');
    document.querySelector('.primary-subject-selector').classList.add('hidden');
    document.getElementById('screen3').querySelector('h2').textContent = "Which subjects do you want help with?"; // Default title
    
    // Mode Switching
    if (level === 'college') {
        this.initUniversityMode();
    } else if (level === 'high') {
        this.initSHSMode();
    } else {
        this.initStandardMode();
    }
};

/* ==================== 1. UNIVERSITY MODE ==================== */
OnboardingWizard.prototype.initUniversityMode = function() {
    document.getElementById('screen3').querySelector('h2').textContent = "Tell us what you're studying";
    document.getElementById('screen3').querySelector('.subtitle').textContent = "We'll tailor your experience to your specific course.";
    
    const container = document.getElementById('university-custom-form');
    container.classList.remove('hidden');

    // Pre-fill School Name
    const schoolInput = document.getElementById('uni-school-name');
    if (schoolInput) schoolInput.value = this.profileData.schoolName || '';

    // Initialize custom subjects array if needed
    if (!Array.isArray(this.profileData.customSubjects)) {
        this.profileData.customSubjects = [];
    }

    const programInput = document.getElementById('uni-program-input');
    const subjectEntry = document.getElementById('uni-subject-entry');
    const addSubjectBtn = document.getElementById('add-uni-subject-btn');

    // Load saved program
    if (this.profileData.universityProgram) programInput.value = this.profileData.universityProgram;

    // Save Program on input
    programInput.addEventListener('input', () => {
        this.profileData.universityProgram = programInput.value;
        this.saveProgress();
    });

    // Render existing tags
    this.renderUniSubjects();

    // Add Subject Logic
    const addHandler = () => {
        const val = subjectEntry.value.trim();
        if (val) {
            this.addUniSubject(val);
            subjectEntry.value = '';
            subjectEntry.focus();
        }
    };

    addSubjectBtn.onclick = addHandler; // Use onclick to prevent multiple listeners
    
    // Enter key handler
    subjectEntry.onkeydown = (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            addHandler();
        }
    };
};

OnboardingWizard.prototype.addUniSubject = function(subject) {
    if (!this.profileData.customSubjects.includes(subject)) {
        this.profileData.customSubjects.push(subject);
        this.saveProgress();
        this.renderUniSubjects();
        
        // Clear error if exists
        const errorMsg = document.getElementById('screen3-error');
        if (errorMsg) errorMsg.classList.remove('show');
    }
};

OnboardingWizard.prototype.removeUniSubject = function(subject) {
    this.profileData.customSubjects = this.profileData.customSubjects.filter(s => s !== subject);
    this.saveProgress();
    this.renderUniSubjects();
};

OnboardingWizard.prototype.renderUniSubjects = function() {
    const list = document.getElementById('uni-subjects-list');
    list.innerHTML = '';

    this.profileData.customSubjects.forEach(subject => {
        const tag = document.createElement('div');
        tag.className = 'uni-subject-tag';
        tag.innerHTML = `
            ${subject}
            <div class="remove-tag" onclick="wizard.removeUniSubject('${subject.replace(/'/g, "\\'")}')">
                <i class="fas fa-times"></i>
            </div>
        `;
        list.appendChild(tag);
    });
};

/* ==================== 2. SHS MODE ==================== */
OnboardingWizard.prototype.initSHSMode = function() {
    document.getElementById('screen3').querySelector('h2').textContent = "What SHS Program Are You In?";
    document.getElementById('screen3').querySelector('.subtitle').textContent = "This helps us suggest relevant topics and track your progress.";

    const container = document.getElementById('shs-program-selector');
    container.classList.remove('hidden');

    this.renderSHSPrograms();
    
    // Change Program Button
    document.getElementById('shs-change-program-btn').addEventListener('click', () => {
        this.resetSHSSelection();
    });

    // Restore State
    if (this.profileData.shsProgram) {
        this.selectSHSProgram(this.profileData.shsProgram);
    } else {
        this.showSHSGrid();
    }
};

OnboardingWizard.prototype.renderSHSPrograms = function() {
    const grid = document.getElementById('shs-program-grid');
    grid.innerHTML = '';

    Object.entries(shsPrograms).forEach(([id, program]) => {
        const card = document.createElement('div');
        card.className = `shs-card ${program.styleClass}`;
        card.innerHTML = `
            <div class="shs-card-header">
                <div class="shs-icon-wrapper"><i class="fas ${program.icon}"></i></div>
                <h3>${program.name}</h3>
            </div>
            <p>${program.description}</p>
        `;
        card.addEventListener('click', () => this.selectSHSProgram(id));
        grid.appendChild(card);
    });
};

OnboardingWizard.prototype.selectSHSProgram = function(programId) {
    this.profileData.shsProgram = programId;
    this.saveProgress();

    const program = shsPrograms[programId];
    
    // Update Detail View
    document.getElementById('shs-selected-name').textContent = program.name;
    document.getElementById('shs-selected-desc').textContent = program.description;
    
    // Update Icon
    const iconWrapper = document.getElementById('shs-selected-icon');
    iconWrapper.innerHTML = `<i class="fas ${program.icon}"></i>`;
    iconWrapper.className = `shs-icon-wrapper ${program.styleClass}`; // apply color
    
    // Render Electives
    this.renderSHSElectives(program);

    // Swap Views
    document.getElementById('shs-program-grid').classList.add('hidden');
    document.getElementById('shs-program-detail').classList.remove('hidden');

    // Scroll top
    window.scrollTo(0,0);
};

OnboardingWizard.prototype.renderSHSElectives = function(program) {
    const grid = document.getElementById('shs-electives-grid');
    grid.innerHTML = '';
    
    // Ensure array exists
    if (!this.profileData.shsElectives) this.profileData.shsElectives = [];

    program.electives.forEach(elective => {
        const btn = document.createElement('button');
        btn.className = 'elective-btn';
        
        // Check if selected
        if (this.profileData.shsElectives.includes(elective.id)) {
            btn.classList.add('selected');
        }

        btn.innerHTML = `
            <div class="elective-checkbox"><i class="fas fa-check"></i></div>
            <span>${elective.name}</span>
        `;

        btn.addEventListener('click', () => this.toggleSHSElective(btn, elective.id));
        grid.appendChild(btn);
    });
};

OnboardingWizard.prototype.toggleSHSElective = function(btn, id) {
    const index = this.profileData.shsElectives.indexOf(id);
    if (index === -1) {
        this.profileData.shsElectives.push(id);
        btn.classList.add('selected');
    } else {
        this.profileData.shsElectives.splice(index, 1);
        btn.classList.remove('selected');
    }
    this.saveProgress();
};

OnboardingWizard.prototype.resetSHSSelection = function() {
    this.profileData.shsProgram = null;
    this.profileData.shsElectives = []; // Reset electives on program change? User flow decision. Yes generally.
    this.saveProgress();
    this.showSHSGrid();
};

OnboardingWizard.prototype.showSHSGrid = function() {
    document.getElementById('shs-program-detail').classList.add('hidden');
    document.getElementById('shs-program-grid').classList.remove('hidden');
};


/* ==================== 3. STANDARD MODE ==================== */
OnboardingWizard.prototype.initStandardMode = function() {
    document.getElementById('subject-grid').classList.remove('hidden');
    document.getElementById('subject-search').parentElement.classList.remove('hidden');

    // Standard Logic (Copied/Adapted from previous screen3.js)
    if (!this.profileData.subjects) this.profileData.subjects = [];

    const subjectCards = document.querySelectorAll('.subject-card');
    subjectCards.forEach(card => {
        // Clone to remove old listeners if re-initializing or just rely on CSS check
        // Ideally we shouldn't add duplicate listeners. 
        // Simple fix: check if valid handler attached? 
        // For now, simpler to just add click handler that checks logic.
        card.onclick = () => this.toggleSubject(card); 
    });

    // Restore Standard State
    if (this.profileData.subjects && this.profileData.subjects.length > 0) {
        this.profileData.subjects.forEach(subjectId => {
            const card = document.querySelector(`.subject-card[data-subject="${subjectId}"]`);
            if (card) card.classList.add('selected');
        });
    }
};

OnboardingWizard.prototype.toggleSubject = function(card) {
    const subjectId = card.dataset.subject;
    const index = this.profileData.subjects.indexOf(subjectId);

    if (index === -1) {
        this.profileData.subjects.push(subjectId);
        card.classList.add('selected');
    } else {
        this.profileData.subjects.splice(index, 1);
        card.classList.remove('selected');
    }
    this.saveProgress();
};


/* ==================== SAVE VALIDATION ==================== */
OnboardingWizard.prototype.saveSubjectsAndNext = function() {
    const errorMsg = document.getElementById('screen3-error');
    if(errorMsg) errorMsg.classList.remove('show');
    
    const level = this.profileData.educationLevel;

    // VALIDATION
    if (level === 'college') {
        // Validate University Fields
        if (!this.profileData.universityProgram || this.profileData.universityProgram.trim() === '') {
            this.showError(errorMsg, "Please enter your program or course of study.");
            return;
        }
        
        // Ensure customSubjects is an array and check length
        const subjects = this.profileData.customSubjects;
        if (!Array.isArray(subjects) || subjects.length === 0) {
            this.showError(errorMsg, "Please add at least one subject you need help with.");
            return;
        }
    } else if (level === 'high') {
         // Validate SHS
         if (!this.profileData.shsProgram) {
             this.showError(errorMsg, "Please select your SHS Program.");
             return;
         }
         if (!this.profileData.shsElectives || this.profileData.shsElectives.length === 0) {
             this.showError(errorMsg, "Please select at least one elective subject.");
             return;
         }
    } else {
        // Validate Standard
        if (this.profileData.subjects.length === 0) {
            this.showError(errorMsg, "Please select at least one subject to continue.");
            return;
        }
    }

    console.log('✅ Screen 3 Complete. Level:', level);
    this.nextScreen();
};

OnboardingWizard.prototype.showError = function(el, msg) {
    if (el) {
        el.textContent = msg;
        el.classList.add('show');
    } else {
        alert(msg);
    }
};
