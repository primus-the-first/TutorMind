/**
 * TutorMind Onboarding Bundle
 * Consolidates all logic, animations, and data for the interactive wizard.
 * 
 * Includes:
 * - Data (Universities, SHS Programs)
 * - Animation Logic (GSAP)
 * - State Management
 * - Screen Logic (1-9)
 */

/* ==================== 1. STATIC DATA ==================== */

const universityList = [
    "University of Ghana (UG) – Legon, Accra",
    "Kwame Nkrumah University of Science and Technology (KNUST) – Kumasi",
    "University of Cape Coast (UCC) – Cape Coast",
    "University of Education, Winneba (UEW) – Winneba",
    "University for Development Studies (UDS) – Tamale",
    "University of Mines and Technology (UMaT) – Tarkwa",
    "University of Health and Allied Sciences (UHAS) – Ho",
    "University of Energy and Natural Resources (UENR) – Sunyani",
    "University of Professional Studies, Accra (UPSA) – Legon, Accra",
    "Ghana Institute of Management and Public Administration (GIMPA) – Legon, Accra",
    "Ashesi University – Berekuso",
    "Central University – Miotso (Tema)",
    "Valley View University (VVU) – Oyibi",
    "Accra Institute of Technology (AIT) – Accra",
    "Academic City University College – Accra"
];

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
        { id: 'government', name: 'Government' },
        { id: 'french', name: 'French' }
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
        { id: 'elective-maths', name: 'Elective Mathematics' }
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
        { id: 'general-knowledge', name: 'General Knowledge in Art' }
      ]
    },
    'home-economics': {
      name: 'Home Economics',
      icon: 'fa-home',
      description: 'Food & Nutrition, Textiles, Management',
      styleClass: 'home-eco',
      electives: [
        { id: 'food-nutrition', name: 'Food & Nutrition' },
        { id: 'management-living', name: 'Management in Living' }
      ]
    },
    'technical': {
      name: 'Technical',
      icon: 'fa-tools',
      description: 'Building, Engineering, Electronics',
      styleClass: 'technical',
      electives: [
        { id: 'technical-drawing', name: 'Technical Drawing' },
        { id: 'electronics', name: 'Electronics' }
      ]
    }
};

/* ==================== 2. MAIN WIZARD CLASS ==================== */

class OnboardingWizard {
    constructor() {
        this.currentScreen = 1;
        this.totalScreens = 9;
        
        // Comprehensive State
        this.profileData = {
            subjects: [],
            primarySubject: null,
            shsProgram: null,
            shsElectives: [],
            universityProgram: null,
            customSubjects: [],
            learningGoal: null,
            educationLevel: null,
            schoolName: null,
            assessmentResults: {},
            studySchedule: null,
            sessionLength: null,
            explanationStyle: null,
            notificationsEnabled: true,
            notificationFrequency: null,
            notificationTime: null,
            firstLessonCompleted: false
        };

        this.assessmentState = {
            questions: [],
            currentQuestion: 0,
            answers: []
        };

        this.lessonState = {
            messages: [],
            currentProblem: null,
            isComplete: false
        };

        this.init();
    }

    init() {
        console.log('🧠 TutorMind Wizard Initialized');
        this.setupAnimations();
        this.setupEventListeners();
        this.loadProgress(); // Restore state if available
        this.showScreen(this.currentScreen);
    }

    /* ==================== NAVIGATION & ANIMATIONS ==================== */

    setupAnimations() {
        if (typeof gsap !== 'undefined') {
            // Neo-Brutalist animations: Sharp, snappy, with elastic overshoots
            gsap.defaults({ ease: "power3.out", duration: 0.35 });
        }
    }

    nextScreen() {
        if (this.currentScreen < this.totalScreens) {
            this.animateScreenExit(this.currentScreen, 'forward', () => {
                this.currentScreen++;
                this.showScreen(this.currentScreen, 'forward');
                this.saveProgress();
            });
        }
    }

    previousScreen() {
        if (this.currentScreen > 1) {
            this.animateScreenExit(this.currentScreen, 'backward', () => {
                this.currentScreen--;
                this.showScreen(this.currentScreen, 'backward');
            });
        }
    }

