<?php
// Help — how TutorMind works, and what to do when something doesn't.
// Design system page (tm-tokens + tm-ds, like dashboard.php) + help.css.
// Content lives in $helpTopics below; the search and topic list are built from it.
// Every question has a stable id, so help#voice-mic-blocked links straight to it
// (for the tooltips planned later).
require_once 'includes/check_auth.php';
require_once 'includes/db_mysql.php';

$user_dark_mode = false;
if (!empty($_SESSION['user_id'])) {
    try {
        $stmt = getDbConnection()->prepare("SELECT dark_mode FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_dark_mode = (bool)$stmt->fetchColumn();
    } catch (Exception $e) {}
}
$v = fn($f) => filemtime($f);

// Answers are trusted static HTML (no user input), so they're printed as-is.
$helpTopics = [
    'start' => ['Getting started', [
        'what-is-tutormind' => ['What is TutorMind?',
            '<p>A tutor that teaches you, rather than handing you answers. Ask about anything you’re learning: it explains, then asks you to use what you just learned. That might be a quick question, a small exercise or a prediction.</p>
             <p>That back-and-forth is the point. Using an idea is what makes it stick.</p>'],
        'why-questions' => ['Why does the tutor ask me questions instead of just answering?',
            '<p>Pulling an idea back out of your head, and using it, moves it into long-term memory far better than reading an explanation does. So after explaining, the tutor usually checks in with a question or hands you something to try.</p>
             <p>If an explanation didn’t land, just say so. Ask it to go slower, use an example, or explain it a different way.</p>'],
        'start-lesson' => ['How do I start?',
            '<p>Type what you want to learn in the message box and send it, or tap one of the quick starts on the chat home screen. You can also attach your notes or slides and ask about them.</p>
             <p>On the first message of a new topic the tutor also plans the lesson behind the scenes, so that first reply can take a little longer.</p>
             <p>New here? <a href="chat#tour">Take the tour of the chat</a>. It shows you around in under a minute.</p>'],
        'personalize' => ['How do I tell TutorMind about me?',
            '<p>Open the profile menu (your name, bottom left in the chat) and choose <strong>Personalization</strong>. There you can set:</p>
             <ul>
                <li>what you’re aiming for, from remembering the key facts to creating something new with them</li>
                <li>how you like things explained</li>
                <li>your education, subject and what you already know</li>
                <li>your interests, which the tutor uses for its examples</li>
             </ul>'],
    ]],
    'chat' => ['Chatting with your tutor', [
        'uploads' => ['What can I upload?',
            '<p>Tap <strong>+</strong> in the message box to attach:</p>
             <ul>
                <li>PDFs, Word documents (.docx) and PowerPoint slides (.pptx)</li>
                <li>plain text files</li>
                <li>images: JPG, PNG, GIF, WebP and BMP</li>
             </ul>
             <p>To learn from a YouTube video, paste its link into your message. The tutor reads the video’s transcript, so this works for videos that have captions.</p>'],
        'edit-message' => ['Can I edit a message I sent?',
            '<p>Yes, your most recent one. Use the pencil beside it, change the text and save. The tutor then answers the new version.</p>'],
        'manage-chats' => ['How do I rename or delete a chat?',
            '<p>Your chats are listed in the sidebar. Each one has a pencil to rename it and a bin to delete it. Deleting a chat is permanent.</p>'],
        'reply-actions' => ['What are the buttons under each reply?',
            '<p>Under every reply you can have it <strong>read aloud</strong>, <strong>copy</strong> it, or rate it with a thumbs up or down. Ratings help us see which answers work.</p>'],
    ]],
    'exercises' => ['Questions and exercises', [
        'question-types' => ['What kinds of questions will I get?',
            '<p>The tutor can ask in a few ways, each built for a different kind of thinking:</p>
             <ul>
                <li><strong>Check:</strong> pick an answer and get instant feedback on why it’s right or wrong.</li>
                <li><strong>Your turn:</strong> answer in your own words.</li>
                <li><strong>Put these in order:</strong> tap the steps in the order they happen.</li>
                <li><strong>Complete the code:</strong> tap each blank to cycle through the options.</li>
                <li><strong>Guide it:</strong> tap your way through a diagram, checkpoint by checkpoint.</li>
                <li><strong>Worked examples, hints and graphs</strong> you can reveal step by step or explore with sliders.</li>
             </ul>'],
        'focus' => ['Why did a question pop up over the chat?',
            '<p>When the tutor asks you something, the question lifts into the middle of the screen so it has your full attention. Once you’ve answered, it waits for the tutor’s reply, then drops back into its place in the chat.</p>
             <p>Want to reread the explanation first? Choose <strong>Back to the lesson</strong> (or press Esc). The question goes back into the chat, and the expand icon on it brings it back up.</p>'],
        'wrong-answer' => ['What happens if I get it wrong?',
            '<p>Nothing bad. Wrong answers are part of learning, and each one tells you why it’s wrong. On a check, just try another option. On ordering and fill-in-the-blank questions you can keep adjusting until it’s right. If you’re stuck, reveal a hint: they start gentle and get more specific.</p>'],
        'run-code' => ['Can I run code?',
            '<p>For JavaScript exercises, yes. Write your code in the box, press <strong>Run</strong> to see its output, then <strong>Show my tutor</strong> to hand it in. For other languages, write your answer in your own words or as code and send it, and the tutor will check it.</p>'],
    ]],
    'dots' => ['The three dots', [
        'three-dots' => ['What do the three dots at the top mean?',
            '<p>Ideas stick after three kinds of contact:</p>
             <ul>
                <li><strong>connecting</strong> them to something you already know</li>
                <li><strong>building</strong> something with them</li>
                <li><strong>predicting</strong> with them</li>
             </ul>
             <p>Each dot fills in as you make one of those contacts in the conversation. When all three are filled, the chip says <strong>Encoded</strong>.</p>'],
        'fill-dots' => ['How do I fill them in?',
            '<p>By joining in. Offer your own comparison when the tutor asks what an idea reminds you of, try the exercises, and make your guess before a step is revealed. The dots follow what you do, not how long you spend.</p>'],
    ]],
    'timer' => ['Study timer and recall quiz', [
        'study-timer' => ['How does the study timer work?',
            '<p>Tap the clock in the top bar. Choose 15, 25, 45 or 60 minutes, then start, pause or reset it. It keeps running while you chat.</p>'],
        'recall-quiz' => ['What’s the recall quiz?',
            '<p>When the timer finishes, TutorMind asks you one question about what you just covered, without looking. The chat blurs so the answer isn’t on screen. Pick how it asks in the timer’s settings:</p>
             <ul>
                <li><strong>Gentle:</strong> multiple choice.</li>
                <li><strong>Standard:</strong> a short answer.</li>
                <li><strong>Challenge:</strong> explain or apply it in your own words.</li>
                <li><strong>Off:</strong> no quiz, just the timer.</li>
             </ul>'],
        'quiz-missing' => ['Why didn’t the quiz appear?',
            '<p>A few reasons:</p>
             <ul>
                <li>The quiz is set to <strong>Off</strong> in the timer.</li>
                <li>There’s no conversation open yet.</li>
                <li>You haven’t filled two of the <a href="#three-dots">three dots</a> in this conversation yet. You’ll see a “Keep going” note saying what to try first.</li>
                <li>The question couldn’t be prepared this time. Run the timer again.</li>
             </ul>'],
        'recall-score' => ['What does my recall score mean?',
            '<p>The arch fills to your score. At 75% or more, the dot lands on top: strong recall. It isn’t a grade. It shows what’s solid and what’s worth another look, and the notes under it say what the question covered.</p>'],
    ]],
    'voice' => ['Voice mode', [
        'voice-mode' => ['How do I use voice mode?',
            '<p>With the message box empty, tap the voice button beside it. Then just talk. When you pause, your words are sent, and the tutor answers out loud.</p>
             <ul>
                <li>Tap the bridge to pause listening, or to interrupt while the tutor is speaking.</li>
                <li>Choose <strong>End</strong> (or press Esc) to go back to the chat.</li>
             </ul>
             <p>Everything you say, and every reply, is saved to the conversation.</p>'],
        'voice-mic-blocked' => ['Voice mode can’t hear me',
            '<p>Check that your browser is allowed to use the microphone. Look for the microphone or lock icon in the address bar and allow it for TutorMind, then tap the bridge again. Voice mode works best in Chrome or Edge. Some other browsers don’t support speech recognition yet.</p>'],
        'voice-exercises' => ['Where do exercises go in voice mode?',
            '<p>They wait for you in the chat. Spoken replies skip code and exercises (they don’t read well out loud), and the transcript tells you when one is waiting.</p>'],
    ]],
    'progress' => ['Your learning and group study', [
        'your-learning' => ['What’s on the Your learning page?',
            '<p>Your streak and study days, the conversation to pick up where you left off, <strong>Worth another look</strong> (questions you missed, with one tap to go over them in the chat), <strong>How well it sticks</strong> (your recall scores) and <strong>Your subjects</strong>. Open it from the profile menu or the top bar.</p>'],
        'group-study' => ['How does group study work?',
            '<p>Start a room on a topic and share its 6-character code or invite link. The room opens as soon as one more person joins. Q, the group’s tutor, guides the discussion and makes sure quieter people get asked too. Your past rooms are listed so you can revisit them.</p>'],
    ]],
    'account' => ['Account and settings', [
        'reminders' => ['Can TutorMind remind me to study?',
            '<p>Yes. In <strong>Settings → Notifications</strong>, turn on study reminders and choose how often: daily, weekdays, every few days or weekly. Get them by email, as notifications on this device, or both.</p>'],
        'appearance' => ['How do I make the text bigger, or switch to dark mode?',
            '<p><strong>Settings → Appearance</strong> has a text-size slider, chat density and dark mode. Dark mode is also the moon button at the top of this page.</p>'],
        'password' => ['I forgot my password',
            '<p>On the sign-in page, choose <strong>Forgot password?</strong> and enter your email. We’ll send you a link to set a new one. To change a password you know, go to <strong>Settings → Security</strong>.</p>'],
        'delete-data' => ['How do I delete my chats or my account?',
            '<p><strong>Settings → Privacy and data</strong> lets you clear all of your conversations, or delete your account and everything in it. Both are permanent and can’t be undone.</p>'],
    ]],
    'trouble' => ['Something’s not working', [
        'slow-replies' => ['The tutor is taking a long time',
            '<p>The first reply on a new topic can take longer while the lesson is planned. At busy times TutorMind automatically switches to another AI service, which can add a few seconds. If nothing arrives, send your message again.</p>'],
        'looks-wrong' => ['The page looks wrong, or half dark',
            '<p>Browser extensions that recolour websites (Dark Reader, for example) can clash with TutorMind’s own themes. Turn the extension off for TutorMind and use the built-in dark mode instead.</p>'],
    ]],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Help — TutorMind</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon-new.svg">
    <link rel="icon" type="image/png" href="assets/icons/icon-512.png">
    <link rel="apple-touch-icon" href="assets/icons/icon-512.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Funnel+Display:wght@600;700&family=Outfit:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/tm-tokens.css?v=<?= $v('assets/css/tm-tokens.css') ?>">
    <link rel="stylesheet" href="assets/css/tm-ds.css?v=<?= $v('assets/css/tm-ds.css') ?>">
    <link rel="stylesheet" href="assets/css/help.css?v=<?= $v('assets/css/help.css') ?>">
</head>
<body class="ds-page hp-page<?= $user_dark_mode ? ' dark-mode' : '' ?>">
    <script>
        // Same theme key and fallbacks as the rest of the app (the server already applied the saved setting)
        (function () {
            var theme = null;
            try { theme = localStorage.getItem('tutormind-theme') || (localStorage.getItem('darkMode') === 'enabled' ? 'dark' : null); } catch (e) {}
            if (theme === 'dark') document.body.classList.add('dark-mode');
        })();
    </script>

    <?php $appNavCurrent = ''; include __DIR__ . '/includes/app_nav.php'; ?>

    <main class="ds-wrap hp-main">
        <header class="hp-head">
            <p class="ds-kicker">Help</p>
            <h1 class="hp-title">How can we help?</h1>
            <p class="hp-lede">How each part of TutorMind works, and what to do when something doesn’t.</p>
            <a class="ds-btn ds-btn--secondary ds-btn--sm hp-tour" href="chat#tour">Take the tour of the chat</a>
            <div class="hp-search" role="search">
                <label for="hpSearch" class="ds-visually-hidden">Search help</label>
                <svg class="ds-i hp-search__icon" aria-hidden="true"><use href="#i-search"/></svg>
                <input type="search" id="hpSearch" class="ds-input hp-search__input" placeholder="Search help, e.g. quiz, microphone, upload" autocomplete="off" spellcheck="false">
            </div>
            <p class="hp-count" id="hpCount" role="status" aria-live="polite"></p>
        </header>

        <div class="hp-layout">
            <nav class="hp-topics" aria-label="Help topics">
                <ul>
                    <?php foreach ($helpTopics as $id => [$title]): ?>
                    <li><a href="#<?= $id ?>" data-topic="<?= $id ?>"><?= htmlspecialchars($title) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <div class="hp-content">
                <?php foreach ($helpTopics as $id => [$title, $questions]): ?>
                <section class="hp-section" id="<?= $id ?>" aria-labelledby="<?= $id ?>-title">
                    <h2 class="hp-section__title" id="<?= $id ?>-title"><?= htmlspecialchars($title) ?></h2>
                    <div class="ds-faq">
                        <?php foreach ($questions as $qid => [$q, $answer]): ?>
                        <details class="ds-faq__item hp-item" id="<?= $qid ?>">
                            <summary><?= htmlspecialchars($q) ?><span class="ds-faq__plus" aria-hidden="true"></span></summary>
                            <div class="hp-answer"><?= $answer ?></div>
                        </details>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endforeach; ?>

                <div class="hp-empty" id="hpEmpty" hidden>
                    <h2 class="hp-empty__title">Nothing matches that yet</h2>
                    <p>Try a shorter word, or tell us what you were looking for and we’ll add it.</p>
                </div>

                <aside class="hp-stuck" aria-labelledby="hpStuckTitle">
                    <div>
                        <h2 class="hp-stuck__title" id="hpStuckTitle">Still stuck?</h2>
                        <p>Tell us what happened or what you were looking for, and we’ll look into it.</p>
                    </div>
                    <a class="ds-btn ds-btn--primary" href="chat#feedback">Send feedback</a>
                </aside>
            </div>
        </div>
    </main>

    <script src="assets/js/tm-ds.js?v=<?= $v('assets/js/tm-ds.js') ?>"></script>
    <script src="assets/js/help.js?v=<?= $v('assets/js/help.js') ?>"></script>
</body>
</html>
