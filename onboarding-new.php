<?php
// Force HTTPS redirect (skip on localhost for development)
$isLocalhost = in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1', '::1']);
if (!$isLocalhost && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')) {
    if (!headers_sent()) {
//         header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
        exit();
    }
}

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_once 'check_auth.php'; // Secure this page
// Temporarily skip DB check for faster load during development
// require_once 'db_mysql.php';

$displayName = isset($_SESSION['first_name']) && !empty($_SESSION['first_name']) ? $_SESSION['first_name'] : (isset($_SESSION['username']) ? $_SESSION['username'] : 'there');
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// TEMPORARILY DISABLED for development - re-enable for production
/*
// Check if onboarding is already completed
if ($user_id) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT onboarding_completed FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If onboarding is already completed, redirect to main app
        if ($user && $user['onboarding_completed']) {
            header('Location: chat');
            exit;
        }
    } catch (Exception $e) {
        error_log("Onboarding check error: " . $e->getMessage());
    }
}
*/
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to TutorMind - Interactive Setup</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Logo Styles -->
    <link rel="stylesheet" href="logo.css?v=<?= time() ?>">
    
    <!-- Wizard Styles -->
    <link rel="stylesheet" href="onboarding-wizard.css?v=<?= time() ?>">

    <!-- Dark Mode Init (must run before body renders to prevent flash) -->
    <script>
    (function() {
        var theme = localStorage.getItem('tutormind-theme');
        var darkMode = localStorage.getItem('darkMode');
        var isDark = (theme === 'dark') || (darkMode === 'enabled');
        if (isDark) {
            document.documentElement.classList.add('dark-mode');
            // Sync both keys so they stay consistent across pages
            if (theme !== 'dark') localStorage.setItem('tutormind-theme', 'dark');
            if (darkMode !== 'enabled') localStorage.setItem('darkMode', 'enabled');
        }
    })();
    </script>
