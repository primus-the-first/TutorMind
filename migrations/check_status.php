<?php
/**
 * Migration status checker.
 *
 * There is no migrations-log table in this project — every migration is
 * applied by hand over SSH (see README.md), so it's easy to lose track of
 * which files have actually run on a given database (this is exactly how
 * chat_rate_limits went missing on prod: the file didn't even reach git
 * until 2ad3910, and nothing ever re-checked it afterwards).
 *
 * This script does NOT apply anything. It inspects information_schema for
 * one representative table/column/index per migration file and reports
 * APPLIED / MISSING / PARTIAL, so "did this environment get this migration"
 * stops being a guess.
 *
 * Usage (same DB config auto-detection as the app itself — config-sql.ini
 * locally, config.ini on prod):
 *   php migrations/check_status.php
 */

require_once __DIR__ . '/../includes/db_mysql.php';

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    if (!tableExists($pdo, $table)) return false;
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function indexExists(PDO $pdo, string $table, string $indexName): bool {
    if (!tableExists($pdo, $table)) return false;
    $stmt = $pdo->query("SHOW INDEX FROM `{$table}` WHERE Key_name = " . $pdo->quote($indexName));
    return $stmt->fetch() !== false;
}

// One representative check per migration file. For multi-column/multi-table
// migrations this checks a single column/table as a proxy for "did this
// file ever get run" — good enough for visibility, not a guarantee every
// statement in the file completed (most use "IF NOT EXISTS", so a re-run
// is safe if you need to confirm further by hand).
$migrations = [
    // full_name is expected MISSING wherever 002_split_user_names.sql has
    // already run — it replaced full_name with first_name/last_name. This
    // row exists so a *never-ran-either-migration* environment still shows
    // up as missing something, not so a normal post-002 environment should
    // alarm on it.
    'alter_users_table.sql' => ['type' => 'column', 'table' => 'users', 'name' => 'full_name'],
    '001_add_user_settings_and_personalization.sql' => ['type' => 'column', 'table' => 'users', 'name' => 'onboarding_completed'],
    '002_split_user_names.sql' => ['type' => 'column', 'table' => 'users', 'name' => 'first_name'],
    '003_add_chat_rate_limits.sql' => ['type' => 'table', 'table' => 'chat_rate_limits'],
    '003_add_comprehensive_learning_profile.php' => ['type' => 'column', 'table' => 'users', 'name' => 'subjects_of_interest'],
    '004_add_feedback_table.php' => ['type' => 'table', 'table' => 'feedback'],
    '004_add_topic_to_knowledge_base.sql' => ['type' => 'column', 'table' => 'knowledge_base', 'name' => 'topic'],
    '005_add_legibility_column.php' => ['type' => 'column', 'table' => 'users', 'name' => 'legibility'],
    '005_add_pomodoro_recall_tables.sql' => ['type' => 'multi_table', 'tables' => ['pomodoro_sessions', 'recall_quizzes']],
    '006_add_knowledge_base.php' => ['type' => 'table', 'table' => 'knowledge_base'],
    '007_add_login_attempts_table.php' => ['type' => 'table', 'table' => 'login_attempts'],
    '008_add_performance_indexes.php' => ['type' => 'multi_index', 'indexes' => [
        ['table' => 'conversations', 'name' => 'idx_conv_user_updated'],
        ['table' => 'messages', 'name' => 'idx_msg_conv_created'],
        ['table' => 'login_attempts', 'name' => 'idx_attempts_ip_time'],
        ['table' => 'login_attempts', 'name' => 'idx_attempts_user_time'],
        ['table' => 'user_tokens', 'name' => 'idx_tokens_selector'],
        ['table' => 'user_tokens', 'name' => 'idx_tokens_expires'],
        ['table' => 'feedback', 'name' => 'idx_feedback_user_created'],
    ]],
    '009_add_message_cache.php' => ['type' => 'column', 'table' => 'messages', 'name' => 'content_html'],
    '010_add_knowledge_level.php' => ['type' => 'column', 'table' => 'users', 'name' => 'knowledge_level'],
    '011_add_interests.php' => ['type' => 'column', 'table' => 'users', 'name' => 'interests'],
    '012_add_last_reminder_sent_at.php' => ['type' => 'column', 'table' => 'users', 'name' => 'last_reminder_sent_at'],
    '013_add_push_subscriptions.php' => ['type' => 'table', 'table' => 'push_subscriptions'],
    '014_add_widget_library.sql' => ['type' => 'table', 'table' => 'widget_library'],
    'add_message_edit.php' => ['type' => 'column', 'table' => 'messages', 'name' => 'is_edited'],
    'add_profile_column.php' => ['type' => 'column', 'table' => 'users', 'name' => 'profile_data'],
    'add_session_context.php' => ['type' => 'column', 'table' => 'conversations', 'name' => 'session_goal'],
];

try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    fwrite(STDERR, "Could not connect to the database: " . $e->getMessage() . "\n");
    exit(1);
}

$dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
echo "Migration status against database: {$dbName}\n";
echo str_repeat('-', 70) . "\n";

$missingCount = 0;
$partialCount = 0;

foreach ($migrations as $file => $check) {
    $path = __DIR__ . '/' . $file;
    $existsOnDisk = file_exists($path);

    switch ($check['type']) {
        case 'table':
            $applied = tableExists($pdo, $check['table']);
            $detail = "table `{$check['table']}`";
            break;
        case 'column':
            $applied = columnExists($pdo, $check['table'], $check['name']);
            $detail = "`{$check['table']}`.`{$check['name']}`";
            break;
        case 'multi_table':
            $results = array_map(fn($t) => tableExists($pdo, $t), $check['tables']);
            $applied = !in_array(false, $results, true);
            $partial = !$applied && in_array(true, $results, true);
            $detail = implode(', ', array_map(fn($t, $ok) => $t . ($ok ? '' : ' [missing]'), $check['tables'], $results));
            break;
        case 'multi_index':
            $results = array_map(fn($i) => indexExists($pdo, $i['table'], $i['name']), $check['indexes']);
            $applied = !in_array(false, $results, true);
            $partial = !$applied && in_array(true, $results, true);
            $found = array_sum($results);
            $detail = "{$found}/" . count($check['indexes']) . " indexes present";
            break;
        default:
            $applied = false;
            $detail = 'unknown check type';
    }

    if (!isset($partial)) $partial = false;

    if ($applied) {
        $status = 'APPLIED ';
    } elseif ($partial) {
        $status = 'PARTIAL ';
        $partialCount++;
    } else {
        $status = 'MISSING ';
        $missingCount++;
    }

    $diskFlag = $existsOnDisk ? '' : '  (file not found on disk!)';
    printf("%-8s %-48s %s%s\n", $status, $file, $detail, $diskFlag);

    $partial = false; // reset for next iteration
}

echo str_repeat('-', 70) . "\n";
echo "{$missingCount} missing, {$partialCount} partial, " .
     (count($migrations) - $missingCount - $partialCount) . " applied, " .
     count($migrations) . " total.\n";

if ($missingCount > 0 || $partialCount > 0) {
    echo "\nRun the corresponding file(s) with:\n";
    echo "  mysql -u <user> -p <dbname> < migrations/<file>.sql       (for .sql files)\n";
    echo "  php migrations/<file>.php                                 (for .php files)\n";
    exit(1);
}

exit(0);
