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
            answers: [],
            correctCount: 0
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
    getAssessmentQuestions() {
        const questionBank = {
            // ── SHS / High School ────────────────────────────────────────────
            'mathematics': [
                { text: "Solve for x: 2x + 4 = 14", options: ["4", "5", "6", "9"], correct: 1 },
                { text: "What is the slope of the line y = 3x - 7?", options: ["7", "-7", "3", "-3"], correct: 2 },
                { text: "What is 15% of 200?", options: ["20", "25", "30", "35"], correct: 2 }
            ],
            'science': [
                { text: "What is the chemical symbol for water?", options: ["WO", "H2O", "HO2", "W2O"], correct: 1 },
                { text: "How many planets are in our solar system?", options: ["7", "8", "9", "10"], correct: 1 },
                { text: "What force keeps planets in orbit around the sun?", options: ["Magnetism", "Friction", "Gravity", "Electricity"], correct: 2 }
            ],
            'languages': [
                { text: "Which sentence is grammatically correct?", options: ["She don't like it.", "She doesn't likes it.", "She doesn't like it.", "She not like it."], correct: 2 },
                { text: "What is the past tense of 'write'?", options: ["writed", "wrote", "written", "writ"], correct: 1 },
                { text: "Which word is a synonym for 'happy'?", options: ["Sad", "Angry", "Joyful", "Tired"], correct: 2 }
            ],
            'computer-science': [
                { text: "What does 'HTML' stand for?", options: ["HyperText Markup Language", "High-Tech Machine Logic", "HyperText Machine Language", "High Transfer Markup Language"], correct: 0 },
                { text: "Which of these is a programming language?", options: ["Excel", "Python", "Photoshop", "Chrome"], correct: 1 },
                { text: "What is the result of 5 % 2 in most programming languages?", options: ["2", "2.5", "1", "0"], correct: 2 }
            ],
            'social-studies': [
                { text: "What is the capital of France?", options: ["Berlin", "Madrid", "Rome", "Paris"], correct: 3 },
                { text: "Which document declared American independence?", options: ["The Constitution", "The Magna Carta", "The Declaration of Independence", "The Bill of Rights"], correct: 2 },
                { text: "What is an economy based primarily on services called?", options: ["Agricultural economy", "Industrial economy", "Service economy", "Barter economy"], correct: 2 }
            ],
            'business': [
                { text: "What does 'debit' mean in accounting?", options: ["Money owed to others", "An entry that increases assets or expenses", "A reduction in revenue", "Money earned from sales"], correct: 1 },
                { text: "What is the accounting equation?", options: ["Revenue = Expenses + Profit", "Assets = Liabilities + Equity", "Profit = Revenue - Assets", "Equity = Assets + Liabilities"], correct: 1 },
                { text: "What does GDP stand for?", options: ["Gross Domestic Product", "General Demand Price", "Gross Debt Percentage", "Government Data Program"], correct: 0 }
            ],
            'general-science': [
                { text: "What is the powerhouse of the cell?", options: ["Nucleus", "Ribosome", "Mitochondria", "Vacuole"], correct: 2 },
                { text: "What is the atomic number of Carbon?", options: ["4", "6", "8", "12"], correct: 1 },
                { text: "Which law states that force equals mass times acceleration?", options: ["Newton's 1st Law", "Newton's 2nd Law", "Newton's 3rd Law", "Hooke's Law"], correct: 1 }
            ],
            'visual-arts': [
                { text: "Which are the primary colors?", options: ["Red, Green, Blue", "Red, Yellow, Blue", "Orange, Purple, Green", "Red, White, Blue"], correct: 1 },
                { text: "What technique involves applying thick paint to create texture?", options: ["Watercolor wash", "Impasto", "Glazing", "Stippling"], correct: 1 },
                { text: "Who painted the Mona Lisa?", options: ["Michelangelo", "Raphael", "Leonardo da Vinci", "Donatello"], correct: 2 }
            ],
            'home-economics': [
                { text: "Which nutrient provides the most energy per gram?", options: ["Protein", "Carbohydrates", "Fat", "Vitamins"], correct: 2 },
                { text: "What is the safe internal temperature for cooked chicken?", options: ["60°C / 140°F", "74°C / 165°F", "90°C / 194°F", "50°C / 122°F"], correct: 1 },
                { text: "Which stitch is most commonly used to start hand sewing?", options: ["Running stitch", "Backstitch", "Slip stitch", "Blanket stitch"], correct: 1 }
            ],
            // ── University / Postgraduate ─────────────────────────────────────
            'uni-accounting': [
                { text: "Under IFRS, how are financial assets measured by default?", options: ["At historical cost", "At fair value", "At replacement cost", "At book value"], correct: 1 },
                { text: "Which concept states that a business is separate from its owner?", options: ["Going concern", "Prudence", "Business entity", "Matching"], correct: 2 },
                { text: "What does a Statement of Cash Flows NOT include?", options: ["Operating activities", "Investing activities", "Financing activities", "Budgeting activities"], correct: 3 }
            ],
            'uni-finance': [
                { text: "What does NPV stand for?", options: ["Net Present Value", "Net Profit Volume", "Nominal Price Value", "Net Price Variance"], correct: 0 },
                { text: "A bond trading above its face value is said to be trading at a:", options: ["Discount", "Premium", "Par", "Deficit"], correct: 1 },
                { text: "Which measure captures the sensitivity of a bond's price to interest rate changes?", options: ["Beta", "Duration", "Yield", "Coupon rate"], correct: 1 }
            ],
            'uni-economics': [
                { text: "When demand falls as income rises, the good is called:", options: ["Normal good", "Inferior good", "Giffen good", "Luxury good"], correct: 1 },
                { text: "Which market structure has a single seller with no close substitutes?", options: ["Oligopoly", "Monopolistic competition", "Monopoly", "Perfect competition"], correct: 2 },
                { text: "The Phillips Curve describes the relationship between:", options: ["Inflation and GDP", "Unemployment and inflation", "Interest rates and savings", "Trade deficit and currency value"], correct: 1 }
            ],
            'uni-business': [
                { text: "What does a SWOT analysis examine?", options: ["Sales, Wages, Output, Targets", "Strengths, Weaknesses, Opportunities, Threats", "Strategy, Work, Operations, Technology", "Systems, Workflow, Objectives, Tasks"], correct: 1 },
                { text: "Which leadership style involves employees in decision-making?", options: ["Autocratic", "Laissez-faire", "Transactional", "Democratic"], correct: 3 },
                { text: "Porter's Five Forces does NOT include:", options: ["Threat of new entrants", "Buyer power", "Employee satisfaction", "Competitive rivalry"], correct: 2 }
            ],
            'uni-cs': [
                { text: "What is the time complexity of binary search?", options: ["O(n)", "O(n²)", "O(log n)", "O(1)"], correct: 2 },
                { text: "Which data structure uses LIFO (Last In, First Out)?", options: ["Queue", "Stack", "Linked list", "Tree"], correct: 1 },
                { text: "What does SQL stand for?", options: ["Structured Query Language", "Simple Query Logic", "Standard Queue Language", "Sequential Query Layer"], correct: 0 }
            ],
            'uni-engineering': [
                { text: "What is Ohm's Law?", options: ["V = IR", "P = mv²", "F = ma", "E = mc²"], correct: 0 },
                { text: "Which material has the highest electrical conductivity?", options: ["Iron", "Aluminium", "Silver", "Copper"], correct: 2 },
                { text: "What does CAD stand for in engineering?", options: ["Computer-Aided Design", "Circuit Analysis Diagram", "Calculated Axial Displacement", "Component Array Database"], correct: 0 }
            ],
            'uni-medicine': [
                { text: "Which organ produces insulin?", options: ["Liver", "Kidney", "Pancreas", "Spleen"], correct: 2 },
                { text: "What is the normal resting heart rate range for adults?", options: ["40–60 bpm", "60–100 bpm", "100–120 bpm", "120–140 bpm"], correct: 1 },
                { text: "Which blood type is the universal donor?", options: ["A+", "B+", "AB+", "O−"], correct: 3 }
            ],
            'uni-law': [
                { text: "What does 'mens rea' mean in criminal law?", options: ["The guilty act", "The guilty mind", "The victim's intent", "The court's finding"], correct: 1 },
                { text: "Which source of law carries the highest authority in most countries?", options: ["Case law", "Statute law", "The constitution", "Customary law"], correct: 2 },
                { text: "What is the standard of proof in civil cases?", options: ["Beyond reasonable doubt", "Balance of probabilities", "Clear and convincing evidence", "Absolute certainty"], correct: 1 }
            ],
            'uni-psychology': [
                { text: "Who is known as the father of psychoanalysis?", options: ["Carl Jung", "B.F. Skinner", "Sigmund Freud", "William James"], correct: 2 },
                { text: "Classical conditioning was demonstrated using a dog by:", options: ["Freud", "Pavlov", "Skinner", "Bandura"], correct: 1 },
                { text: "Maslow's hierarchy places which need at the top?", options: ["Safety", "Love and belonging", "Esteem", "Self-actualization"], correct: 3 }
            ],
            'uni-biology': [
                { text: "What is the basic unit of heredity?", options: ["Cell", "Chromosome", "Gene", "Protein"], correct: 2 },
                { text: "During which phase of mitosis do chromosomes line up at the cell's equator?", options: ["Prophase", "Anaphase", "Telophase", "Metaphase"], correct: 3 },
                { text: "Which molecule carries genetic information from DNA to the ribosome?", options: ["tRNA", "mRNA", "rRNA", "siRNA"], correct: 1 }
            ],
            'uni-chemistry': [
                { text: "What is the pH of a neutral solution at 25°C?", options: ["0", "7", "14", "10"], correct: 1 },
                { text: "Which type of bond involves the sharing of electrons?", options: ["Ionic bond", "Hydrogen bond", "Covalent bond", "Metallic bond"], correct: 2 },
                { text: "What is the molar mass of water (H₂O)?", options: ["16 g/mol", "18 g/mol", "20 g/mol", "22 g/mol"], correct: 1 }
            ],
            'uni-history': [
                { text: "Which event triggered the start of World War I?", options: ["The invasion of Poland", "The assassination of Archduke Franz Ferdinand", "The sinking of the Lusitania", "The bombing of Pearl Harbor"], correct: 1 },
                { text: "The Cold War was primarily between which two superpowers?", options: ["USA and China", "UK and USSR", "USA and USSR", "China and USA"], correct: 2 },
                { text: "Which African country was never colonized by a European power?", options: ["Ghana", "Nigeria", "Ethiopia", "Kenya"], correct: 2 }
            ],
            'uni-education': [
                { text: "Bloom's Taxonomy categorizes learning into how many levels?", options: ["4", "5", "6", "7"], correct: 2 },
                { text: "Which learning theory is based on observable behavior changes?", options: ["Constructivism", "Cognitivism", "Behaviourism", "Humanism"], correct: 2 },
                { text: "What does IEP stand for in special education?", options: ["Individual Education Plan", "Integrated Education Program", "Instructional Evaluation Process", "Inclusive Engagement Practice"], correct: 0 }
            ],
            // ── Aptitude fallback ─────────────────────────────────────────────
            'aptitude': [
                { text: "If a train travels 120 km in 2 hours, what is its average speed?", options: ["40 km/h", "50 km/h", "60 km/h", "80 km/h"], correct: 2 },
                { text: "Which number comes next in the series: 2, 4, 8, 16, ___?", options: ["24", "28", "30", "32"], correct: 3 },
                { text: "A is taller than B. B is taller than C. Who is the shortest?", options: ["A", "B", "C", "Cannot be determined"], correct: 2 },
                { text: "What is 30% of 150?", options: ["35", "40", "45", "50"], correct: 2 },
                { text: "If all roses are flowers and some flowers fade quickly, which statement must be true?", options: ["All roses fade quickly", "Some roses may fade quickly", "No roses fade quickly", "All flowers are roses"], correct: 1 }
            ]
        };

        // ── Keyword map for free-text university subjects ─────────────────────
        const uniKeywords = [
            { keys: ['accounting', 'financial account', 'auditing', 'taxation', 'bookkeep'], bank: 'uni-accounting' },
            { keys: ['finance', 'investment', 'banking', 'portfolio', 'asset management', 'corporate finance'], bank: 'uni-finance' },
            { keys: ['economics', 'microeconomics', 'macroeconomics', 'econometrics'], bank: 'uni-economics' },
            { keys: ['business', 'management', 'marketing', 'entrepreneurship', 'human resource', 'supply chain', 'logistics', 'mba'], bank: 'uni-business' },
            { keys: ['computer', 'software', 'programming', 'data science', 'cybersecurity', 'artificial intelligence', 'machine learning', 'it ', 'information technology', 'networking'], bank: 'uni-cs' },
            { keys: ['engineering', 'mechanical', 'electrical', 'civil', 'chemical engineering', 'aerospace', 'biomedical'], bank: 'uni-engineering' },
            { keys: ['medicine', 'medical', 'nursing', 'pharmacy', 'pharmacology', 'anatomy', 'physiology', 'dentistry', 'public health'], bank: 'uni-medicine' },
            { keys: ['law', 'legal', 'jurisprudence', 'criminology'], bank: 'uni-law' },
            { keys: ['psychology', 'counselling', 'psychiatry', 'neuroscience', 'behavioral'], bank: 'uni-psychology' },
            { keys: ['biology', 'microbiology', 'genetics', 'ecology', 'zoology', 'botany', 'biochemistry'], bank: 'uni-biology' },
            { keys: ['chemistry', 'organic chemistry', 'inorganic', 'analytical chemistry'], bank: 'uni-chemistry' },
            { keys: ['history', 'political science', 'international relations', 'geography', 'sociology', 'anthropology'], bank: 'uni-history' },
            { keys: ['education', 'pedagogy', 'curriculum', 'teaching', 'early childhood'], bank: 'uni-education' },
            { keys: ['mathematics', 'statistics', 'calculus', 'algebra', 'actuarial'], bank: 'mathematics' },
            { keys: ['literature', 'linguistics', 'english', 'communication', 'journalism', 'french', 'spanish'], bank: 'languages' },
            { keys: ['physics', 'astrophysics', 'thermodynamics', 'quantum'], bank: 'general-science' },
        ];

        const level = this.profileData.educationLevel;
        const shsProgram = this.profileData.shsProgram;
        const shsElectives = this.profileData.shsElectives || [];
        const subjects = this.profileData.subjects || [];
        const customSubjects = this.profileData.customSubjects || [];

        // ── University / College / Postgraduate path ──────────────────────────
        if (level === 'college' && customSubjects.length > 0) {
            const combined = customSubjects.join(' ').toLowerCase();
            for (const { keys, bank } of uniKeywords) {
                if (keys.some(k => combined.includes(k))) return questionBank[bank];
            }
            return questionBank['aptitude'];
        }

        // ── SHS path ──────────────────────────────────────────────────────────
        if (shsProgram === 'business' || shsElectives.includes('financial-accounting') || shsElectives.includes('business-management')) {
            return questionBank['business'];
        }
        if (shsProgram && questionBank[shsProgram]) {
            return questionBank[shsProgram];
        }

        // ── Standard subject grid (elementary / middle / other) ───────────────
        const gridMap = {
            'mathematics': 'mathematics',
            'science': 'science',
            'languages': 'languages',
            'computer-science': 'computer-science',
            'social-studies': 'social-studies'
        };
        for (const sub of subjects) {
            if (gridMap[sub]) return questionBank[gridMap[sub]];
        }

        // ── Adult learner or anything unrecognized → aptitude ─────────────────
        return questionBank['aptitude'];
    }

    initAssessment() {
        const loading = document.getElementById('assessment-loading');
        const content = document.getElementById('assessment-content');

        loading.classList.remove('hidden');
        content.classList.add('hidden');

        setTimeout(() => {
            loading.classList.add('hidden');
            content.classList.remove('hidden');
            gsap.from(content, { opacity: 0, y: 20 });

            this.assessmentState.questions = this.getAssessmentQuestions();
            this.assessmentState.currentQuestion = 0;
            this.assessmentState.answers = [];
            this.assessmentState.correctCount = 0;
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
        const state = this.assessmentState;
        const q = state.questions[state.currentQuestion];
        const isCorrect = state.tempAnswer === q.correct;

        state.answers.push({ answerIdx: state.tempAnswer, correct: isCorrect });
        if (isCorrect) state.correctCount = (state.correctCount || 0) + 1;

        if (state.currentQuestion < state.questions.length - 1) {
            state.currentQuestion++;
            this.renderQuestion();
        } else {
            this.showAssessmentResult();
        }
    }

    showAssessmentResult() {
        const state = this.assessmentState;
        const total = state.questions.length;
        const correct = state.correctCount || 0;
        const pct = correct / total;

        let level, label, icon, message;
        if (pct >= 0.67) {
            level = 'advanced';
            label = 'Advanced';
            icon = '🏆';
            message = "Excellent! You have a strong foundation. We'll challenge you with deeper concepts right away.";
        } else if (pct >= 0.34) {
            level = 'intermediate';
            label = 'Intermediate';
            icon = '📈';
            message = "Good start! You know the basics. We'll build on that and fill in the gaps.";
        } else {
            level = 'beginner';
            label = 'Beginner';
            icon = '🌱';
            message = "No worries — everyone starts somewhere! We'll take things step by step.";
        }

        // Save to profile
        this.profileData.assessmentResults = {
            score: correct,
            total,
            level,
            topic: this.getAssessmentTopic()
        };
        this.profileData.knowledgeLevel = level;
        this.saveProgress();

        // Replace content with result card
        const content = document.getElementById('assessment-content');
        content.innerHTML = `
            <div class="assessment-result-card" style="text-align:center;padding:2rem 1rem;">
                <div style="font-size:3rem;margin-bottom:0.75rem;">${icon}</div>
                <h2 style="margin-bottom:0.5rem;">Your Level: <span class="level-badge level-${level}">${label}</span></h2>
                <p style="color:var(--text-muted,#6b7280);margin-bottom:1.5rem;max-width:360px;margin-left:auto;margin-right:auto;">${message}</p>
                <p style="font-size:0.875rem;color:var(--text-muted,#6b7280);">Score: ${correct} / ${total} correct</p>
                <button class="btn btn-primary" style="margin-top:1.5rem;" onclick="wizard.nextScreen()">
                    Continue <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        `;

        // Add level badge styles inline if not already present
        if (!document.getElementById('level-badge-styles')) {
            const style = document.createElement('style');
            style.id = 'level-badge-styles';
            style.textContent = `
                .level-badge { display:inline-block; padding:0.2em 0.7em; border-radius:4px; font-weight:700; border:2px solid currentColor; }
                .level-beginner  { color:#16a34a; background:#dcfce7; }
                .level-intermediate { color:#d97706; background:#fef3c7; }
                .level-advanced  { color:#7c3aed; background:#ede9fe; }
            `;
            document.head.appendChild(style);
        }

        if (typeof gsap !== 'undefined') gsap.from(content.firstElementChild, { opacity: 0, y: 20, duration: 0.4 });
    }

    getAssessmentTopic() {
        const shsProgram = this.profileData.shsProgram;
        const shsElectives = this.profileData.shsElectives || [];
        const customSubjects = this.profileData.customSubjects || [];
        const subjects = this.profileData.subjects || [];
        if (shsProgram === 'business' || shsElectives.includes('financial-accounting')) return 'Business & Accounting';
        if (shsProgram) return shsProgram.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        if (customSubjects.length > 0) return customSubjects[0];
        if (subjects.length > 0) return subjects[0].replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        return 'General Aptitude';
    }

    skipAssessment() {
        console.log('⏭️ Assessment skipped');
        this.profileData.assessmentResults = { skipped: true };
        this.profileData.knowledgeLevel = null;
        this.saveProgress();
        this.nextScreen();
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

    finishLessonAndNext() {
        console.log('✅ First lesson completed');
        this.profileData.firstLessonCompleted = true;
        this.saveProgress();
        this.nextScreen();
    }

    /* ==================== SCREEN 9: SUMMARY ==================== */
    initSummary() {
        const container = document.getElementById('summary-content');
        const p = this.profileData;
        const levelLabels = { beginner: '🌱 Beginner', intermediate: '📈 Intermediate', advanced: '🏆 Advanced' };
        const levelLine = p.knowledgeLevel
            ? `<p><strong>Knowledge Level:</strong> ${levelLabels[p.knowledgeLevel] || p.knowledgeLevel}</p>`
            : '';

        container.innerHTML = `
            <div class="summary-card">
                <h3>Ready to Learn! 🚀</h3>
                <p><strong>Focus:</strong> ${p.educationLevel}</p>
                <p><strong>Goal:</strong> ${p.learningGoal || 'General Learning'}</p>
                ${levelLine}
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
