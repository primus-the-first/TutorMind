<?php
// Force HTTPS redirect
// if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
//     if (!headers_sent()) {
//         header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
//         exit();
//     }
// }

// Prevent caching - especially important for mobile browsers
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_once 'check_auth.php'; // Secure this page
require_once 'db_mysql.php';

$user_email = 'email@example.com';
$displayName = isset($_SESSION['first_name']) && !empty($_SESSION['first_name']) ? $_SESSION['first_name'] : (isset($_SESSION['username']) ? $_SESSION['username'] : 'User');
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$user_program = null;

// Fetch user data
if ($user_id) {
    try {
        $pdo = getDbConnection();
        
        // Fetch email first (guaranteed to exist)
        $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $user_email = $user['email'];
        }

        // Try to fetch program (might not exist yet if migration wasn't run)
        try {
            $stmt = $pdo->prepare("SELECT field_of_study FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $prog = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($prog) {
                $user_program = $prog['field_of_study'];
            }
        } catch (Exception $e) {
            // Program column might be missing, ignore this specific error
            error_log("Program fetch error (column might be missing): " . $e->getMessage());
        }
        
    } catch (Exception $e) {
        error_log("Tutor page user fetch error: " . $e->getMessage());
    }
}

// Default prompts (will be updated by AI via JS)
$selectedPrompts = [
    'explain' => "Explain a complex topic simply.",
    'write' => "Write a creative story or email.",
    'build' => "Help me build a project plan.",
    'research' => "Research a topic in depth."
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#7b3ff2">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>TutorMind Chat | TutorMind</title>
    <!-- Fonts and Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Source+Sans+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- MathJax Configuration -->
    <script>
        MathJax = {
            tex: {
                inlineMath: [['$', '$'], ['\\(', '\\)']],
                displayMath: [['$$', '$$'], ['\\[', '\\]']],
                processEscapes: true,
                processEnvironments: true
            },
            options: {
                ignoreHtmlClass: 'no-mathjax',
                processHtmlClass: 'tex2jax_process'
            }
        };
    </script>

    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    
    <!-- Highlight.js for Syntax Highlighting -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/vs2015.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>

    <!-- Custom Styles for the new UI Overhaul -->
    <link rel="stylesheet" href="ui-overhaul.css?v=<?= time() ?>">
    <link rel="stylesheet" href="settings.css?v=<?= time() ?>">
    <link rel="stylesheet" href="logo.css?v=<?= time() ?>">
</head>
<body class="flex h-screen chat-empty">

    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar">
        <div class="sidebar-top-row" style="display: flex; align-items: center; padding: 1rem 0.75rem; gap: 0.5rem;">
            <button id="menu-toggle" class="menu-toggle">
                <i class="fas fa-bars"></i>
            </button>
            <a href="index" class="app-logo" style="margin-bottom: 0; padding: 0;">
                <span class="app-logo-text">🧠 TutorMind</span>
            </a>
        </div>
        
        <div class="sidebar-header">
            <button id="newChatBtn" class="new-chat-btn">
                <i class="fas fa-pen"></i> <span>New chat</span>
            </button>
        </div>
        
        <nav id="chat-history-container" class="chat-history">
            <!-- Chat history will be dynamically populated here by tutor_mysql.js -->
        </nav>
        
        <!-- User Profile Section -->
        <div id="user-account-dropdown" class="user-profile">
            <button id="user-account-trigger" class="user-info-button">
                <div class="user-avatar">
                    <?= htmlspecialchars(strtoupper(substr($displayName, 0, 1)))
?>
                </div>
                <div class="user-details">
                    <h4><?= htmlspecialchars($displayName) ?></h4>
                    <p><?= htmlspecialchars($user_email) ?></p>
                </div>
                <i id="user-account-chevron" class="fas fa-chevron-up"></i>
            </button>
            
            <div id="user-account-menu" class="user-menu hidden">
                 <div class="user-menu-header">
                    <div class="user-avatar">
                        <?= htmlspecialchars(strtoupper(substr($displayName, 0, 1)))
?>
                    </div>
                    <div class="user-details">
                        <h4><?= htmlspecialchars($displayName) ?></h4>
                        <p><?= htmlspecialchars($user_email) ?></p>
                    </div>
                </div>
                <nav class="user-menu-nav">
                    <a href="#"><i class="fas fa-star"></i> Upgrade plan</a>
                    <a href="#"><i class="fas fa-user-edit"></i> Personalization</a>
                    <a href="#" id="open-settings-btn"><i class="fas fa-cog"></i> Settings</a>
                    <a href="#"><i class="fas fa-question-circle"></i> Help</a>
                    <a href="/TutorMind/auth_mysql?action=logout" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Log out</a>
                    <div class="dark-mode-toggle">
                        <span><i class="fas fa-moon"></i> Dark Mode</span>
                        <label for="darkModeToggle" class="toggle-switch">
                            <input type="checkbox" id="darkModeToggle" class="sr-only">
                            <div class="slider"></div>
                        </label>
                    </div>
                </nav>
            </div>
        </div>
    </aside>

    <!-- Mobile Overlay -->
    <div id="sidebar-overlay" class="sidebar-overlay hidden"></div>

    <!-- Main Chat Area -->
    <div class="main-chat-wrapper">
        <header class="main-chat-header">
            <div class="header-left">
                <button id="mobile-menu-toggle" class="menu-toggle mobile-only">
                    <i class="fas fa-bars"></i>
                </button>
                <a href="index" class="mobile-logo-link mobile-only" style="text-decoration: none; color: inherit; margin-left: 10px;">
                    <span class="app-logo-text" style="font-size: 1.2rem;">TutorMind</span>
                </a>
            </div>
            
            <a href="index" style="text-decoration: none; color: inherit;" class="desktop-only">
                <h2 id="conversation-title" class="conversation-title">TutorMind</h2>
            </a>

            <!-- Mobile User Avatar removed as requested -->
        </header>

        <main id="chat-container" class="chat-content">
            <!-- Welcome Screen -->
            <div id="welcome-screen" class="welcome-section">
                <div class="gradient-orb"></div>
                <h1 id="welcome-greeting" data-username="<?= htmlspecialchars($displayName) ?>"></h1>
                <p>What would you like to know?</p>
                

            </div>
            <!-- Chat messages will be appended here -->
        </main>

        <footer class="input-bar-area">
            <div id="attachment-preview-area"></div>
            <form id="tutorForm" class="unified-input-container">
                <input type="hidden" id="conversation_id" name="conversation_id" value="<?= isset($_GET['conversation_id']) ? htmlspecialchars($_GET['conversation_id']) : '' ?>">
                <input type="file" id="file-attachment" name="attachment[]" class="hidden-input" multiple>
                
                <!-- Combined Input Bar -->
                <div class="combined-input-bar">
                    <!-- Left side: Add button -->
                    <label for="file-attachment" class="add-btn" title="Add attachments">
                        <i class="fas fa-plus"></i>
                    </label>
                    
                    <!-- Center: Text input -->
                    <input type="text" id="question" name="question" class="main-text-input" placeholder="Ask TutorMind" required>
                    
                    <!-- Right side: Controls group -->
                    <div class="input-controls-group">
                        <!-- Tools button (placeholder for now) -->
                        <button type="button" class="control-btn tools-btn" title="Tools">
                            <i class="fas fa-sliders-h"></i> Tools
                        </button>
                        
                        <!-- Learning Level dropdown -->
                        <select id="learningLevel" name="learningLevel" class="control-dropdown" title="Reasoning level">
                            <option value="Remember">Remember</option>
                            <option value="Understand" selected>Understand</option>
                            <option value="Apply">Apply</option>
                            <option value="Analyze">Analyze</option>
                            <option value="Evaluate">Evaluate</option>
                            <option value="Create">Create</option>
                        </select>
                        
                        <!-- Voice input button -->
                        <button type="button" class="control-btn voice-btn" title="Voice input">
                            <i class="fas fa-microphone"></i>
                        </button>
                        
                        <!-- Submit button -->
                        <button type="submit" id="ai-submit-btn" class="submit-btn" title="Send message">
                            <i class="fas fa-arrow-up"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Horizontal Suggestion Pills (Moved here for Gemini layout) -->
                <div class="suggestion-pills-row">
                    <button type="button" class="suggestion-pill" data-prompt="<?= htmlspecialchars($selectedPrompts['explain']) ?>">
                        <span class="pill-icon">💡</span> Explain
                    </button>
                    <button type="button" class="suggestion-pill" data-prompt="<?= htmlspecialchars($selectedPrompts['write']) ?>">
                        <span class="pill-icon">✍️</span> Write
                    </button>
                    <button type="button" class="suggestion-pill" data-prompt="<?= htmlspecialchars($selectedPrompts['build']) ?>">
                        <span class="pill-icon">🔨</span> Build
                    </button>
                    <button type="button" class="suggestion-pill" data-prompt="<?= htmlspecialchars($selectedPrompts['research']) ?>">
                        <span class="pill-icon">🔍</span> Deep Research
                    </button>
                </div>
            </form>
        </footer>
    </div>

    <!-- Toast for copy feedback -->
    <div id="copy-toast" class="copy-toast" style="display:none;">Copied to clipboard</div>

    <!-- Main application script -->
    <script src="settings.js?v=<?= time() ?>"></script>
    <script src="tutor_mysql.js?v=<?= time() ?>"></script>
</body>
</html>
