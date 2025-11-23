<?php
// Prevent caching - especially important for mobile browsers
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_once 'check_auth.php'; // Secure this page
require_once 'db_mysql.php';

$displayName = isset($_SESSION['first_name']) && !empty($_SESSION['first_name']) ? $_SESSION['first_name'] : (isset($_SESSION['username']) ? $_SESSION['username'] : 'there');
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Check if onboarding is already completed
if ($user_id) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT onboarding_completed FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If onboarding is already completed, redirect to main app
        if ($user && $user['onboarding_completed']) {
            header('Location: tutor_mysql.php');
            exit;
        }
    } catch (Exception $e) {
        error_log("Onboarding check error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to TutorMind - Let's Personalize Your Experience</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="logo.css?v=<?= time() ?>">
    <style>
        :root {
            --primary: #7B3FF2;
            --primary-light: #9D6BF5;
            --primary-dark: #5E2EBF;
            --secondary: #FF6B35;
            --accent: #FFB347;
            --cyan: #4ECDC4;
            --bg-white: #FFFFFF;
            --bg-light: #F9FAFB;
            --text-primary: #1A1A1A;
            --text-secondary: #6B7280;
            --text-muted: #9CA3AF;
            --border: #E5E7EB;
            --success: #10B981;
            --error: #EF4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }

        .onboarding-container {
            background: var(--bg-white);
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
            position: relative;
        }

        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 3rem 2rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }
        
        .header .app-logo {
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }
        
        .header .app-logo .app-logo-text {
            color: white;
            background: none;
            -webkit-text-fill-color: white;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .header p {
            font-size: 1rem;
            opacity: 0.9;
        }

        .progress-bar-container {
            height: 4px;
            background: rgba(255, 255, 255, 0.3);
            margin-top: 1.5rem;
        }

        .progress-bar {
            height: 100%;
            background: white;
            width: 50%;
            transition: width 0.3s ease;
        }

        .form-content {
            padding: 2.5rem 2rem;
        }

        .step {
            display: none;
        }

        .step.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .step-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .step-subtitle {
            color: var(--text-secondary);
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }

        .input-group {
            margin-bottom: 1.5rem;
        }

        .input-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .required {
            color: var(--error);
        }

        .input-group select,
        .input-group input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.2s ease;
            font-family: inherit;
        }

        .input-group select:focus,
        .input-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(123, 63, 242, 0.1);
        }

        .input-group small {
            display: block;
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        .error-message {
            color: var(--error);
            font-size: 0.85rem;
            margin-top: 0.5rem;
            display: none;
        }

        .error-message.show {
            display: block;
        }

        .buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            flex: 1;
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: inherit;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(123, 63, 242, 0.3);
        }

        .btn-secondary {
            background: var(--bg-light);
            color: var(--text-secondary);
        }

        .btn-secondary:hover {
            background: var(--border);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        .skip-link {
            text-align: center;
            margin-top: 1rem;
        }

        .skip-link a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.2s ease;
        }

        .skip-link a:hover {
            color: var(--primary);
        }

        @media (max-width: 640px) {
            body {
                padding: 1rem;
            }

            .header {
                padding: 2rem 1.5rem 1.5rem;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .form-content {
                padding: 2rem 1.5rem;
            }

            .buttons {
                flex-direction: column-reverse;
            }
        }
    </style>
</head>
<body>
    <div class="onboarding-container">
        <div class="header">
            <a href="index.html" class="app-logo" style="display: inline-flex;">
                <span class="app-logo-text">🧠 TutorMind</span>
            </a>
            <h1>Welcome, <?= htmlspecialchars($displayName) ?>! 👋</h1>
            <p>Let's personalize your learning experience</p>
            <div class="progress-bar-container">
                <div class="progress-bar" id="progressBar"></div>
            </div>
        </div>

        <div class="form-content">
            <form id="onboardingForm">
                <!-- Step 1: Basic Information -->
                <div class="step active" id="step1">
                    <h2 class="step-title">Tell us about yourself</h2>
                    <p class="step-subtitle">This helps us provide examples and context that are relevant to you</p>

                    <div class="input-group">
                        <label for="country">Country <span class="required">*</span></label>
                        <select id="country" name="country" required>
                            <option value="">Select your country</option>
                            <option value="Ghana">Ghana</option>
                            <option value="Nigeria">Nigeria</option>
                            <option value="Kenya">Kenya</option>
                            <option value="South Africa">South Africa</option>
                            <option value="United States">United States</option>
                            <option value="United Kingdom">United Kingdom</option>
                            <option value="Canada">Canada</option>
                            <option value="Other">Other</option>
                        </select>
                        <small>We'll use this to provide culturally relevant examples</small>
                    </div>

                    <div class="input-group">
                        <label for="education_level">Current Education Level <span class="required">*</span></label>
                        <select id="education_level" name="education_level" required>
                            <option value="">Select your level</option>
                            <option value="Primary">Primary School</option>
                            <option value="Secondary">Secondary/High School</option>
                            <option value="University">University/College</option>
                            <option value="Graduate">Graduate Studies</option>
                            <option value="Professional">Professional/Working</option>
                            <option value="Other">Other</option>
                        </select>
                        <small>This helps us adjust the depth of explanations</small>
                    </div>

                    <div class="error-message" id="step1Error"></div>

                    <div class="buttons">
                        <button type="button" class="btn btn-primary" id="nextBtn">
                            Next <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Optional Details -->
                <div class="step" id="step2">
                    <h2 class="step-title">A few more details...</h2>
                    <p class="step-subtitle">These are optional but help us personalize even more</p>

                    <div class="input-group">
                        <label for="primary_language">Primary Language</label>
                        <input type="text" id="primary_language" name="primary_language" placeholder="e.g., English" value="English">
                        <small>The language you're most comfortable learning in</small>
                    </div>

                    <div class="input-group" id="fieldOfStudyGroup" style="display: none;">
                        <label for="field_of_study">Field of Study / Program</label>
                        <input type="text" id="field_of_study" name="field_of_study" placeholder="e.g., Computer Science, Medicine">
                        <small>What are you studying? (if applicable)</small>
                    </div>

                    <div class="input-group">
                        <label for="institution">Institution (Optional)</label>
                        <input type="text" id="institution" name="institution" placeholder="e.g., University of Cape Coast">
                        <small>Your school or university name</small>
                    </div>

                    <div class="error-message" id="step2Error"></div>

                    <div class="buttons">
                        <button type="button" class="btn btn-secondary" id="backBtn">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <button type="submit" class="btn btn-primary" id="finishBtn">
                            Finish & Start Learning <i class="fas fa-check"></i>
                        </button>
                    </div>

                    <div class="skip-link">
                        <a href="#" id="skipBtn">Skip for now</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="onboarding.js?v=<?= time() ?>"></script>
</body>
</html>
