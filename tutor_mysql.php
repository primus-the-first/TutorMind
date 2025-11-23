<?php
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

if ($user_id) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $user_email = $user['email'];
        }
    } catch (Exception $e) {
        error_log("Tutor page user fetch error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    <script src="https://polyfill.io/v3/polyfill.min.js?features=es6"></script>
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    
    <!-- Highlight.js for Syntax Highlighting -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/vs2015.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>

    <!-- Custom Styles for the new UI Overhaul -->
    <link rel="stylesheet" href="ui-overhaul.css?v=<?= time() ?>">
    <link rel="stylesheet" href="settings.css?v=<?= time() ?>">
    <link rel="stylesheet" href="logo.css?v=<?= time() ?>">
</head>
<body class="flex h-screen">

    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar">
        <div class="sidebar-top-row" style="display: flex; align-items: center; padding: 1rem 0.75rem; gap: 0.5rem;">
            <button id="menu-toggle" class="menu-toggle">
                <i class="fas fa-bars"></i>
            </button>
            <a href="index.html" class="app-logo" style="margin-bottom: 0; padding: 0;">
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
                    <a href="auth_mysql.php?action=logout" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Log out</a>
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
            <button id="mobile-menu-toggle" class="menu-toggle mobile-only">
                <i class="fas fa-bars"></i>
            </button>
            <a href="index.html" style="text-decoration: none; color: inherit;">
                <h2 id="conversation-title" class="conversation-title">TutorMind</h2>
            </a>
        </header>

        <main id="chat-container" class="chat-content">
            <!-- Welcome Screen -->
            <div id="welcome-screen" class="welcome-section">
                <div class="gradient-orb"></div>
                <h1>Hi there, <?= htmlspecialchars($displayName) ?></h1>
                <p>What would you like to know?</p>
                <div class="suggestion-grid">
                    <div class="suggestion-card" data-prompt="Explain the concept of photosynthesis in simple terms." role="button">
                        <div class="card-icon purple"><i class="fas fa-leaf"></i></div>
                        <h4>Explain a concept</h4>
                    </div>
                    <div class="suggestion-card" data-prompt="Help me debug this Python code that's supposed to sort a list." role="button">
                        <div class="card-icon orange"><i class="fas fa-bug"></i></div>
                        <h4>Help me debug code</h4>
                    </div>
                    <div class="suggestion-card" data-prompt="Summarize the main causes of World War I." role="button">
                        <div class="card-icon peach"><i class="fas fa-scroll"></i></div>
                        <h4>Summarize a topic</h4>
                    </div>
                    <div class="suggestion-card" data-prompt="Give me a challenging math problem about calculus." role="button">
                        <div class="card-icon light-purple"><i class="fas fa-brain"></i></div>
                        <h4>Give me a challenge</h4>
                    </div>
                </div>
            </div>
            <!-- Chat messages will be appended here -->
        </main>

        <footer class="input-bar-area">
            <div id="attachment-preview-area"></div>
            <form id="tutorForm" class="input-form">
                <input type="hidden" id="conversation_id" name="conversation_id" value="">
                
                <label for="file-attachment" class="icon-btn" title="Attach file">
                    <i class="fas fa-paperclip"></i>
                </label>
                <input type="file" id="file-attachment" name="attachment" class="hidden-input">

                <input type="text" id="question" name="question" class="text-input" placeholder="Ask about your file or anything else..." required>
                
                <select id="learningLevel" name="learningLevel" class="dropdown-btn" title="Select response depth">
                    <option value="Remember">Remember</option>
                    <option value="Understand" selected>Understand</option>
                    <option value="Apply">Apply</option>
                    <option value="Analyze">Analyze</option>
                    <option value="Evaluate">Evaluate</option>
                    <option value="Create">Create</option>
                </select>

                <button type="submit" id="ai-submit-btn" class="send-btn">
                    <i class="fas fa-paper-plane"></i>
                </button>
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