    showScreen(screenNumber, direction = 'forward') {
        // Hide all screens
        document.querySelectorAll('.screen').forEach(s => {
            s.classList.remove('active');
            s.style.display = 'none';
        });

        const target = document.getElementById(`screen${screenNumber}`);
        if (target) {
            target.style.display = 'block';
            target.classList.add('active');
            
            // Neo-Brutalist Slide Animation with slight bounce
            const xOffset = direction === 'backward' ? -60 : 60;
            gsap.fromTo(target, 
                { opacity: 0, x: xOffset, scale: 0.97 },
                { 
                    opacity: 1, 
                    x: 0, 
                    scale: 1,
                    duration: 0.45,
                    ease: "back.out(1.3)", // Elastic overshoot
                    clearProps: "all" 
                }
            );

            this.updateProgressBar(screenNumber);
            this.initializeScreenLogic(screenNumber);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    animateScreenExit(screenNumber, direction, callback) {
        const current = document.getElementById(`screen${screenNumber}`);
        if (current && typeof gsap !== 'undefined') {
            const xOffset = direction === 'forward' ? -40 : 40;
            gsap.to(current, {
                opacity: 0,
                x: xOffset,
                scale: 0.95,
                duration: 0.25,
                ease: "power2.in",
                onComplete: callback
            });
        } else {
            callback();
        }
    }

    updateProgressBar(screenNumber) {
        const progressBar = document.getElementById('wizard-progress-bar');
        const progressText = document.getElementById('wizard-progress-text');
        
        if (progressBar) {
            const percentage = (screenNumber / this.totalScreens) * 100;
            // Animate the progress bar growth
            gsap.to(progressBar, {
                width: `${percentage}%`,
                duration: 0.6,
                ease: "power2.out"
            });
        }
        if (progressText) {
            progressText.textContent = `${screenNumber} / ${this.totalScreens}`;
        }
    }

    /* ==================== SCREEN LOGIC ROUTES ==================== */

    initializeScreenLogic(screenNumber) {
        switch(screenNumber) {
            case 1: this.initWelcome(); break;
            case 2: this.initEducation(); break;
            case 3: this.initSubjects(); break;
            case 4: this.initGoals(); break;
            case 5: this.initAssessment(); break;
            case 6: this.initPreferences(); break;
            case 7: this.initNotifications(); break;
            case 8: this.initFirstLesson(); break;
            case 9: this.initSummary(); break;
        }
    }

    /* ==================== SCREEN 1: WELCOME ==================== */
    initWelcome() {
        // Reset and Animate Hero Elements
        gsap.from('#screen1 h1', { y: 30, opacity: 0, delay: 0.1 });
        gsap.from('#screen1 .subtitle', { y: 20, opacity: 0, delay: 0.2 });
        
        // Floating Icons
        gsap.from('.hero-icon', { 
            scale: 0, opacity: 0, stagger: 0.1, duration: 0.6, ease: "back.out(1.7)" 
        });
        
        // Continuous float
        gsap.to('.hero-icon', { y: -10, duration: 2, repeat: -1, yoyo: true, ease: 'sine.inOut', stagger: 0.2 });

        // Pulse Button
        gsap.to('#get-started-btn', { 
            boxShadow: '0 0 25px rgba(245, 158, 11, 0.6)', 
            repeat: -1, yoyo: true, duration: 1.5 
        });

        document.getElementById('get-started-btn').onclick = () => this.nextScreen();
    }

    /* ==================== SCREEN 2: EDUCATION ==================== */
    initEducation() {
        const inputContainer = document.getElementById('school-input-container');
        const schoolInput = document.getElementById('school-name-input');
        const label = document.getElementById('school-input-label');

        // Populate Datalist
        const datalist = document.getElementById('university-list');
        if (datalist && datalist.options.length === 0) {
            universityList.forEach(uni => {
                const opt = document.createElement('option');
                opt.value = uni;
                datalist.appendChild(opt);
            });
        }

        // Card Selection with Bounce Animation
        document.querySelectorAll('.education-card').forEach(card => {
            card.onclick = () => {
                // Remove selection from others
                document.querySelectorAll('.education-card').forEach(c => {
                    c.classList.remove('selected');
                    gsap.to(c, { scale: 1, duration: 0.2 });
                });
                
                // Select and animate this card
                card.classList.add('selected');
                gsap.fromTo(card, 
                    { scale: 1 },
                    { scale: 1.05, duration: 0.15, yoyo: true, repeat: 1, ease: "power1.inOut" }
                );
                
                const level = card.dataset.level;
                this.profileData.educationLevel = level;
                
                // Show Input with slide-down
                inputContainer.classList.remove('hidden');
                gsap.from(inputContainer, { 
                    height: 0, 
                    opacity: 0, 
                    duration: 0.4,
                    ease: "power2.out"
                });

                // Configure Input text
                if (level === 'college') {
                    label.textContent = "Which university do you attend?";
                    schoolInput.setAttribute('list', 'university-list');
                    schoolInput.placeholder = "Search universities...";
                } else if (level === 'high') {
                    label.textContent = "What is the name of your school?";
                    schoolInput.removeAttribute('list');
                    schoolInput.placeholder = "Enter school name...";
                } else {
                    label.textContent = "Institution or Organization (Optional)";
                    schoolInput.removeAttribute('list');
                }
                
                this.saveProgress();
            };
        });

        // Input Handling
        if (schoolInput) {
            schoolInput.oninput = (e) => {
                this.profileData.schoolName = e.target.value;
                this.saveProgress();
            };
        }

        document.getElementById('screen2-next-btn').onclick = () => {
            if (!this.profileData.educationLevel) {
                this.showError('screen2-error', 'Please select an education level.');
                return;
            }
            if (['high', 'college'].includes(this.profileData.educationLevel) && !this.profileData.schoolName) {
                this.showError('screen2-error', 'Please enter your school name.');
                return;
            }
            this.nextScreen();
        };

        // Restore State
        if (this.profileData.educationLevel) {
            const savedCard = document.querySelector(`.education-card[data-level="${this.profileData.educationLevel}"]`);
            if (savedCard) savedCard.click(); // Trigger logic
            if (this.profileData.schoolName) schoolInput.value = this.profileData.schoolName;
        }
    }

    /* ==================== SCREEN 3: SUBJECTS ==================== */
    initSubjects() {
        const level = this.profileData.educationLevel;
        
        // Hide all sub-modes initially
        ['subject-grid', 'shs-program-selector', 'university-custom-form'].forEach(id => {
            document.getElementById(id).classList.add('hidden');
        });

        // Get heading and search elements
        const heading = document.querySelector('#screen3 h2');
        const subtitle = document.querySelector('#screen3 .subtitle');
        const searchContainer = document.querySelector('.subject-search-container');

        if (level === 'high') {
            this.initSHSMode();
            // Update heading for SHS
            heading.textContent = "What SHS Program Are You In?";
            subtitle.textContent = "This helps us suggest relevant topics and track your progress.";
            searchContainer.classList.add('hidden'); // Hide search for SHS
        } else if (level === 'college') {
            this.initUniMode();
            // Update heading for University
            heading.textContent = "What are you studying at university?";
            subtitle.textContent = "Tell us about your program and the subjects you need help with.";
            searchContainer.classList.add('hidden'); // Hide search for University
        } else {
            this.initStandardSubjectMode();
            // Keep default heading
            heading.textContent = "Which subjects do you want help with?";
            subtitle.textContent = "Don't worry, you can add or change these anytime.";
            searchContainer.classList.remove('hidden'); // Show search for standard
        }

        document.getElementById('subjects-continue-btn').onclick = () => this.validateAndNextSubject();
    }

    initSHSMode() {
        document.getElementById('shs-program-selector').classList.remove('hidden');
        const grid = document.getElementById('shs-program-grid');
        grid.innerHTML = '';
        
        Object.entries(shsPrograms).forEach(([id, prog]) => {
            const card = document.createElement('div');
            card.className = `shs-card ${prog.styleClass}`;
            card.innerHTML = `<div class="shs-icon-wrapper"><i class="fas ${prog.icon}"></i></div><h3>${prog.name}</h3>`;
            card.onclick = () => this.selectSHSProgram(id);
            grid.appendChild(card);
        });

        // Restore
        if (this.profileData.shsProgram) this.selectSHSProgram(this.profileData.shsProgram);
    }

    selectSHSProgram(id) {
        this.profileData.shsProgram = id;
        document.getElementById('shs-program-grid').classList.add('hidden');
        
        const detail = document.getElementById('shs-program-detail');
        detail.classList.remove('hidden');
        
        const program = shsPrograms[id];
        document.getElementById('shs-selected-name').textContent = program.name;
        document.getElementById('shs-selected-desc').textContent = program.description;
        
        // Render Electives
        const elecGrid = document.getElementById('shs-electives-grid');
        elecGrid.innerHTML = '';
        program.electives.forEach(elec => {
            const btn = document.createElement('button');
            btn.className = `elective-btn ${this.profileData.shsElectives.includes(elec.id) ? 'selected' : ''}`;
            btn.textContent = elec.name;
            btn.onclick = () => {
                btn.classList.toggle('selected');
                if (btn.classList.contains('selected')) {
                    this.profileData.shsElectives.push(elec.id);
                } else {
                    this.profileData.shsElectives = this.profileData.shsElectives.filter(x => x !== elec.id);
                }
                this.saveProgress();
            };
            elecGrid.appendChild(btn);
        });

        document.getElementById('shs-change-program-btn').onclick = () => {
            document.getElementById('shs-program-grid').classList.remove('hidden');
            detail.classList.add('hidden');
        };
    }

    initUniMode() {
        const container = document.getElementById('university-custom-form');
        container.classList.remove('hidden');
        document.getElementById('uni-school-name').value = this.profileData.schoolName || '';
        
        const subInput = document.getElementById('uni-subject-entry');
        const addBtn = document.getElementById('add-uni-subject-btn');
        const list = document.getElementById('uni-subjects-list');

        const renderTags = () => {
            list.innerHTML = '';
            this.profileData.customSubjects.forEach(sub => {
                const tag = document.createElement('div');
                tag.className = 'uni-subject-tag';
                tag.innerHTML = `${sub} <i class="fas fa-times"></i>`;
                tag.querySelector('i').onclick = () => {
                    this.profileData.customSubjects = this.profileData.customSubjects.filter(s => s !== sub);
                    renderTags();
                };
                list.appendChild(tag);
            });
        };

        const addSubject = () => {
            if (subInput.value.trim()) {
                this.profileData.customSubjects.push(subInput.value.trim());
                subInput.value = '';
                renderTags();
                this.saveProgress();
            }
        };

        addBtn.onclick = addSubject;
        subInput.onkeydown = (e) => { if (e.key === 'Enter') addSubject(); };
        
        renderTags();
    }

    initStandardSubjectMode() {
        document.getElementById('subject-grid').classList.remove('hidden');
        document.querySelectorAll('.subject-card').forEach(card => {
            if (this.profileData.subjects.includes(card.dataset.subject)) card.classList.add('selected');
            
            card.onclick = () => {
                card.classList.toggle('selected');
                const sub = card.dataset.subject;
                if (card.classList.contains('selected')) this.profileData.subjects.push(sub);
                else this.profileData.subjects = this.profileData.subjects.filter(s => s !== sub);
                this.saveProgress();
            };
        });
    }

    validateAndNextSubject() {
        // Logic to validate based on mode
        const level = this.profileData.educationLevel;
        let valid = false;
        
        if (level === 'high') valid = this.profileData.shsProgram && this.profileData.shsElectives.length > 0;
        else if (level === 'college') valid = this.profileData.customSubjects.length > 0;
        else valid = this.profileData.subjects.length > 0;

        if (!valid) this.showError('screen3-error', 'Please complete the selection.');
        else this.nextScreen();
    }

    /* ==================== SCREEN 4: GOALS ==================== */
    initGoals() {
        document.querySelectorAll('.goal-card').forEach(card => {
            if (this.profileData.learningGoal === card.dataset.goal) card.classList.add('selected');
            
            card.onclick = () => {
                document.querySelectorAll('.goal-card').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                this.profileData.learningGoal = card.dataset.goal;
                this.saveProgress();
            };
        });

        document.getElementById('goals-continue-btn').onclick = () => {
            if (this.profileData.learningGoal) this.nextScreen();
            else this.showError('screen4-error', 'Please select a goal.');
        };
    }

    /* ==================== SCREEN 5: ASSESSMENT ==================== */
    initAssessment() {
        const loading = document.getElementById('assessment-loading');
        const content = document.getElementById('assessment-content');
        
        loading.classList.remove('hidden');
        content.classList.add('hidden');

        // Mock Question Generation
        setTimeout(() => {
            loading.classList.add('hidden');
            content.classList.remove('hidden');
            gsap.from(content, { opacity: 0, y: 20 });
            
            this.assessmentState.questions = [
                { text: "What is 12 x 12?", options: ["124", "144", "122"], correct: 1 },
                { text: "The powerhouse of the cell is...", options: ["Nucleus", "Mitochondria", "Ribosome"], correct: 1 },
                { text: "Solve for x: 2x = 10", options: ["2", "5", "10"], correct: 1 }
            ];
            this.renderQuestion();
        }, 1500);

        document.getElementById('assessment-continue-btn').onclick = () => this.nextQuestion();
    }

    renderQuestion() {
        const q = this.assessmentState.questions[this.assessmentState.currentQuestion];
        const totalQuestions = this.assessmentState.questions.length;
        
        document.getElementById('question-text').textContent = q.text;
        document.getElementById('current-q-num').textContent = this.assessmentState.currentQuestion + 1;
        document.getElementById('total-q-num').textContent = totalQuestions; // Update total dynamically
        
        const container = document.getElementById('options-container');
        container.innerHTML = '';
        q.options.forEach((opt, idx) => {
            const btn = document.createElement('button');
            btn.className = 'option-btn';
            btn.textContent = opt;
            btn.onclick = () => {
                document.querySelectorAll('.option-btn').forEach(b => b.classList.remove('selected'));
                btn.classList.add('selected');
                this.assessmentState.tempAnswer = idx;
                document.getElementById('assessment-continue-btn').classList.remove('disabled');
            };
            container.appendChild(btn);
        });
        document.getElementById('assessment-continue-btn').classList.add('disabled');
    }

    nextQuestion() {
        // Save Answer Logic Mock
        this.assessmentState.answers.push(this.assessmentState.tempAnswer);
        
        if (this.assessmentState.currentQuestion < this.assessmentState.questions.length - 1) {
            this.assessmentState.currentQuestion++;
            this.renderQuestion();
        } else {
            this.nextScreen();
        }
    }

    skipAssessment() {
        console.log('⏭️ Assessment skipped');
        this.nextScreen(); // Jump to preferences
    }

    /* ==================== SCREEN 6: PREFERENCES ==================== */
    initPreferences() {
        const bindSelect = (selector, key) => {
            document.querySelectorAll(selector).forEach(el => {
                if (this.profileData[key] === el.dataset.value) el.classList.add('selected');
                el.onclick = () => {
                    document.querySelectorAll(selector).forEach(x => x.classList.remove('selected'));
                    el.classList.add('selected');
                    this.profileData[key] = el.dataset.value;
                    this.checkPreferences();
                    this.saveProgress();
                };
            });
        };

        bindSelect('.schedule-option', 'studySchedule');
        bindSelect('.duration-option', 'sessionLength');
        bindSelect('.style-card', 'explanationStyle');

        document.getElementById('preferences-continue-btn').onclick = () => this.nextScreen();
        this.checkPreferences();
    }

    checkPreferences() {
        const { studySchedule, sessionLength, explanationStyle } = this.profileData;
        const btn = document.getElementById('preferences-continue-btn');
        if (studySchedule && sessionLength && explanationStyle) btn.classList.remove('disabled');
        else btn.classList.add('disabled');
    }

    /* ==================== SCREEN 7: NOTIFICATIONS ==================== */
    initNotifications() {
        const toggle = document.getElementById('notification-toggle');
        const timeInput = document.getElementById('notification-time');
        
        const updateUI = () => {
            if (this.profileData.notificationsEnabled) {
                toggle.classList.add('active');
                document.getElementById('notification-settings-container').classList.remove('hidden');
            } else {
                toggle.classList.remove('active');
                document.getElementById('notification-settings-container').classList.add('hidden');
            }
        };

        toggle.onclick = () => {
            this.profileData.notificationsEnabled = !this.profileData.notificationsEnabled;
            updateUI();
        };

        document.getElementById('notifications-continue-btn').onclick = () => {
            if (this.profileData.notificationsEnabled) {
                 // Mock Permission Request
                 if ('Notification' in window) Notification.requestPermission();
            }
            this.nextScreen();
        };

        document.getElementById('notifications-skip-btn').onclick = () => {
            this.profileData.notificationsEnabled = false;
            this.nextScreen();
        };
        
        updateUI();
    }

    /* ==================== SCREEN 8: FIRST LESSON ==================== */
    initFirstLesson() {
        const chat = document.getElementById('lesson-chat-container');
        chat.innerHTML = '';
        this.addChat('ai', "Hi there! Let's solve a quick problem to get started.", 500);
        this.addChat('ai', "What is the capital of France?", 1500);

        const opts = document.getElementById('lesson-options');
        opts.innerHTML = '';
        setTimeout(() => {
            ['Berlin', 'Madrid', 'Paris'].forEach(city => {
                const btn = document.createElement('button');
                btn.className = 'lesson-option-btn';
                btn.textContent = city;
                btn.onclick = () => {
                    this.addChat('user', city);
                    opts.classList.add('hidden');
                    if (city === 'Paris') {
                        this.addChat('ai', "Correct! Paris is the capital.", 1000);
                        setTimeout(() => {
                            document.getElementById('lesson-continue-btn').classList.remove('hidden');
                        }, 2000);
                    } else {
                        this.addChat('ai', "Not quite. Try again!", 1000);
                        setTimeout(() => opts.classList.remove('hidden'), 2000);
                    }
                };
                opts.appendChild(btn);
            });
            opts.classList.remove('hidden');
        }, 2000);
    }

    addChat(role, text, delay = 0) {
        setTimeout(() => {
            const div = document.createElement('div');
            div.className = `chat-message ${role}`;
            div.textContent = text;
            document.getElementById('lesson-chat-container').appendChild(div);
            div.scrollIntoView({ behavior: 'smooth' });
        }, delay);
    }

    /* ==================== SCREEN 9: SUMMARY ==================== */
    initSummary() {
        const container = document.getElementById('summary-content');
        const p = this.profileData;
        
        container.innerHTML = `
            <div class="summary-card">
                <h3>Ready to Learn! 🚀</h3>
                <p><strong>Focus:</strong> ${p.educationLevel}</p>
                <p><strong>Goal:</strong> ${p.learningGoal || 'General Learning'}</p>
                <p><strong>Style:</strong> ${p.explanationStyle || 'Adaptive'}</p>
            </div>
        `;
    }

    /* ==================== UTILS ==================== */
    saveProgress() {
        const data = {
            currentScreen: this.currentScreen,
            profileData: this.profileData
        };
        localStorage.setItem('tutormind_wizard_v2', JSON.stringify(data));
    }

     loadProgress() {
        // Disabled for dev testing to always start fresh or uncomment to enable
        // const saved = localStorage.getItem('tutormind_wizard_v2');
        // if (saved) { ... }
    }

    setupEventListeners() {
        // Global keyboard navigation
        document.addEventListener('keydown', (e) => {
            // Escape to go back (except on critical screens)
            if (e.key === 'Escape' && this.currentScreen > 1 && 
                this.currentScreen !== 5 && this.currentScreen !== 8) {
                this.previousScreen();
            }
        });

        // Prevent accidental page refresh during onboarding
        window.addEventListener('beforeunload', (e) => {
            if (this.currentScreen > 1 && this.currentScreen < this.totalScreens) {
                e.preventDefault();
                e.returnValue = 'Your progress is saved. Are you sure you want to leave?';
                return e.returnValue;
            }
        });
    }

    showError(id, msg) {
        const el = document.getElementById(id);
        if (el && typeof gsap !== 'undefined') {
            el.textContent = msg;
            el.classList.add('show');
            // Shake animation for error feedback
            gsap.fromTo(el, 
                { x: 0 },
                { x: 10, duration: 0.05, yoyo: true, repeat: 5, ease: "power1.inOut" }
            );
        } else if (el) {
            el.textContent = msg;
            el.classList.add('show');
        } else {
            alert(msg);
        }
    }

    async completeOnboarding() {
        console.log('🎓 Completing onboarding...');
        
        // Show loading state
        const btn = document.querySelector('.btn-primary.btn-large');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        }

        try {
            const response = await fetch('api/user_onboarding.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(this.profileData)
            });

            const result = await response.json();

            if (result.success) {
                console.log('✅ Profile saved successfully');
                
                // Clear progress cache
                localStorage.removeItem('tutormind_wizard_v2');
                
                // Redirect to dashboard
                window.location.href = 'chat';
            } else {
                throw new Error(result.error || 'Failed to save profile');
            }
        } catch (error) {
            console.error('❌ Onboarding completion error:', error);
            
            // Reset button
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = 'Go to Dashboard <i class="fas fa-rocket"></i>';
            }
            
            alert('There was an error saving your profile. Please try again or contact support.');
        }
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    window.wizard = new OnboardingWizard();
});
