<?php
require_once 'check_auth.php'; // Secure this page
require_once 'db.php';

$user_email = '';
try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $user_email = $user['email'];
    }
} catch (Exception $e) {
    error_log("Tutor page user fetch error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Tutor | TutorMind</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="settings.css?v=<?= time() ?>">
    <script>
      MathJax = {
        tex: {
          inlineMath: [['\\(', '\\)'], ['$', '$']],
          displayMath: [['\\[', '\\]'], ['$$', '$$']]
        },
        chtml: {
          fontCache: 'global'
        }
      };
    </script>
    <script type="text/javascript" id="MathJax-script" async
      src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js">
    </script>
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <button id="newChatBtn" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-plus"></i> New Chat
                </button>
            </div>
            <nav id="chat-history-container" class="chat-history">
                <!-- Chat history populated by JS -->
            </nav>
            <div class="sidebar-footer">
                <div id="user-account-dropdown" class="user-menu">
                    <button id="user-account-trigger" class="user-menu-trigger">
                        <div class="user-avatar">
                            <?= htmlspecialchars(strtoupper(substr($_SESSION['username'], 0, 1)))
                        ?>
                        </div>
                        <div class="user-info">
                            <p class="user-name"><?= htmlspecialchars($_SESSION['username']) ?></p>
                            <p class="user-email"><?= htmlspecialchars($user_email) ?></p>
                        </div>
                        <i class="fas fa-chevron-up" id="user-account-chevron"></i>
                    </button>
                    <div id="user-account-menu" class="user-menu-dropdown hidden">
                        <a href="#" id="open-settings-btn"><i class="fas fa-cog w-6"></i> Settings</a>
                        <a href="#"><i class="fas fa-star w-6 text-yellow-400"></i> Upgrade plan</a>
                        <a href="auth.php?action=logout" class="logout-link"><i class="fas fa-sign-out-alt w-6"></i> Log out</a>
                        <div class="dark-mode-toggle">
                            <span><i class="fas fa-moon"></i> Dark Mode</span>
                            <label class="switch">
                                <input type="checkbox" id="darkModeToggle">
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="chat-header">
                <button id="menu-toggle" class="btn md:hidden"><i class="fas fa-bars"></i></button>
                <h2 class="text-lg font-semibold">TutorMind AI</h2>
                <div class="w-8"></div> <!-- Spacer -->
            </header>

            <div id="chat-container" class="chat-messages">
                <div id="welcome-screen" class="welcome-screen">
                    <h1>Hi, <?= htmlspecialchars($_SESSION['username']) ?>!</h1>
                    <p>What would you like to know?</p>
                    <div class="prompt-cards-grid">
                        <div class="prompt-card" data-prompt="Explain the concept of photosynthesis in simple terms.">
                            <i class="fas fa-leaf text-2xl text-green-500"></i>
                            <h3>Explain a concept</h3>
                        </div>
                        <div class="prompt-card" data-prompt="Help me debug this Python code that's supposed to sort a list.">
                            <i class="fas fa-bug text-2xl text-red-500"></i>
                            <h3>Help me debug code</h3>
                        </div>
                        <div class="prompt-card" data-prompt="Summarize the main causes of World War I.">
                            <i class="fas fa-scroll text-2xl text-yellow-600"></i>
                            <h3>Summarize a topic</h3>
                        </div>
                        <div class="prompt-card" data-prompt="Give me a challenging math problem about calculus.">
                            <i class="fas fa-brain text-2xl text-blue-500"></i>
                            <h3>Give me a challenge</h3>
                        </div>
                    </div>
                </div>
                <!-- Messages will be appended here -->
            </div>

            <footer class="chat-input-area">
                <div id="attachment-preview-area"></div>
                <form id="tutorForm" class="chat-input-form">
                    <input type="hidden" id="conversation_id" name="conversation_id" value="">
                    <label for="file-attachment" class="btn" title="Attach file">
                        <i class="fas fa-paperclip"></i>
                    </label>
                    <input type="file" id="file-attachment" name="attachment" class="hidden">
                    <input type="text" id="question" name="question" class="form-input" placeholder="Ask anything..." style="flex:1;" required>
                    <button type="submit" id="ai-submit-btn" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            </footer>
        </main>
    </div>

    <div id="sidebar-overlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-20 md:hidden"></div>
    <div id="copy-toast" style="position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background-color: #333; color: white; padding: 10px 20px; border-radius: 8px; z-index: 1000; display: none;">Copied!</div>


    <script src="settings.js?v=<?= time() ?>"></script>
    <script src="tutor_mysql.js?v=<?= time() ?>"></script>
</body>
</html>