<?php
/**
 * Migration: Add Comprehensive Learning Profile
 * 
 * Adds columns to support 9-screen interactive onboarding:
 * - Subject interests and primary subject
 * - Learning goals
 * - Curriculum system
 * - Assessment results and mastery level
 * - Study preferences (schedule, session length, explanation style)
 * - Notification preferences
 * - First lesson completion tracking
 */

require_once __DIR__ . '/../db_mysql.php';

try {
    $pdo = getDbConnection();
    
    echo "Starting comprehensive learning profile migration...\n\n";
    
    // Screen 2: Subjects
    echo "Adding subject tracking columns...\n";
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS subjects_of_interest TEXT COMMENT 'JSON array of selected subjects with subcategories'");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS primary_subject VARCHAR(100) COMMENT 'Starting subject selected'");
    
    // Screen 3: Goals
    echo "Adding learning goal column...\n";
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS learning_goal VARCHAR(50) COMMENT 'homework_help|exam_prep|concept_mastery|get_ahead|catch_up|general_learning'");
    
    // Screen 4: Education Level & Context
    echo "Adding curriculum system column...\n";
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS curriculum_system VARCHAR(50) COMMENT 'Common Core, IGCSE, IB, etc.'");
    
    // Screen 5: Knowledge Assessment Results
    echo "Adding assessment result columns...\n";
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS assessment_results TEXT COMMENT 'JSON: {subject: {score, masteryLevel, gaps: []}}'");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS baseline_mastery_level VARCHAR(20) COMMENT 'beginner|developing|proficient|advanced'");
    
    // Screen 6: Learning Preferences
    echo "Adding learning preference columns...\n";
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS study_schedule VARCHAR(30) COMMENT 'mornings|afternoons|evenings|late_nights|varies'");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS session_length VARCHAR(30) COMMENT 'quick_5_15|standard_15_30|deep_30_60|extended_60_plus'");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS explanation_style VARCHAR(30) COMMENT 'concise|detailed|conversational|adaptive'");
    
    // Screen 7: Notifications
    echo "Adding notification preference columns...\n";
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS notifications_enabled TINYINT DEFAULT 1");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS notification_frequency VARCHAR(20) COMMENT 'daily|three_weekly|weekly'");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS notification_time TIME COMMENT 'Preferred reminder time'");
    
    // Screen 8: First Lesson Completion
    echo "Adding first lesson tracking columns...\n";
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS first_lesson_completed TINYINT DEFAULT 0");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS first_lesson_data TEXT COMMENT 'JSON: problem solved, approach, feedback'");
    
    echo "\n✅ Migration completed successfully!\n\n";
    echo "New columns added:\n";
    echo "- subjects_of_interest (TEXT)\n";
    echo "- primary_subject (VARCHAR 100)\n";
    echo "- learning_goal (VARCHAR 50)\n";
    echo "- curriculum_system (VARCHAR 50)\n";
    echo "- assessment_results (TEXT)\n";
    echo "- baseline_mastery_level (VARCHAR 20)\n";
    echo "- study_schedule (VARCHAR 30)\n";
    echo "- session_length (VARCHAR 30)\n";
    echo "- explanation_style (VARCHAR 30)\n";
    echo "- notifications_enabled (TINYINT)\n";
    echo "- notification_frequency (VARCHAR 20)\n";
    echo "- notification_time (TIME)\n";
    echo "- first_lesson_completed (TINYINT)\n";
    echo "- first_lesson_data (TEXT)\n\n";
    
    // Verify columns were added
    echo "Verifying migration...\n";
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $requiredColumns = [
        'subjects_of_interest',
        'primary_subject',
        'learning_goal',
        'curriculum_system',
        'assessment_results',
        'baseline_mastery_level',
        'study_schedule',
        'session_length',
        'explanation_style',
        'notifications_enabled',
        'notification_frequency',
        'notification_time',
        'first_lesson_completed',
        'first_lesson_data'
    ];
    
    $missing = [];
    foreach ($requiredColumns as $col) {
        if (!in_array($col, $columns)) {
            $missing[] = $col;
        }
    }
    
    if (empty($missing)) {
        echo "✅ All columns verified successfully!\n";
    } else {
        echo "⚠️  Warning: Missing columns: " . implode(', ', $missing) . "\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
