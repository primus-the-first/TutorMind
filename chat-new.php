<?php
// Copy all the PHP from tutor_mysql.php but with new HTML
require_once 'check_auth.php';
require_once 'db_mysql.php';

$user_email = 'email@example.com';
$displayName = isset($_SESSION['first_name']) && !empty($_SESSION['first_name']) ? $_SESSION['first_name'] : (isset($_SESSION['username']) ? $_SESSION['username'] : 'User');
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Initialize Database
try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

// Fetch user data
if ($user_id) {
    try {
        $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $user_email = $user['email'];
        }
    } catch (Exception $e) {
        error_log("User fetch error: " . $e->getMessage());
    }
}

// Server-Side Rendering of Chat Messages
$ssr_messages_html = '';
$ssr_chat_active = false;
$ssr_conversation_title = 'TutorMind';

if (isset($_GET['conversation_id'])) {
    $convo_id = $_GET['conversation_id'];
    try {
        require_once 'vendor/autoload.php';
        
        $stmt = $pdo->prepare("SELECT title FROM conversations WHERE id = ? AND user_id = ?");
        $stmt->execute([$convo_id, $user_id]);
        $convo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($convo) {
            $ssr_chat_active = true;
            $ssr_conversation_title = $convo['title'];
            
            $stmt = $pdo->prepare("SELECT role, content FROM messages WHERE conversation_id = ? ORDER BY created_at ASC");
            $stmt->execute([$convo_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $Parsedown = new Parsedown();
            $Parsedown->setBreaksEnabled(true);
            
            foreach ($messages as $msg) {
                $role = $msg['role'];
                $roleClass = ($role === 'user') ? 'user' : 'ai';
                $avatar = ($role === 'user') ? '👤' : '🤖';
                $parts = json_decode($msg['content'], true);
                
                $messageContentHtml = '';
                if (is_array($parts)) {
                    foreach ($parts as $part) {
                        if (isset($part['text'])) {
                            $html = $Parsedown->text($part['text']);
                            $messageContentHtml .= $html;
                        }
                    }
                }
                
                $ssr_messages_html .= '
                <div class="message ' . $roleClass . '">
                    <div class="message-avatar">' . $avatar . '</div>
                    <div class="message-content">
                        ' . $messageContentHtml . '
                    </div>
                </div>';
            }
        }
    } catch (Exception $e) {
        error_log("SSR Error: " . $e->getMessage());
    }
}

// Server-Side Rendering of Chat History
$ssr_history_html = '';
try {
    $stmt = $pdo->prepare("SELECT id, title FROM conversations WHERE user_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$user_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($history as $convo) {
        $activeClass = (isset($convo_id) && $convo_id == $convo['id']) ? 'active' : '';
        $ssr_history_html .= '
        <div class="conversation-item ' . $activeClass . '" data-conversation-id="' . $convo['id'] . '" onclick="loadConversation(' . $convo['id'] . ')">
            <div class="conversation-title">' . htmlspecialchars($convo['title']) . '</div>
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TutorMind Chat - Modern Scholar</title>
    <link rel="icon" type="image/png" href="assets/favicon.png">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- MathJax -->
    <script>
        MathJax = {
            tex: { inlineMath: [['\\(', '\\)']], displayMath: [['\\[', '\\]']] }
        };
    </script>
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    
    <!-- Highlight.js -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    
    <!-- Modern Scholar Design -->
    <link rel="stylesheet" href="landing.css">
    <link rel="stylesheet" href="chat-interface.css">
</head>
<body>
    <!-- Header -->
    <div class="chat-header">
        <div class="chat-header-left">
            <button class="sidebar-toggle" id="sidebar-toggle" style="display: none;">
                <i class="fas fa-bars"></i>
            </button>
            <div class="chat-logo">🧠</div>
            <div class="chat-title">
                <h1>TutorMind</h1>
                <p class="chat-subtitle">Your Personal AI Tutor</p>
            </div>
        </div>
        <div class="chat-actions">
            <button class="icon-btn dark-mode-toggle" id="dark-mode-toggle" title="Toggle Dark Mode">
                <i class="fas fa-moon"></i>
            </button>
            <button class="icon-btn" onclick="window.location.href='dashboard.php'" title="Settings">
                <i class="fas fa-cog"></i>
            </button>
            <button class="icon-btn" onclick="window.location.href='auth_mysql?action=logout'" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </button>
        </div>
    </div>

    <!-- Main Container -->
    <div class="chat-container">
        <!-- Sidebar -->
        <div class="chat-sidebar" id="chat-sidebar">
            <div class="sidebar-header">
                <button class="new-chat-btn" onclick="window.location.href='chat-new'" id="newChatBtn">
                    <i class="fas fa-plus"></i> New Chat
                </button>
            </div>
            <div class="sidebar-section">
                <h3>Recent Conversations</h3>
                <div class="conversation-list" id="chat-history-container">
                    <?= $ssr_history_html ?>
                </div>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="chat-main">
            <div class="chat-messages" id="chat-container">
                <?php if (!$ssr_chat_active): ?>
                <div class="empty-state">
                    <div class="empty-icon">💬</div>
                    <h2 class="empty-title">Welcome back, <?= htmlspecialchars($displayName) ?>!</h2>
                    <p class="empty-subtitle">Start a conversation or pick up where you left off</p>
                </div>
                <?php else: ?>
                    <?= $ssr_messages_html ?>
                <?php endif; ?>
            </div>

            <!-- Input Area -->
            <div class="chat-input-area">
                <div class="suggestions-container">
                    <div class="suggestion-chip" onclick="insertSuggestion(this)">💡 Explain this concept</div>
                    <div class="suggestion-chip" onclick="insertSuggestion(this)">📝 Give me practice problems</div>
                    <div class="suggestion-chip" onclick="insertSuggestion(this)">🎯 Check my work</div>
                    <div class="suggestion-chip" onclick="insertSuggestion(this)">🔍 Break it down step-by-step</div>
                </div>

                <form id="tutorForm" class="input-wrapper">
                    <input type="hidden" id="conversation_id" name="conversation_id" value="<?= isset($_GET['conversation_id']) ? htmlspecialchars($_GET['conversation_id']) : '' ?>">
                    <input type="file" id="file-attachment" class="hidden-input" multiple style="display: none;">
                    
                    <div class="input-box">
                        <textarea id="question" name="question" placeholder="Ask me anything... (Press Enter to send)" rows="1"></textarea>
                        <div class="input-actions">
                            <label for="file-attachment" class="input-action-btn" data-action="image" title="Upload image">
                                <i class="fas fa-image"></i> Image
                            </label>
                            <button type="button" class="input-action-btn" data-action="voice" title="Voice input">
                                <i class="fas fa-microphone"></i> Voice
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="send-btn" id="ai-submit-btn" aria-label="Send message">
                        <i class="fas fa-paper-plane" aria-hidden="true"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.4/gsap.min.js"></script>
    <script src="tutor_mysql.js?v=<?= time() ?>"></script>
    
    <script>
        // Initialize Highlight.js
        if (typeof hljs !== 'undefined') {
            hljs.highlightAll();
        }
        
        // Load conversation function
        function loadConversation(id) {
            window.location.href = 'chat-new?conversation_id=' + id;
        }
        
        // Insert suggestion
        function insertSuggestion(chip) {
            const textarea = document.getElementById('question');
            textarea.value = chip.textContent.trim().replace(/^[^\s]+\s/, ''); // Remove emoji
            textarea.focus();
        }
    </script>
    
    <!-- Enhanced Chat Interface -->
    <script src="chat-interface.js?v=<?= time() ?>"></script>
</body>
</html>
