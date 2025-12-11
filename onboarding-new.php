<?php
// Force HTTPS redirect (skip on localhost for development)
$isLocalhost = in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1', '::1']);
if (!$isLocalhost && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')) {
    if (!headers_sent()) {
        header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
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
</head>
<body>
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
            
            <!-- ==================== SCREEN 2: SUBJECTS ==================== -->
            <div class="screen" id="screen2">
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
                
                <!-- Primary Subject Selection (appears when multiple subjects selected) -->
                <div class="primary-subject-selector hidden" id="primary-subject-selector">
                    <p class="primary-subject-question">Which subject would you like to start with?</p>
                    <div class="primary-subject-buttons" id="primary-subject-buttons"></div>
                </div>
                
                <!-- No Selection Message -->
                <p class="error-message" id="subjects-error">Please select at least one subject to continue.</p>
                
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
            
            <!-- ==================== SCREEN 3: GOALS ==================== -->
            <div class="screen" id="screen3">
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
                <p class="error-message" id="goals-error">Please select a goal to continue.</p>
                
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
            
            <!-- ==================== SCREEN 4: EDUCATION ==================== -->
            <div class="screen" id="screen4">
                <h2>What's your current education level?</h2>
                <p class="subtitle">This helps us align with your curriculum and difficulty level.</p>
                
                <!-- Education Level Cards -->
                <div class="education-grid">
                    <!-- Elementary -->
                    <div class="education-card" data-level="elementary">
                        <div class="education-icon">🎒</div>
                        <h3>Elementary School</h3>
                        <p>Grades 1-5</p>
                    </div>
                    
                    <!-- Middle School -->
                    <div class="education-card" data-level="middle">
                        <div class="education-icon">📚</div>
                        <h3>Middle School</h3>
                        <p>Grades 6-8</p>
                    </div>
                    
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
                
                <!-- Country Selector (appears after education level selected) -->
                <div class="country-selector hidden" id="country-selector">
                    <label for="country-select">Which country's education system are you following?</label>
                    <select id="country-select" class="styled-select">
                        <option value="">Select your country...</option>
                        <option value="US">🇺🇸 United States</option>
                        <option value="GB">🇬🇧 United Kingdom</option>
                        <option value="CA">🇨🇦 Canada</option>
                        <option value="AU">🇦🇺 Australia</option>
                        <option value="IN">🇮🇳 India</option>
                        <option value="PH">🇵🇭 Philippines</option>
                        <option value="NG">🇳🇬 Nigeria</option>
                        <option value="ZA">🇿🇦 South Africa</option>
                        <option value="--" disabled>────────────</option>
                        <option value="AF">🇦🇫 Afghanistan</option>
                        <option value="AL">🇦🇱 Albania</option>
                        <option value="DZ">🇩🇿 Algeria</option>
                        <option value="AD">🇦🇩 Andorra</option>
                        <option value="AO">🇦🇴 Angola</option>
                        <option value="AG">🇦🇬 Antigua and Barbuda</option>
                        <option value="AR">🇦🇷 Argentina</option>
                        <option value="AM">🇦🇲 Armenia</option>
                        <option value="AT">🇦🇹 Austria</option>
                        <option value="AZ">🇦🇿 Azerbaijan</option>
                        <option value="BS">🇧🇸 Bahamas</option>
                        <option value="BH">🇧🇭 Bahrain</option>
                        <option value="BD">🇧🇩 Bangladesh</option>
                        <option value="BB">🇧🇧 Barbados</option>
                        <option value="BY">🇧🇾 Belarus</option>
                        <option value="BE">🇧🇪 Belgium</option>
                        <option value="BZ">🇧🇿 Belize</option>
                        <option value="BJ">🇧🇯 Benin</option>
                        <option value="BT">🇧🇹 Bhutan</option>
                        <option value="BO">🇧🇴 Bolivia</option>
                        <option value="BA">🇧🇦 Bosnia and Herzegovina</option>
                        <option value="BW">🇧🇼 Botswana</option>
                        <option value="BR">🇧🇷 Brazil</option>
                        <option value="BN">🇧🇳 Brunei</option>
                        <option value="BG">🇧🇬 Bulgaria</option>
                        <option value="BF">🇧🇫 Burkina Faso</option>
                        <option value="BI">🇧🇮 Burundi</option>
                        <option value="CV">🇨🇻 Cabo Verde</option>
                        <option value="KH">🇰🇭 Cambodia</option>
                        <option value="CM">🇨🇲 Cameroon</option>
                        <option value="CF">🇨🇫 Central African Republic</option>
                        <option value="TD">🇹🇩 Chad</option>
                        <option value="CL">🇨🇱 Chile</option>
                        <option value="CN">🇨🇳 China</option>
                        <option value="CO">🇨🇴 Colombia</option>
                        <option value="KM">🇰🇲 Comoros</option>
                        <option value="CG">🇨🇬 Congo</option>
                        <option value="CR">🇨🇷 Costa Rica</option>
                        <option value="HR">🇭🇷 Croatia</option>
                        <option value="CU">🇨🇺 Cuba</option>
                        <option value="CY">🇨🇾 Cyprus</option>
                        <option value="CZ">🇨🇿 Czech Republic</option>
                        <option value="DK">🇩🇰 Denmark</option>
                        <option value="DJ">🇩🇯 Djibouti</option>
                        <option value="DM">🇩🇲 Dominica</option>
                        <option value="DO">🇩🇴 Dominican Republic</option>
                        <option value="EC">🇪🇨 Ecuador</option>
                        <option value="EG">🇪🇬 Egypt</option>
                        <option value="SV">🇸🇻 El Salvador</option>
                        <option value="GQ">🇬🇶 Equatorial Guinea</option>
                        <option value="ER">🇪🇷 Eritrea</option>
                        <option value="EE">🇪🇪 Estonia</option>
                        <option value="SZ">🇸🇿 Eswatini (Swaziland)</option>
                        <option value="ET">🇪🇹 Ethiopia</option>
                        <option value="FJ">🇫🇯 Fiji</option>
                        <option value="FI">🇫🇮 Finland</option>
                        <option value="FR">🇫🇷 France</option>
                        <option value="GA">🇬🇦 Gabon</option>
                        <option value="GM">🇬🇲 Gambia</option>
                        <option value="GE">🇬🇪 Georgia</option>
                        <option value="DE">🇩🇪 Germany</option>
                        <option value="GH">🇬🇭 Ghana</option>
                        <option value="GR">🇬🇷 Greece</option>
                        <option value="GD">🇬🇩 Grenada</option>
                        <option value="GT">🇬🇹 Guatemala</option>
                        <option value="GN">🇬🇳 Guinea</option>
                        <option value="GW">🇬🇼 Guinea-Bissau</option>
                        <option value="GY">🇬🇾 Guyana</option>
                        <option value="HT">🇭🇹 Haiti</option>
                        <option value="HN">🇭🇳 Honduras</option>
                        <option value="HU">🇭🇺 Hungary</option>
                        <option value="IS">🇮🇸 Iceland</option>
                        <option value="ID">🇮🇩 Indonesia</option>
                        <option value="IR">🇮🇷 Iran</option>
                        <option value="IQ">🇮🇶 Iraq</option>
                        <option value="IE">🇮🇪 Ireland</option>
                        <option value="IL">🇮🇱 Israel</option>
                        <option value="IT">🇮🇹 Italy</option>
                        <option value="JM">🇯🇲 Jamaica</option>
                        <option value="JP">🇯🇵 Japan</option>
                        <option value="JO">🇯🇴 Jordan</option>
                        <option value="KZ">🇰🇿 Kazakhstan</option>
                        <option value="KE">🇰🇪 Kenya</option>
                        <option value="KI">🇰🇮 Kiribati</option>
                        <option value="KW">🇰🇼 Kuwait</option>
                        <option value="KG">🇰🇬 Kyrgyzstan</option>
                        <option value="LA">🇱🇦 Laos</option>
                        <option value="LV">🇱🇻 Latvia</option>
                        <option value="LB">🇱🇧 Lebanon</option>
                        <option value="LS">🇱🇸 Lesotho</option>
                        <option value="LR">🇱🇷 Liberia</option>
                        <option value="LY">🇱🇾 Libya</option>
                        <option value="LI">🇱🇮 Liechtenstein</option>
                        <option value="LT">🇱🇹 Lithuania</option>
                        <option value="LU">🇱🇺 Luxembourg</option>
                        <option value="MG">🇲🇬 Madagascar</option>
                        <option value="MW">🇲🇼 Malawi</option>
                        <option value="MY">🇲🇾 Malaysia</option>
                        <option value="MV">🇲🇻 Maldives</option>
                        <option value="ML">🇲🇱 Mali</option>
                        <option value="MT">🇲🇹 Malta</option>
                        <option value="MH">🇲🇭 Marshall Islands</option>
                        <option value="MR">🇲🇷 Mauritania</option>
                        <option value="MU">🇲🇺 Mauritius</option>
                        <option value="MX">🇲🇽 Mexico</option>
                        <option value="FM">🇫🇲 Micronesia</option>
                        <option value="MD">🇲🇩 Moldova</option>
                        <option value="MC">🇲🇨 Monaco</option>
                        <option value="MN">🇲🇳 Mongolia</option>
                        <option value="ME">🇲🇪 Montenegro</option>
                        <option value="MA">🇲🇦 Morocco</option>
                        <option value="MZ">🇲🇿 Mozambique</option>
                        <option value="MM">🇲🇲 Myanmar</option>
                        <option value="NA">🇳🇦 Namibia</option>
                        <option value="NR">🇳🇷 Nauru</option>
                        <option value="NP">🇳🇵 Nepal</option>
                        <option value="NL">🇳🇱 Netherlands</option>
                        <option value="NZ">🇳🇿 New Zealand</option>
                        <option value="NI">🇳🇮 Nicaragua</option>
                        <option value="NE">🇳🇪 Niger</option>
                        <option value="KP">🇰🇵 North Korea</option>
                        <option value="MK">🇲🇰 North Macedonia</option>
                        <option value="NO">🇳🇴 Norway</option>
                        <option value="OM">🇴🇲 Oman</option>
                        <option value="PK">🇵🇰 Pakistan</option>
                        <option value="PW">🇵🇼 Palau</option>
                        <option value="PA">🇵🇦 Panama</option>
                        <option value="PG">🇵🇬 Papua New Guinea</option>
                        <option value="PY">🇵🇾 Paraguay</option>
                        <option value="PE">🇵🇪 Peru</option>
                        <option value="PL">🇵🇱 Poland</option>
                        <option value="PT">🇵🇹 Portugal</option>
                        <option value="QA">🇶🇦 Qatar</option>
                        <option value="RO">🇷🇴 Romania</option>
                        <option value="RU">🇷🇺 Russia</option>
                        <option value="RW">🇷🇼 Rwanda</option>
                        <option value="KN">🇰🇳 Saint Kitts and Nevis</option>
                        <option value="LC">🇱🇨 Saint Lucia</option>
                        <option value="VC">🇻🇨 Saint Vincent and the Grenadines</option>
                        <option value="WS">🇼🇸 Samoa</option>
                        <option value="SM">🇸🇲 San Marino</option>
                        <option value="ST">🇸🇹 Sao Tome and Principe</option>
                        <option value="SA">🇸🇦 Saudi Arabia</option>
                        <option value="SN">🇸🇳 Senegal</option>
                        <option value="RS">🇷🇸 Serbia</option>
                        <option value="SC">🇸🇨 Seychelles</option>
                        <option value="SL">🇸🇱 Sierra Leone</option>
                        <option value="SG">🇸🇬 Singapore</option>
                        <option value="SK">🇸🇰 Slovakia</option>
                        <option value="SI">🇸🇮 Slovenia</option>
                        <option value="SB">🇸🇧 Solomon Islands</option>
                        <option value="SO">🇸🇴 Somalia</option>
                        <option value="KR">🇰🇷 South Korea</option>
                        <option value="SS">🇸🇸 South Sudan</option>
                        <option value="ES">🇪🇸 Spain</option>
                        <option value="LK">🇱🇰 Sri Lanka</option>
                        <option value="SD">🇸🇩 Sudan</option>
                        <option value="SR">🇸🇷 Suriname</option>
                        <option value="SE">🇸🇪 Sweden</option>
                        <option value="CH">🇨🇭 Switzerland</option>
                        <option value="SY">🇸🇾 Syria</option>
                        <option value="TW">🇹🇼 Taiwan</option>
                        <option value="TJ">🇹🇯 Tajikistan</option>
                        <option value="TZ">🇹🇿 Tanzania</option>
                        <option value="TH">🇹🇭 Thailand</option>
                        <option value="TL">🇹🇱 Timor-Leste</option>
                        <option value="TG">🇹🇬 Togo</option>
                        <option value="TO">🇹🇴 Tonga</option>
                        <option value="TT">🇹🇹 Trinidad and Tobago</option>
                        <option value="TN">🇹🇳 Tunisia</option>
                        <option value="TR">🇹🇷 Turkey</option>
                        <option value="TM">🇹🇲 Turkmenistan</option>
                        <option value="TV">🇹🇻 Tuvalu</option>
                        <option value="UG">🇺🇬 Uganda</option>
                        <option value="UA">🇺🇦 Ukraine</option>
                        <option value="AE">🇦🇪 United Arab Emirates</option>
                        <option value="UY">🇺🇾 Uruguay</option>
                        <option value="UZ">🇺🇿 Uzbekistan</option>
                        <option value="VU">🇻🇺 Vanuatu</option>
                        <option value="VE">🇻🇪 Venezuela</option>
                        <option value="VN">🇻🇳 Vietnam</option>
                        <option value="YE">🇾🇪 Yemen</option>
                        <option value="ZM">🇿🇲 Zambia</option>
                        <option value="ZW">🇿🇼 Zimbabwe</option>
                        <option value="other">🌍 Other Country</option>
                    </select>
                </div>
                
                <!-- Error Message -->
                <p class="error-message" id="education-error">Please select your education level to continue.</p>
                
                <!-- Navigation -->
                <div class="screen-navigation">
                    <button class="btn btn-secondary" onclick="wizard.previousScreen()">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <button class="btn btn-primary" id="education-continue-btn" onclick="wizard.saveEducationAndNext()">
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
            
            <!-- ==================== SCREEN 6: PREFERENCES (Coming next) ==================== -->
            <div class="screen" id="screen6">
                <h2>A few quick preferences...</h2>
                <p style="text-align: center; color: var(--text-muted); margin-top: 3rem;">
                    🚧 Screen under construction 🚧
                </p>
            </div>
            
            <!-- ==================== SCREEN 7: NOTIFICATIONS (Coming next) ==================== -->
            <div class="screen" id="screen7">
                <h2>Would you like study reminders?</h2>
                <p style="text-align: center; color: var(--text-muted); margin-top: 3rem;">
                    🚧 Screen under construction 🚧
                </p>
            </div>
            
            <!-- ==================== SCREEN 8: FIRST LESSON (Coming next) ==================== -->
            <div class="screen" id="screen8">
                <h2>Let's solve your first problem together!</h2>
                <p style="text-align: center; color: var(--text-muted); margin-top: 3rem;">
                    🚧 Screen under construction 🚧
                </p>
            </div>
            
            <!-- ==================== SCREEN 9: SUMMARY (Coming next) ==================== -->
            <div class="screen" id="screen9">
                <h2>Your Learning Profile</h2>
                <p style="text-align: center; color: var(--text-muted); margin-top: 3rem;">
                    🚧 Screen under construction 🚧
                </p>
            </div>
        </div>
    </div>
    
    <!-- GSAP Animation Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.4/gsap.min.js"></script>
    
    <!-- Core Wizard Logic -->
    <script src="onboarding-wizard.js?v=<?= time() ?>"></script>
    
    <!-- Welcome Screen Animations -->
    <script src="onboarding-animations.js?v=<?= time() ?>"></script>
    
    <!-- Screen 2: Subject Selection Logic -->
    <script src="onboarding-screen2.js?v=<?= time() ?>"></script>
    
    <!-- Screen 3: Goal Selection Logic -->
    <script src="onboarding-screen3.js?v=<?= time() ?>"></script>
    
    <!-- Screen 4: Education Level Logic -->
    <script src="onboarding-screen4.js?v=<?= time() ?>"></script>
    
    <!-- Screen 5: AI Assessment Logic -->
    <script src="onboarding-screen5.js?v=<?= time() ?>"></script>
</body>
</html>
