<?php
/**
 * ONE-OFF: reset all accounts to a fresh-onboarding state ahead of a new
 * testing round, while preserving one account's activity data untouched.
 *
 * - Keeps every user row (no accounts deleted).
 * - Resets onboarding_completed + every onboarding-derived profile column
 *   to their defaults, for ALL users.
 * - Deletes conversations/messages/recall_quizzes/pomodoro_sessions/
 *   push_subscriptions/chat_rate_limits for every user EXCEPT $KEEP_USER_ID.
 * - Deletes user_tokens entirely (forces everyone, including $KEEP_USER_ID,
 *   to log in again).
 * - Leaves knowledge_base, feedback, and login_attempts untouched.
 *
 * CLI only. Run once, then delete this file — re-running it later would
 * wipe activity that's accumulated since the last run.
 */

if (php_sapi_name() !== 'cli') {
    exit("This script is CLI only.\n");
}

require_once __DIR__ . '/../includes/db_mysql.php';

$KEEP_USER_ID = 1; // confirm this is the right id on THIS environment before running

$pdo = getDbConnection();

echo "=== Users on this environment (confirm KEEP_USER_ID is correct before proceeding) ===\n";
foreach ($pdo->query("SELECT id, username, email FROM users ORDER BY id")->fetchAll() as $row) {
    $marker = ($row['id'] == $KEEP_USER_ID) ? '  <-- KEEP' : '';
    echo "id={$row['id']} {$row['username']} {$row['email']}{$marker}\n";
}

echo "\n=== Before ===\n";
foreach (['conversations', 'messages', 'recall_quizzes', 'pomodoro_sessions', 'push_subscriptions', 'chat_rate_limits', 'user_tokens'] as $t) {
    echo "$t: " . $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn() . "\n";
}

$pdo->beginTransaction();
try {
    $pdo->prepare("DELETE FROM messages WHERE conversation_id IN (SELECT id FROM (SELECT id FROM conversations WHERE user_id != ?) AS c)")
        ->execute([$KEEP_USER_ID]);
    $pdo->prepare("DELETE FROM recall_quizzes WHERE user_id != ?")->execute([$KEEP_USER_ID]);
    $pdo->prepare("DELETE FROM pomodoro_sessions WHERE user_id != ?")->execute([$KEEP_USER_ID]);
    $pdo->prepare("DELETE FROM push_subscriptions WHERE user_id != ?")->execute([$KEEP_USER_ID]);
    $pdo->prepare("DELETE FROM chat_rate_limits WHERE user_id != ?")->execute([$KEEP_USER_ID]);
    $pdo->prepare("DELETE FROM conversations WHERE user_id != ?")->execute([$KEEP_USER_ID]);

    $pdo->exec("DELETE FROM user_tokens");

    $pdo->exec("
        UPDATE users SET
            onboarding_completed = 0,
            subjects_of_interest = NULL,
            primary_subject = NULL,
            learning_goal = NULL,
            curriculum_system = NULL,
            assessment_results = NULL,
            baseline_mastery_level = NULL,
            study_schedule = NULL,
            session_length = NULL,
            explanation_style = NULL,
            notifications_enabled = 1,
            notification_frequency = NULL,
            notification_time = NULL,
            first_lesson_completed = 0,
            first_lesson_data = NULL,
            profile_data = NULL,
            knowledge_level = NULL,
            interests = NULL,
            last_reminder_sent_at = NULL,
            education_level = NULL,
            field_of_study = NULL,
            country = NULL,
            primary_language = 'English',
            institution = NULL
    ");

    $pdo->commit();
    echo "\nCommitted.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "ROLLED BACK: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== After ===\n";
foreach (['conversations', 'messages', 'recall_quizzes', 'pomodoro_sessions', 'push_subscriptions', 'chat_rate_limits', 'user_tokens'] as $t) {
    echo "$t: " . $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn() . "\n";
}

echo "\n=== Users ===\n";
foreach ($pdo->query("SELECT id, username, onboarding_completed FROM users ORDER BY id")->fetchAll() as $row) {
    echo "id={$row['id']} {$row['username']} onboarding_completed={$row['onboarding_completed']}\n";
}
