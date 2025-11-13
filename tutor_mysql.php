<?php
require_once 'check_auth.php'; // Secure this page
require_once 'db_mysql.php';

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
    <title>AI Tutor | Grade Target</title>
    <!-- Tailwind CSS CDN for modern styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Outfit font for clean and modern typography -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <!-- Add this to the <head> of your tutor.html file -->
    <script>
      MathJax = {
        tex: {
          inlineMath: [['\(', '\)'], ['$', '$']],
          displayMath: [['\[', '\]'], ['$$', '$$']]
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
<body class="bg-gray-100 flex h-screen">
    <!-- Sidebar -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-30 w-64 bg-gray-800 text-white flex flex-col transform -translate-x-full md:relative md:translate-x-0 transition-transform duration-300 ease-in-out">
        <div class="p-4 border-b border-gray-700"> <!-- New Chat Button -->
            <button id="newChatBtn" class="w-full bg-purple-700 hover:bg-purple-600 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-plus mr-2"></i> New Chat
            </button>
        </div>
        <nav id="chat-history-container" class="flex-1 p-4 space-y-2 overflow-y-auto" onclick="document.getElementById('sidebar').classList.remove('open'); document.getElementById('sidebar-overlay').classList.add('hidden');">
            <!-- Chat history links will be populated here -->
        </nav>
        <!-- User Account Dropdown -->
        <div class="p-4 border-t border-gray-700">
            <div id="user-account-dropdown" class="relative">
                <!-- Trigger Element -->
                <button id="user-account-trigger" class="w-full flex items-center text-left p-2 rounded-lg hover:bg-gray-700 transition-colors duration-200">
                    <div class="w-10 h-10 rounded-full bg-purple-500 flex items-center justify-center font-bold text-white mr-3">
                        <?= htmlspecialchars(strtoupper(substr($_SESSION['username'], 0, 1)))
?>
                    </div>
                    <div class="flex-1">
                        <p class="font-semibold text-white truncate"><?= htmlspecialchars($_SESSION['username']) ?></p>
                        <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($user_email) ?></p>
                    </div>
                    <i class="fas fa-chevron-up text-gray-400 ml-2 transform transition-transform duration-200" id="user-account-chevron"></i>
                </button>

                <!-- Dropdown Menu -->
                <div id="user-account-menu" class="absolute bottom-full left-0 right-0 mb-2 w-full bg-gray-900 rounded-lg shadow-lg border border-gray-700 hidden">
                    <!-- User Header -->
                    <div class="p-4 border-b border-gray-700">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full bg-purple-500 flex items-center justify-center font-bold text-white mr-3">
                                <?= htmlspecialchars(strtoupper(substr($_SESSION['username'], 0, 1)))
?>
                            </div>
                            <div class="flex-1">
                                <p class="font-semibold text-white truncate"><?= htmlspecialchars($_SESSION['username']) ?></p>
                                <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($user_email) ?></p>
                            </div>
                        </div>
                    </div>
                    <!-- Menu List -->
                    <nav class="p-2">
                        <a href="#" class="flex items-center px-3 py-2 text-gray-300 hover:bg-gray-700 rounded-md"><i class="fas fa-star w-6 text-yellow-400"></i> Upgrade plan</a>
                        <a href="#" class="flex items-center px-3 py-2 text-gray-300 hover:bg-gray-700 rounded-md"><i class="fas fa-user-edit w-6 text-blue-400"></i> Personalization</a>
                        <a href="profile.php" class="flex items-center px-3 py-2 text-gray-300 hover:bg-gray-700 rounded-md"><i class="fas fa-cog w-6 text-gray-400"></i> Settings</a>
                        <a href="#" class="flex items-center px-3 py-2 text-gray-300 hover:bg-gray-700 rounded-md"><i class="fas fa-question-circle w-6 text-green-400"></i> Help</a>
                        <a href="auth_mysql.php?action=logout" class="flex items-center px-3 py-2 text-red-400 hover:bg-red-500/20 hover:text-red-300 rounded-md mt-2 border-t border-gray-700 pt-3"><i class="fas fa-sign-out-alt w-6"></i> Log out</a>
                        <!-- Dark Mode Toggle -->
                        <div class="flex items-center justify-between px-3 py-2 text-gray-300 mt-2 border-t border-gray-700 pt-3">
                            <span class="flex items-center mr-2">
                                <i class="fas fa-moon w-6 mr-2 text-blue-300"></i>
                                <span class="text-white">Dark Mode</span>
                            </span>
                            <label for="darkModeToggle" class="relative inline-flex items-center cursor-pointer ml-2">
                                <input type="checkbox" id="darkModeToggle" class="sr-only">
                                <div class="block bg-gray-600 w-10 h-6 rounded-full"></div>
                                <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition"></div>
                            </label>
                        </div>
                    </nav>
                </div>
            </div>
        </div>
    </aside>

    <!-- Overlay for mobile sidebar -->
    <div id="sidebar-overlay" class="sidebar-overlay fixed inset-0 bg-black bg-opacity-50 z-20 hidden md:hidden"></div>

    <div class="container mx-auto bg-white shadow-xl rounded-xl flex flex-col h-full overflow-hidden flex-1">
        <!-- Header Section -->
        <header class="header bg-white rounded-t-xl">
            <div class="header-content p-4 border-b flex items-center justify-between">
                <!-- Hamburger Menu for mobile -->
                <button id="menu-toggle" class="md:hidden text-gray-600 hover:text-indigo-600 p-2 rounded-md">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
                <!-- This div is now a spacer to balance the hamburger menu button -->
                <div class="flex-grow"></div>
                <!-- Spacer to keep title centered on mobile -->
                <div class="w-8 md:hidden"></div>
            </div>
        </header>

        <!-- Main Content -->
        <main id="chat-container" class="chat-messages flex-1 p-4 overflow-y-auto">
            <!-- Welcome Screen (visible on new chat) -->
            <div id="welcome-screen" class="flex flex-col items-center justify-center h-full text-center">
                <header class="mb-8">
                    <h1 class="text-4xl font-bold bg-gradient-to-r from-purple-600 to-indigo-800 bg-clip-text text-transparent mb-2">
                        Hi there, <?= htmlspecialchars($_SESSION['username']) ?>
                    </h1>
                    <p class="text-lg text-gray-700 font-semibold mb-4">What would you like to know?</p>
                </header>

                <!-- Prompt Cards Grid -->
                <div class="w-full max-w-4xl mx-auto">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="prompt-card" data-prompt="Explain the concept of photosynthesis in simple terms." role="button" tabindex="0" aria-label="Explain a concept">
                            <span class="text-2xl text-green-500"><i class="fas fa-leaf"></i></span>
                            <h3 class="font-semibold text-gray-800">Explain a concept</h3>
                        </div>
                        <div class="prompt-card" data-prompt="Help me debug this Python code that's supposed to sort a list." role="button" tabindex="0" aria-label="Help me debug code">
                            <span class="text-2xl text-red-500"><i class="fas fa-bug"></i></span>
                            <h3 class="font-semibold text-gray-800">Help me debug code</h3>
                        </div>
                        <div class="prompt-card" data-prompt="Summarize the main causes of World War I." role="button" tabindex="0" aria-label="Summarize a topic">
                            <span class="text-2xl text-yellow-600"><i class="fas fa-scroll"></i></span>
                            <h3 class="font-semibold text-gray-800">Summarize a topic</h3>
                        </div>
                        <div class="prompt-card" data-prompt="Give me a challenging math problem about calculus." role="button" tabindex="0" aria-label="Give me a challenge">
                            <span class="text-2xl text-blue-500"><i class="fas fa-brain"></i></span>
                            <h3 class="font-semibold text-gray-800">Give me a challenge</h3>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Chat messages will be appended here dynamically -->
        </main>

        <!-- Footer with chat input -->
        <footer class="p-4 bg-white border-t chat-input-area">
            <div id="attachment-preview-area"></div> <!-- To show selected file -->
            <form id="tutorForm" class="flex items-center gap-4">
                <input type="hidden" id="conversation_id" name="conversation_id" value="">

                <!-- File Upload Button -->
                <label for="file-attachment" class="file-input-label" title="Attach file">
                    <i class="fas fa-paperclip"></i>
                </label>
                <input type="file" id="file-attachment" name="attachment" accept=".txt,.pdf,.docx,.pptx,.jpg,.jpeg,.png,.gif,.bmp,.webp">

                <input type="text" id="question" name="question" class="form-input flex-1" placeholder="Ask about your file or anything else..." required>
                
                <select id="learningLevel" name="learningLevel" class="form-select" title="Select response depth based on Bloom's Taxonomy">
                    <option value="Remember">Remember</option>
                    <option value="Understand" selected>Understand</option>
                    <option value="Apply">Apply</option>
                    <option value="Analyze">Analyze</option>
                    <option value="Evaluate">Evaluate</option>
                    <option value="Create">Create</option>
                </select>

                <button type="submit" id="ai-submit-btn" class="btn btn-primary px-6 py-2 text-white font-semibold rounded-lg shadow-md">
                    <i class="fas fa-paper-plane"></i> Send
                </button>
            </form>
        </footer>
    </div>

    <script src="tutor_mysql.js?v=2"></script>
</body>
</html>
