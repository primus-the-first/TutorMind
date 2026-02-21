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

// Initialize Database Connection Globally
try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

// Fetch user data
if ($user_id) {
    try {
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

// --- Server-Side Rendering of Chat Messages ---
$ssr_messages_html = '';
$ssr_chat_active = false;
$ssr_conversation_title = 'TutorMind';

if (isset($_GET['conversation_id'])) {
    $convo_id = $_GET['conversation_id'];
    try {
        require_once 'vendor/autoload.php'; // Needed for Parsedown
        
        // Verify ownership
        $stmt = $pdo->prepare("SELECT title FROM conversations WHERE id = ? AND user_id = ?");
        $stmt->execute([$convo_id, $user_id]);
        $convo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($convo) {
            $ssr_chat_active = true;
            $ssr_conversation_title = $convo['title'];
            
            // Fetch messages (limit 50 for initial load to be fast)
            $stmt = $pdo->prepare("SELECT id, role, content, is_edited FROM messages WHERE conversation_id = ? ORDER BY created_at ASC");
            $stmt->execute([$convo_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Find the last user message id for edit button
            $lastUserMsgId = null;
            foreach (array_reverse($messages) as $m) {
                if ($m['role'] === 'user') {
                    $lastUserMsgId = $m['id'];
                    break;
                }
            }
            
            $Parsedown = new Parsedown();
            $Parsedown->setBreaksEnabled(true);
            
            foreach ($messages as $msg) {
                $role = $msg['role']; // 'user' or 'model' (ai)
                $roleClass = ($role === 'user') ? 'user' : 'ai';
                $avatar = ($role === 'user') ? '👤' : '🤖';
                $parts = json_decode($msg['content'], true);
                
                $messageContentHtml = '';
                
                // Handle parts (text/images)
                // For user messages, file context is stored as text starting with
                // "Context from uploaded file '...':â€ — show a pill instead of the full text
                $attachmentPillsHtml = '';
                $questionHtml = '';
                
                if (is_array($parts)) {
                    foreach ($parts as $part) {
                        if (isset($part['text'])) {
                            if ($role === 'user' && strpos($part['text'], "Context from uploaded file '") === 0) {
                                // Extract filename from the context prefix
                                if (preg_match("/Context from uploaded file '([^']+)':/", $part['text'], $matches)) {
                                    $filename = htmlspecialchars($matches[1]);
                                } else {
                                    $filename = 'Document';
                                }
                                $attachmentPillsHtml .= '<div class="message-attachment-pill"><i class="fas fa-file-alt"></i> ' . $filename . '</div>';
                            } else {
                                // Normal text (user question or AI response)
                                $html = $Parsedown->text($part['text']);
                                $questionHtml .= $html;
                            }
                        }
                        if (isset($part['inline_data'])) {
                            $attachmentPillsHtml .= '<div class="message-attachment-pill"><i class="fas fa-image"></i> Image attached</div>';
                        }
                    }
                }
                
                // Show attachment pills first, then the actual message
                if (!empty($attachmentPillsHtml)) {
                    $messageContentHtml .= '<div class="message-attachments-grid">' . $attachmentPillsHtml . '</div>';
                }
                $messageContentHtml .= $questionHtml;

                // Edited badge
                $editedBadge = '';
                if ($msg['is_edited']) {
                    $editedBadge = '<span class="edited-badge">(edited)</span>';
                }

                // Edit button (only on last user message)
                $editButton = '';
                if ($role === 'user' && $msg['id'] == $lastUserMsgId) {
                    $editButton = '
                    <div class="message-actions">
                        <button class="edit-msg-btn" title="Edit message">
                            <i class="fas fa-pencil-alt"></i>
                        </button>
                    </div>';
                }
                
                $ssr_messages_html .= '
                <div class="message ' . $roleClass . '" data-message-id="' . $msg['id'] . '">
                    <div class="message-avatar">' . $avatar . '</div>
                    <div class="message-content">
                        ' . $messageContentHtml . $editedBadge . '
                    </div>' . $editButton . '
                </div>';
            }
        }
    } catch (Exception $e) {
        error_log("SSR Error: " . $e->getMessage());
    }
}

// --- Server-Side Rendering of Chat History ---
$ssr_history_html = '';
try {
    $stmt = $pdo->prepare("SELECT id, title FROM conversations WHERE user_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$user_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($history as $convo) {
        $activeClass = (isset($convo_id) && $convo_id == $convo['id']) ? 'active' : '';
        $ssr_history_html .= '
        <div class="history-item flex justify-between items-center">
            <a href="chat/' . $convo['id'] . '" class="flex-1 truncate ' . $activeClass . '" title="' . htmlspecialchars($convo['title']) . '" data-conversation-id="' . $convo['id'] . '" onclick="event.preventDefault(); loadConversation(' . $convo['id'] . ');">' . htmlspecialchars($convo['title']) . '</a>
            <button class="edit-btn text-gray-400 hover:text-white ml-2" title="Rename" onclick="event.stopPropagation(); enableRename(this.parentNode, ' . $convo['id'] . ', \'' . htmlspecialchars($convo['title'], ENT_QUOTES) . '\')"><i class="fas fa-pencil-alt"></i></button>
            <button class="delete-btn text-gray-400 hover:text-white ml-2" title="Delete" onclick="event.stopPropagation(); deleteConversation(' . $convo['id'] . ')"><i class="fas fa-trash-alt"></i></button>
        </div>';
    }
} catch (Exception $e) {
    error_log("SSR History Error: " . $e->getMessage());
}
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
    <link rel="icon" type="image/png" href="assets/favicon.png">
    <link rel="apple-touch-icon" href="assets/favicon.png">
    <base href="<?= rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') ?>/">
    <!-- Fonts and Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Source+Sans+Pro:wght@400;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
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

    <!-- Custom Styles -->
    <link rel="stylesheet" href="ui-overhaul.css?v=<?= time() ?>">
    <link rel="stylesheet" href="settings.css?v=<?= time() ?>">
    <link rel="stylesheet" href="logo.css?v=<?= time() ?>">
</head>
<body class="flex h-screen <?= $ssr_chat_active ? '' : 'chat-empty' ?>">

    <!-- Sidebar (Desktop Only) -->
    <aside id="sidebar" class="sidebar desktop-only">
        <div class="sidebar-top-row" style="display: flex; align-items: center; padding: 1rem 0.75rem; gap: 0.5rem;">
            <button id="menu-toggle" class="menu-toggle">
                <i class="fas fa-bars"></i>
            </button>
            <a href="index" class="app-logo" style="margin-bottom: 0; padding: 0;">
                <img src="assets/logo-bridge.svg" alt="TutorMind" class="w-8 h-8"> 
                <span class="app-logo-text">TutorMind</span>
            </a>
        </div>
        
        <div class="sidebar-header">
            <button id="newChatBtn" class="new-chat-btn">
                <i class="fas fa-pen"></i> <span>New chat</span>
            </button>
        </div>

        <h3 class="sidebar-section-title">Recent Conversations</h3>
        
        <nav id="chat-history-container" class="chat-history">
            <?= $ssr_history_html ?>
        </nav>
        
        <!-- User Profile Section -->
        <div id="user-account-dropdown" class="user-profile">
            <button id="user-account-trigger" class="user-info-button">
                <div class="user-avatar">
                    <?php if (isset($_SESSION['avatar_url']) && !empty($_SESSION['avatar_url'])): ?>
                        <img src="<?= htmlspecialchars($_SESSION['avatar_url']) ?>" alt="User Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                    <?php else: ?>
                        <?= htmlspecialchars(strtoupper(substr($displayName, 0, 1))) ?>
                    <?php endif; ?>
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
                        <?= htmlspecialchars(strtoupper(substr($displayName, 0, 1))) ?>
                    </div>
                    <div class="user-details">
                        <h4><?= htmlspecialchars($displayName) ?></h4>
                        <p><?= htmlspecialchars($user_email) ?></p>
                    </div>
                </div>
                <nav class="user-menu-nav">
                    <a href="dashboard.php" target="_blank"><i class="fas fa-chart-line"></i> Dashboard</a>
                    <a href="#"><i class="fas fa-star"></i> Upgrade plan</a>
                    <a href="#"><i class="fas fa-user-edit"></i> Personalization</a>
                    <a href="#" id="open-settings-btn"><i class="fas fa-cog"></i> Settings</a>
                    <a href="#" id="open-feedback-btn"><i class="fas fa-comment-dots"></i> Send Feedback</a>
                    <a href="#"><i class="fas fa-question-circle"></i> Help</a>
                    <a href="<?= rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') ?>/auth_mysql?action=logout" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Log out</a>
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


    <!-- Main Chat Area -->
    <div class="main-chat-wrapper">
        <header class="main-chat-header">
            <div class="header-left">
                <!-- Premium Nav Selector (Mobile Only) -->
                <div class="mobile-nav-selector mobile-only" id="mobileNavSelector">
                    <button type="button" class="nav-trigger-btn" id="navTrigger" title="Menu">
                        <i class="fas fa-bars"></i>
                        <span class="nav-pulse"></span>
                    </button>
                    
                    <div class="nav-drawer" id="navDrawer">
                        <div class="nav-name-display" id="navNameDisplay">Menu</div>
                        <div class="nav-options-container">
                            <a href="tutor_mysql.php" class="nav-option" data-name="New Chat" title="New Chat">
                                <i class="fas fa-plus"></i>
                            </a>
                            <a href="dashboard.php" class="nav-option" data-name="Dashboard" title="Dashboard">
                                <i class="fas fa-chart-line"></i>
                            </a>
                            <button type="button" class="nav-option" data-name="History" id="mobileHistoryBtn" title="Chat History">
                                <i class="fas fa-history"></i>
                            </button>
                            <button type="button" class="nav-option" data-name="Settings" id="mobileSettingsBtn" title="Settings">
                                <i class="fas fa-cog"></i>
                            </button>
                            <a href="<?= rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') ?>/auth_mysql?action=logout" class="nav-option" data-name="Logout" title="Logout">
                                <i class="fas fa-sign-out-alt"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <a href="index" class="mobile-logo-link mobile-only">
                    <img src="assets/logo-bridge.svg" alt="" aria-hidden="true">
                    <span class="app-logo-text">TutorMind</span>
                </a>
            </div>

            <a href="index" class="desktop-only">
                <h2 id="conversation-title" class="conversation-title" style="<?= $ssr_chat_active ? 'display:block' : 'display:none' ?>"><?= htmlspecialchars($ssr_conversation_title) ?></h2>
            </a>

            <!-- Dark Mode Toggle -->
            <button class="icon-btn" id="dark-mode-toggle" title="Toggle Dark Mode" aria-label="Toggle dark mode">
            </button>
        </header>

        <main id="chat-container" class="chat-content">
            <!-- Welcome Screen (Now contains the Quick Start content) -->
            <div id="welcome-screen" class="welcome-section" style="<?= $ssr_chat_active ? 'display:none' : 'display:flex' ?>">
                <div class="quick-start-content">
                    <!-- Glowing Orb Effect -->
                    <div class="quick-start-orb"></div>

                    <!-- Welcome Header with Typewriter Effect -->
                    <h1 class="quick-start-title">
                        <span id="welcome-greeting" data-username="<?= htmlspecialchars($displayName) ?>">Welcome back, <?= htmlspecialchars($displayName) ?>!</span>
                    </h1>
                    
                    

                    

                </div>
            </div>
            <!-- Chat messages will be appended here -->
            <?= $ssr_messages_html ?>
        </main>

        <footer class="input-bar-area">
            <form id="tutorForm" class="unified-input-container">
                <input type="hidden" id="conversation_id" name="conversation_id" value="<?= isset($_GET['conversation_id']) ? htmlspecialchars($_GET['conversation_id']) : '' ?>">
                <input type="file" id="file-attachment" name="attachment[]" class="hidden-input" multiple>
                
                <!-- Combined Input Bar -->
                <div class="combined-input-bar">
                    <!-- Preview Area (Inside the bar now) -->
                    <div id="attachment-preview-area"></div>

                    <!-- Input Main Area (Text) -->
                    <div class="input-main-area">
                         <textarea id="question" name="question" class="main-text-input" placeholder="Ask anything" rows="1" required style="resize: none; overflow-y: hidden;"></textarea>
                    </div>

                    <!-- Input Actions Row (Bottom) -->
                    <div class="input-actions-row">
                        <!-- Left Actions -->
                        <div class="left-actions">
                            <!-- Attach Button -->
                            <label for="file-attachment" class="action-pill-btn attach-pill" title="Add attachments">
                                <i class="fas fa-paperclip"></i> <span>Attach</span>
                            </label>

                            <!-- Tools Menu -->
                            <div class="tools-dropdown-wrapper">
                                <button type="button" id="tools-btn" class="action-pill-btn tool-pill" title="Tools" aria-haspopup="true" aria-expanded="false" aria-controls="tools-menu">
                                    <i class="fas fa-sliders-h" aria-hidden="true"></i> <span>Tools</span>
                                </button>
                                <!-- Tools Menu Content (Same as before) -->
                                <div id="tools-menu" class="tools-menu hidden" role="menu" aria-labelledby="tools-btn">
                                    <button type="button" class="tools-menu-item" data-goal="homework_help">
                                        <span class="tools-menu-emoji">📚</span>
                                        <div class="tools-menu-text">
                                            <span class="tools-menu-title">Homework Help</span>
                                            <span class="tools-menu-desc">Get step-by-step guidance</span>
                                        </div>
                                    </button>
                                    <button type="button" class="tools-menu-item" data-goal="test_prep">
                                        <span class="tools-menu-emoji">🎯</span>
                                        <div class="tools-menu-text">
                                            <span class="tools-menu-title">Test Prep</span>
                                            <span class="tools-menu-desc">Prepare for exams</span>
                                        </div>
                                    </button>
                                    <button type="button" class="tools-menu-item" data-goal="explore">
                                        <span class="tools-menu-emoji">💡</span>
                                        <div class="tools-menu-text">
                                            <span class="tools-menu-title">Explore Topic</span>
                                            <span class="tools-menu-desc">Learn something new</span>
                                        </div>
                                    </button>
                                    <button type="button" class="tools-menu-item" data-goal="practice">
                                        <span class="tools-menu-emoji">✏️</span>
                                        <div class="tools-menu-text">
                                            <span class="tools-menu-title">Practice</span>
                                            <span class="tools-menu-desc">Solve problems</span>
                                        </div>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Learning Level Section -->
                            <div class="learning-level-wrapper">
                                <!-- Desktop Select (Hidden on mobile) -->
                                <select id="learningLevel" name="learningLevel" class="action-pill-btn tool-pill desktop-only" title="Reasoning level" style="outline:none; cursor:pointer;">
                                    <option value="Remember">Remember</option>
                                    <option value="Understand" selected>Understand</option>
                                    <option value="Apply">Apply</option>
                                    <option value="Analyze">Analyze</option>
                                    <option value="Evaluate">Evaluate</option>
                                    <option value="Create">Create</option>
                                </select>

                                <!-- Mobile Custom Selector (Hidden on desktop) -->
                                <div class="mobile-level-selector mobile-only" id="mobileLevelSelector">
                                    <button type="button" class="level-trigger-btn" id="levelTrigger" title="Set thinking level">
                                        <i class="fas fa-brain"></i>
                                        <span class="active-level-indicator"></span>
                                    </button>
                                    
                                    <div class="level-drawer" id="levelDrawer">
                                        <div class="level-name-display" id="levelNameDisplay">Understand</div>
                                        <div class="level-options-container">
                                            <button type="button" class="level-option" data-value="Remember" data-name="Remember" title="Remember (Recall facts)">
                                                <i class="fas fa-box-archive"></i>
                                            </button>
                                            <button type="button" class="level-option" data-value="Understand" data-name="Understand" title="Understand (Connect concepts)">
                                                <i class="fas fa-puzzle-piece"></i>
                                            </button>
                                            <button type="button" class="level-option" data-value="Apply" data-name="Apply" title="Apply (Knowledge in action)">
                                                <i class="fas fa-bolt-lightning"></i>
                                            </button>
                                            <button type="button" class="level-option" data-value="Analyze" data-name="Analyze" title="Analyze (Deep dissection)">
                                                <i class="fas fa-microscope"></i>
                                            </button>
                                            <button type="button" class="level-option" data-value="Evaluate" data-name="Evaluate" title="Evaluate (Judge & Verify)">
                                                <i class="fas fa-scale-balanced"></i>
                                            </button>
                                            <button type="button" class="level-option" data-value="Create" data-name="Create" title="Create (Original Genesis)">
                                                <i class="fas fa-atom"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Actions -->
                        <div class="right-actions">
                             <!-- Voice input (quick) - Moved
                             <button type="button" class="voice-action-btn voice-btn" title="Voice input" style="display:none">
                                <i class="fas fa-microphone-lines"></i> <span>Voice</span>
                            </button>
                             -->
                             <!-- Voice Mode (full conversation) -->
                             <button type="button" class="voice-mode-trigger-btn" title="Voice Mode" id="voice-mode-trigger">
                                <i class="fas fa-waveform-lines default-voice-icon"></i>
                                <i class="fas fa-microphone mobile-voice-icon" style="display:none"></i> 
                                <span>Voice Mode</span>
                            </button>
                             <!-- Submit -->
                            <button type="submit" id="ai-submit-btn" class="submit-circle-btn" title="Send message" aria-label="Send message">
                                <i class="fas fa-arrow-up" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>

        </footer>
        
        <!-- Complete setup link for personalized experience (Moved after input area for mobile flexibility) -->
        <div class="setup-link-row">
            <a href="onboarding" class="setup-link">
                <i class="fas fa-magic"></i> Complete setup for a personalized experience
            </a>
        </div>
    </div>

    <!-- Voice Mode Overlay -->
    <div id="voice-mode-overlay" class="voice-mode-overlay hidden">
        <button class="voice-mode-close" id="voice-mode-close" title="Exit Voice Mode">
            <i class="fas fa-times"></i>
        </button>
        
        <div class="voice-mode-content">
            <!-- Animated Circle -->
            <div class="voice-mode-circle-container">
                <div class="voice-mode-circle" id="voice-mode-circle">
                    <div class="voice-mode-circle-inner"></div>
                    <div class="voice-mode-ripple"></div>
                    <div class="voice-mode-ripple"></div>
                    <div class="voice-mode-ripple"></div>
                </div>
            </div>
            
            <!-- Status Text -->
            <div class="voice-mode-status" id="voice-mode-status">Tap to speak</div>
            
            <!-- Transcript Area -->
            <div class="voice-mode-transcript" id="voice-mode-transcript">
                <!-- Messages will be added here dynamically -->
            </div>
            
            <!-- Hint Text -->
            <div class="voice-mode-hint">Press <kbd>Esc</kbd> to exit</div>
        </div>
    </div>

    <!-- Toast for copy feedback -->
    <div id="copy-toast" class="copy-toast" style="display:none;">Copied to clipboard</div>

    <!-- GSAP Animation Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.4/gsap.min.js"></script>
    
    <!-- Main application scripts -->
    <script src="settings.js?v=<?= time() ?>"></script>
    <script src="session-context.js?v=<?= time() ?>"></script>
    <script src="quick-start.js?v=<?= time() ?>"></script>
    <script src="tutor_mysql.js?v=<?= time() ?>"></script>
    
    <!-- Initialize Highlight.js -->
    <script>
        if (typeof hljs !== 'undefined') {
            hljs.highlightAll();
        }
    </script>
    
    <!-- Enhanced Chat Interface (New!) -->
    <script src="chat-interface.js?v=<?= time() ?>"></script>

    <!-- Mobile Navigation Layer (Icons popup) -->
    <div id="mobile-sidebar-overlay" class="sidebar-overlay hidden mobile-only"></div>

    <!-- History Tray (Mobile Only) -->
    <div id="mobile-history-tray" class="mobile-history-tray mobile-only">
        <div class="history-tray-header">
            <h3>Recent Conversations</h3>
            <button id="closeHistoryTray"><i class="fas fa-times"></i></button>
        </div>
        <div class="history-tray-content">
            <?= $ssr_history_html ?>
        </div>
    </div>
</body>
</html>