</head>
<body>
    <script>
    // Apply dark-mode class to body as well (CSS targets body.dark-mode)
    if (document.documentElement.classList.contains('dark-mode')) {
        document.body.classList.add('dark-mode');
    }
    </script>
    <div id="onboarding-container">
        <!-- Progress Bar -->
        <div class="wizard-progress">
            <div class="wizard-progress-text">
                <h2>Let's Get Started, <?= htmlspecialchars($displayName) ?>! 👋</h2>
                <span id="wizard-progress-text">1 / 9</span>
            </div>
            <div class="wizard-progress-bar-container">
                <div id="wizard-progress-bar" style="width: 11%;"></div>
            </div>
        </div>
        
        <!-- Screens Container -->
        <div class="screens-wrapper">
            <!-- ==================== SCREEN 1: WELCOME ==================== -->
            <div class="screen active" id="screen1">
                <div class="gradient-bg"></div>
                
                <h1>Your Personal AI Tutor, Ready 24/7</h1>
                <p class="subtitle">Get instant help, step-by-step explanations, and practice tailored to your needs</p>
                
                <div class="hero-icons">
                    <div class="hero-icon">📚</div>
                    <div class="hero-icon">🧠</div>
                    <div class="hero-icon">🎯</div>
                    <div class="hero-icon">🚀</div>
                </div>
                
                <button id="get-started-btn" class="btn btn-primary btn-large">
                    Get Started <i class="fas fa-arrow-right"></i>
                </button>
            </div>
            
            <!-- ==================== SCREEN 2: EDUCATION ==================== -->
            <div class="screen" id="screen2">
                <h2>What's your current education level?</h2>
                <p class="subtitle">This helps us align with your curriculum and difficulty level.</p>
                
                <!-- Education Level Cards -->
                <div class="education-grid">
                    <!-- High School -->
                    <div class="education-card" data-level="high">
                        <div class="education-icon">🎓</div>
                        <h3>High School</h3>
                        <p>Grades 9-12</p>
                    </div>
                    
                    <!-- College -->
                    <div class="education-card" data-level="college">
                        <div class="education-icon">🏛️</div>
                        <h3>College/University</h3>
                        <p>Undergraduate & Graduate</p>
                    </div>
                    
                    <!-- Adult Learner -->
                    <div class="education-card" data-level="adult">
                        <div class="education-icon">👔</div>
                        <h3>Adult Learner</h3>
                        <p>Professional Development</p>
                    </div>
                    
                    <!-- Other -->
                    <div class="education-card" data-level="other">
                        <div class="education-icon">🌍</div>
                        <h3>Other</h3>
                        <p>Custom / Homeschool</p>
                    </div>
                </div>

                <!-- School/University Input -->
                <div id="school-input-container" class="school-input-wrapper hidden" style="margin-top: 2rem;">
                    <label id="school-input-label" for="school-name-input" style="display: block; margin-bottom: 0.5rem; color: var(--text-primary); font-weight: 500;">School or University Name</label>
                    <div class="input-with-icon" style="position: relative;">
                        <input type="text" id="school-name-input" placeholder="Start typing..." style="width: 100%; padding: 1rem; border-radius: 12px; border: 2px solid var(--border-color); font-size: 1rem;">
                    </div>
                    <datalist id="university-list">
                        <!-- Populated via JS -->
                    </datalist>
                </div>
                
                <!-- Error Message -->
                <p class="error-message" id="screen2-error">Please select your education level to continue.</p>
                
                <!-- Navigation -->
                <div class="screen-navigation">
                    <button class="btn btn-secondary" onclick="wizard.previousScreen()">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <button class="btn btn-primary" id="screen2-next-btn" onclick="wizard.saveEducationAndNext()">
                        Continue <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
            
            <!-- ==================== SCREEN 3: SUBJECTS ==================== -->
            <div class="screen" id="screen3">
                <h2>Which subjects do you want help with?</h2>
                <p class="subtitle">Don't worry, you can add or change these anytime.</p>
                
                <!-- Search Bar -->
                <div class="subject-search-container">
                    <i class="fas fa-search"></i>
                    <input type="search" id="subject-search" placeholder="Search subjects..." class="subject-search-input">
                </div>
                
                <!-- Subject Grid -->
                <div class="subject-grid" id="subject-grid">
                    <!-- Mathematics -->
                    <div class="subject-card" data-subject="mathematics">
                        <div class="subject-card-header">
                            <div class="subject-icon">🧮</div>
                            <h3>Mathematics</h3>
                            <div class="checkmark hidden"><i class="fas fa-check-circle"></i></div>
                        </div>
                        <div class="subcategories hidden">
                            <label><input type="checkbox" value="arithmetic" data-parent="mathematics"> Arithmetic</label>
                            <label><input type="checkbox" value="algebra" data-parent="mathematics"> Algebra</label>
                            <label><input type="checkbox" value="geometry" data-parent="mathematics"> Geometry</label>
                            <label><input type="checkbox" value="calculus" data-parent="mathematics"> Calculus</label>
                            <label><input type="checkbox" value="statistics" data-parent="mathematics"> Statistics</label>
                            <label><input type="checkbox" value="trigonometry" data-parent="mathematics"> Trigonometry</label>
                        </div>
                    </div>
                    
                    <!-- Science -->
                    <div class="subject-card" data-subject="science">
                        <div class="subject-card-header">
                            <div class="subject-icon">🔬</div>
                            <h3>Science</h3>
                            <div class="checkmark hidden"><i class="fas fa-check-circle"></i></div>
                        </div>
                        <div class="subcategories hidden">
                            <label><input type="checkbox" value="physics" data-parent="science"> Physics</label>
                            <label><input type="checkbox" value="chemistry" data-parent="science"> Chemistry</label>
                            <label><input type="checkbox" value="biology" data-parent="science"> Biology</label>
                            <label><input type="checkbox" value="earth-science" data-parent="science"> Earth Science</label>
                            <label><input type="checkbox" value="environmental" data-parent="science"> Environmental Science</label>
                        </div>
                    </div>
                    
                    <!-- Languages -->
                    <div class="subject-card" data-subject="languages">
                        <div class="subject-card-header">
                            <div class="subject-icon">📖</div>
                            <h3>Languages</h3>
                            <div class="checkmark hidden"><i class="fas fa-check-circle"></i></div>
                        </div>
                        <div class="subcategories hidden">
                            <label><input type="checkbox" value="english" data-parent="languages"> English</label>
                            <label><input type="checkbox" value="spanish" data-parent="languages"> Spanish</label>
                            <label><input type="checkbox" value="french" data-parent="languages"> French</label>
                            <label><input type="checkbox" value="grammar" data-parent="languages"> Grammar</label>
                            <label><input type="checkbox" value="writing" data-parent="languages"> Essay Writing</label>
                            <label><input type="checkbox" value="literature" data-parent="languages"> Literature</label>
                        </div>
                    </div>
                    
                    <!-- Computer Science -->
                    <div class="subject-card" data-subject="computer-science">
                        <div class="subject-card-header">
                            <div class="subject-icon">💻</div>
                            <h3>Computer Science</h3>
                            <div class="checkmark hidden"><i class="fas fa-check-circle"></i></div>
                        </div>
                        <div class="subcategories hidden">
                            <label><input type="checkbox" value="programming" data-parent="computer-science"> Programming</label>
                            <label><input type="checkbox" value="data-structures" data-parent="computer-science"> Data Structures</label>
                            <label><input type="checkbox" value="web-dev" data-parent="computer-science"> Web Development</label>
                            <label><input type="checkbox" value="algorithms" data-parent="computer-science"> Algorithms</label>
                            <label><input type="checkbox" value="databases" data-parent="computer-science"> Databases</label>
                        </div>
                    </div>
                    
                    <!-- Social Studies -->
                    <div class="subject-card" data-subject="social-studies">
                        <div class="subject-card-header">
                            <div class="subject-icon">📊</div>
                            <h3>Social Studies</h3>
                            <div class="checkmark hidden"><i class="fas fa-check-circle"></i></div>
                        </div>
                        <div class="subcategories hidden">
                            <label><input type="checkbox" value="history" data-parent="social-studies"> History</label>
                            <label><input type="checkbox" value="geography" data-parent="social-studies"> Geography</label>
                            <label><input type="checkbox" value="economics" data-parent="social-studies"> Economics</label>
                            <label><input type="checkbox" value="civics" data-parent="social-studies"> Civics</label>
                            <label><input type="checkbox" value="psychology" data-parent="social-studies"> Psychology</label>
                        </div>
                    </div>
                    
                    <!--Other -->
                    <div class="subject-card" data-subject="other">
                        <div class="subject-card-header">
                            <div class="subject-icon">🎨</div>
                            <h3>Other</h3>
                            <div class="checkmark hidden"><i class="fas fa-check-circle"></i></div>
                        </div>
                        <div class="subcategories hidden">
                            <label><input type="checkbox" value="arts" data-parent="other"> Arts</label>
                            <label><input type="checkbox" value="music" data-parent="other"> Music Theory</label>
                            <label><input type="checkbox" value="sat-act" data-parent="other"> SAT/ACT Prep</label>
                            <label><input type="checkbox" value="test-prep" data-parent="other"> Test Preparation</label>
                            <label><input type="checkbox" value="study-skills" data-parent="other"> Study Skills</label>
                        </div>
                    </div>
                </div>
                
                <!-- ==================== GHANA SHS PROGRAM SELECTOR (High School) ==================== -->
                <div id="shs-program-selector" class="shs-program-selector hidden">
                    <!-- Info Banner -->
                    <div class="info-banner">
                        <i class="fas fa-info-circle"></i>
                        <p><strong>You're not limited to your program!</strong> While we'll suggest topics from your subjects, you can ask questions about anything - even outside your program.</p>
                    </div>

                    <!-- Program Selection Grid -->
                    <div id="shs-program-grid" class="shs-grid">
                        <!-- Programs populated via JS -->
                    </div>

                    <!-- Selected Program Detail View (Initially Hidden) -->
                    <div id="shs-program-detail" class="shs-program-detail hidden">
                        <div class="shs-detail-header">
                            <button class="shs-change-btn" id="shs-change-program-btn">
                                <i class="fas fa-arrow-left"></i> Change Program
                            </button>
                            <div class="shs-program-title">
                                <div id="shs-selected-icon" class="shs-icon-wrapper"></div>
                                <div>
                                    <h2 id="shs-selected-name">Program Name</h2>
                                    <p id="shs-selected-desc">Description</p>
                                </div>
                            </div>
                        </div>

                        <!-- Core Subjects -->
                        <div class="shs-core-subjects">
                            <h4 class="shs-section-title">Core Subjects <span class="shs-section-subtitle">(All students take these)</span></h4>
                            <div class="core-grid">
                                <div class="core-item"><div class="check-circle"><i class="fas fa-check"></i></div> English Language</div>
                                <div class="core-item"><div class="check-circle"><i class="fas fa-check"></i></div> Mathematics (Core)</div>
                                <div class="core-item"><div class="check-circle"><i class="fas fa-check"></i></div> Integrated Science</div>
                                <div class="core-item"><div class="check-circle"><i class="fas fa-check"></i></div> Social Studies</div>
                            </div>
                        </div>

                        <!-- Electives -->
                        <div class="shs-electives">
                            <h4 class="shs-section-title">Which elective subjects are you taking?</h4>
                            <div id="shs-electives-grid" class="electives-grid">
                                <!-- Populated via JS -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================== UNIVERSITY CUSTOM FORM ==================== -->
                <div id="university-custom-form" class="university-custom-form hidden">
                    <h2>What are you studying at university?</h2>
                    
                    <label>School/University Name:</label>
                    <input type="text" id="uni-school-name" readonly style="background: var(--bg-secondary); cursor: not-allowed;">
                    
                    <label>Your Program/Course:</label>
                    <input type="text" id="uni-program-input" placeholder="e.g., BSc Computer Science, BA Economics">
                    
                    <label>Which subjects do you need help with?</label>
                    <div class="uni-subject-input-group">
                        <input type="text" id="uni-subject-entry" placeholder="e.g., Calculus II">
                        <button type="button" id="add-uni-subject-btn" class="btn-icon-only"><i class="fas fa-plus"></i></button>
                    </div>
                    
                    <div id="uni-subjects-list" class="uni-tags-container">
                        <!-- Tags populated via JS -->
                    </div>
                    
                    <p class="helper-text">Don't worry, you can add more subjects later!</p>
                </div>
                
                <!-- Primary Subject Selection (appears when multiple subjects selected) -->
                <div class="primary-subject-selector hidden" id="primary-subject-selector">
                    <p class="primary-subject-question">Which subject would you like to start with?</p>
                    <div class="primary-subject-buttons" id="primary-subject-buttons"></div>
                </div>
                
                <!-- No Selection Message -->
                <p class="error-message" id="screen3-error">Please select at least one subject to continue.</p>
                
                <!-- Navigation -->
                <div class="screen-navigation">
                    <button class="btn btn-secondary" onclick="wizard.previousScreen()">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <button class="btn btn-primary" id="subjects-continue-btn" onclick="wizard.saveSubjectsAndNext()">
                        Continue <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
            
            <!-- ==================== SCREEN 4: GOALS ==================== -->
            <div class="screen" id="screen4">
                <h2>What brings you here today?</h2>
                <p class="subtitle">Choose your main learning goal so we can personalize your experience.</p>
                
                <!-- Goal Cards Grid -->
                <div class="goal-grid">
                    <!-- Homework Help -->
                    <div class="goal-card" data-goal="homework_help">
                        <div class="goal-icon">📚</div>
                        <h3>Homework Help</h3>
                        <p>I need help with specific assignments</p>
                    </div>
                    
                    <!-- Exam Preparation -->
                    <div class="goal-card" data-goal="exam_prep">
                        <div class="goal-icon">🎯</div>
                        <h3>Exam Preparation</h3>
                        <p>I'm studying for a test or exam</p>
                    </div>
                    
                    <!-- Concept Mastery -->
                    <div class="goal-card" data-goal="concept_mastery">
                        <div class="goal-icon">💡</div>
                        <h3>Concept Mastery</h3>
                        <p>I want to deeply understand topics</p>
                    </div>
                    
                    <!-- Get Ahead -->
                    <div class="goal-card" data-goal="get_ahead">
                        <div class="goal-icon">🚀</div>
                        <h3>Get Ahead</h3>
                        <p>I want to learn beyond my current grade</p>
                    </div>
                    
                    <!-- Catch Up -->
                    <div class="goal-card" data-goal="catch_up">
                        <div class="goal-icon">🔄</div>
                        <h3>Catch Up</h3>
                        <p>I'm struggling with topics I should already know</p>
                    </div>
                    
                    <!-- General Learning -->
                    <div class="goal-card" data-goal="general_learning">
                        <div class="goal-icon">🌟</div>
                        <h3>General Learning</h3>
                        <p>I'm curious and want to explore</p>
                    </div>
                </div>
                
                <!-- Error Message -->
                <p class="error-message" id="screen4-error">Please select a goal to continue.</p>
                
                <!-- Navigation -->
                <div class="screen-navigation">
                    <button class="btn btn-secondary" onclick="wizard.previousScreen()">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <button class="btn btn-primary" id="goals-continue-btn" onclick="wizard.saveGoalAndNext()">
                        Continue <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
            
            <!-- ==================== SCREEN 5: ASSESSMENT ==================== -->
            <div class="screen" id="screen5">
                <!-- Loading State (Initial) -->
                <div id="assessment-loading" class="text-center">
                    <div class="ai-spinner"></div>
                    <h3 class="mt-4">Analyzing your profile...</h3>
                    <p class="text-muted">AI is generating a personalized skills check based on your goals.</p>
                </div>

                <!-- Assessment Content (Hidden Initially) -->
                <div id="assessment-content" class="hidden">
                    <div class="assessment-header">
                        <span class="badge-pill">Skills Check</span>
                        <span class="question-tracker">Question <span id="current-q-num">1</span> of <span id="total-q-num">5</span></span>
                    </div>

                    <div class="question-card">
                        <h3 id="question-text">Question text goes here?</h3>
                        
                        <div class="options-grid" id="options-container">
                            <!-- Options injected by JS -->
                        </div>
                    </div>

                    <!-- Navigation -->
                    <div class="screen-navigation">
                        <button class="btn btn-secondary" onclick="wizard.previousScreen()">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <button class="btn btn-primary disabled" id="assessment-continue-btn" onclick="wizard.nextQuestionOnly()">
                            Next Question <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                    
                    <button class="btn-text" onclick="wizard.skipAssessment()" style="margin-top: 1rem; color: var(--text-muted); font-size: 0.9rem;">
                        Skip assessment for now
                    </button>
                </div>
            </div>
            
            <!-- ==================== SCREEN 6: PREFERENCES ==================== -->
            <div class="screen" id="screen6">
                <h2>How do you learn best?</h2>
                <p class="subtitle">Customize your learning environment to fit your style.</p>
                
                <div class="preferences-container">
                    <!-- Section A: Schedule -->
                    <div class="preference-section">
                        <h4>📅 When do you usually study?</h4>
                        <div class="options-row">
                            <button class="preference-btn schedule-option" data-value="weekdays">Weekdays</button>
                            <button class="preference-btn schedule-option" data-value="weekends">Weekends</button>
                            <button class="preference-btn schedule-option" data-value="daily">Daily</button>
                            <button class="preference-btn schedule-option" data-value="flexible">Flexible / Whenever</button>
                        </div>
                    </div>

                    <!-- Section B: Duration -->
                    <div class="preference-section">
                        <h4>⏱️ Preferred Session Length</h4>
                        <div class="options-row">
                            <button class="preference-btn duration-option" data-value="15m">15 mins</button>
                            <button class="preference-btn duration-option" data-value="30m">30 mins</button>
                            <button class="preference-btn duration-option" data-value="45m">45 mins</button>
                            <button class="preference-btn duration-option" data-value="60m+">60+ mins</button>
                        </div>
                    </div>

                    <!-- Section C: Style -->
                    <div class="preference-section">
                        <h4>🤔 Preferred Explanation Style</h4>
                        <div class="style-grid">
                            <div class="style-card" data-value="simple">
                                <div class="style-icon">💡</div>
                                <h3>Simple & Direct</h3>
                                <p>Get straight to the point.</p>
                            </div>
                            <div class="style-card" data-value="detailed">
                                <div class="style-icon">📖</div>
                                <h3>Detailed & In-depth</h3>
                                <p>Explain the 'why' and 'how'.</p>
                            </div>
                            <div class="style-card" data-value="socratic">
                                <div class="style-icon">❓</div>
                                <h3>Socratic</h3>
                                <p>Guide me with questions.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Error Message -->
                <p class="error-message" id="preferences-error">Please select an option for each category.</p>

                <!-- Navigation -->
                <div class="screen-navigation">
                    <button class="btn btn-secondary" onclick="wizard.previousScreen()">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <button class="btn btn-primary disabled" id="preferences-continue-btn" onclick="wizard.savePreferencesAndNext()">
                        Continue <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
            
            <!-- ==================== SCREEN 7: NOTIFICATIONS ==================== -->
            <div class="screen" id="screen7">
                <h2>Stay on track with reminders</h2>
                <p class="subtitle">Building a habit is easier with a nudge. We won't spam you.</p>
                
                <div class="notifications-wrapper">
                    <!-- Master Toggle -->
                    <button id="notification-toggle" class="notification-toggle-btn active">
                        <i class="fas fa-bell"></i>
                        <span>Reminders Enabled</span>
                        <div class="toggle-switch"></div>
                    </button>
                    
                    <!-- Settings Container -->
                    <div id="notification-settings-container" class="mt-4">
                        <div class="notification-card">
                            <div class="setting-row">
                                <label>Frequency</label>
                                <div class="frequency-selector">
                                    <button class="frequency-option selected" data-value="daily">Daily</button>
                                    <button class="frequency-option" data-value="weekdays">Weekdays</button>
                                    <button class="frequency-option" data-value="weekends">Weekends</button>
                                </div>
                            </div>
                            
                            <div class="setting-row">
                                <label>Time</label>
                                <div class="time-picker-wrapper">
                                    <input type="time" id="notification-time" value="17:00">
                                </div>
                            </div>
                        </div>
                        
                        <div class="notification-preview">
                            <div class="mini-notification">
                                <div class="notif-icon">🤖</div>
                                <div class="notif-content">
                                    <div class="notif-title">TutorMind</div>
                                    <div class="notif-body">Time for your daily session! Ready to solve some problems? 🚀</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Navigation -->
                <div class="screen-navigation">
                    <button class="btn btn-secondary" onclick="wizard.previousScreen()">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <button class="btn btn-primary" id="notifications-continue-btn">
                        Continue <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
                
                <button id="notifications-skip-btn" class="btn-text mt-2" style="width: 100%; color: var(--text-muted);">
                    Maybe later
                </button>
            </div>
            
            <!-- ==================== SCREEN 8: FIRST LESSON ==================== -->
            <div class="screen" id="screen8">
                <h2>Let's solve your first problem together!</h2>
                <p class="subtitle">Experience how TutorMind works in real-time.</p>
                
                <div class="lesson-interface-container">
                    <div class="lesson-window">
                        <!-- Chat Area -->
                        <div id="lesson-chat-container" class="lesson-chat-area">
                            <!-- Messages injected by JS -->
                        </div>
                        
                        <!-- Interaction Area -->
                        <div class="lesson-input-area">
                            <div id="lesson-options" class="lesson-options-grid hidden">
                                <!-- Options injected by JS -->
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Navigation (Initially Hidden) -->
                <div class="screen-navigation centered-nav">
                   <button class="btn btn-primary hidden" id="lesson-continue-btn" onclick="wizard.finishLessonAndNext()">
                        Continue to Summary <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
            
            <!-- ==================== SCREEN 9: SUMMARY ==================== -->
            <div class="screen" id="screen9">
                <h2>You're all set! 🎉</h2>
                <p class="subtitle">We've personalized TutorMind just for you.</p>
                
                <div id="summary-content" class="summary-wrapper">
                    <!-- Injected by JS -->
                </div>
                
                <div class="completion-actions">
                    <p class="completion-note">Ready to start learning?</p>
                    <button class="btn btn-primary btn-large pop-in-delay" onclick="wizard.completeOnboarding()">
                        Go to Dashboard <i class="fas fa-rocket"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- GSAP Animation Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.4/gsap.min.js"></script>
    
    <!-- Core Wizard Logic -->
    <script src="onboarding-wizard.js?v=<?= time() ?>"></script>
    
    <!-- Welcome Screen Animations -->
    <script src="onboarding-animations.js?v=<?= time() ?>"></script>
    
    <!-- University Data -->
    <script src="onboarding-universities.js?v=<?= time() ?>"></script>
    
    <!-- Screen 2: Education Selection Logic -->
    <script src="onboarding-screen2.js?v=<?= time() ?>"></script>
    
    <!-- Screen 3: Subject Selection Logic -->
    <script src="onboarding-screen3.js?v=<?= time() ?>"></script>
    
    <!-- Screen 4: Goal Selection Logic -->
    <script src="onboarding-screen4.js?v=<?= time() ?>"></script>
    
    <!-- Screen 5: AI Assessment Logic -->
    <script src="onboarding-screen5.js?v=<?= time() ?>"></script>
    
    <!-- Screen 6: Preferences Logic -->
    <script src="onboarding-screen6.js?v=<?= time() ?>"></script>
    
    <!-- Screen 7: Notifications Logic -->
    <script src="onboarding-screen7.js?v=<?= time() ?>"></script>
    
    <!-- Screen 8: First Lesson Logic -->
    <script src="onboarding-screen8.js?v=<?= time() ?>"></script>
    
    <!-- Screen 9: Summary Logic -->
    <script src="onboarding-screen9.js?v=<?= time() ?>"></script>
</body>
</html>
