<?php
/**
 * Migration 020: Huddle rounds, reactions and focus sessions in group study.
 *
 * - group_rounds: a live question the whole room answers at once. Answers are
 *   hidden until everyone has locked in (or the host reveals, or it times
 *   out), then shown together: "2 of 5 got it". source = who started it
 *   (host / focus timer / Q). Its question is posted to the transcript as a Q
 *   message carrying round_id, which the room renders as the round's card.
 * - group_round_answers: one locked-in choice per person per round. Locked
 *   means locked: no changing it once it's in.
 * - group_message_reactions: Clicked / Wait what / Same / You got this on a
 *   message. Feedback on the message, never a score for the person.
 * - group_sessions.focus_*: a shared focus timer the host starts; when it
 *   runs out, one client claims it (focus_ends_at cleared atomically) and a
 *   recall round is generated from what the room covered.
 *
 * Run once per environment: php migrations/020_group_study_rounds.php
 */

require_once __DIR__ . '/../includes/db_mysql.php';

try {
    $pdo = getDbConnection();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS group_rounds (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            source ENUM('host', 'timer', 'q') NOT NULL DEFAULT 'host',
            started_by_user_id INT NULL,
            question TEXT NOT NULL,
            options JSON NOT NULL COMMENT 'Array of 2-4 answer strings',
            correct_index TINYINT NOT NULL,
            explanation TEXT NULL COMMENT 'One line on why, shown after the reveal',
            status ENUM('open', 'revealed') NOT NULL DEFAULT 'open',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            revealed_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_session_status (session_id, status),
            FOREIGN KEY (session_id) REFERENCES group_sessions(id) ON DELETE CASCADE,
            FOREIGN KEY (started_by_user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS group_round_answers (
            round_id INT NOT NULL,
            user_id INT NOT NULL,
            choice TINYINT NOT NULL,
            locked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (round_id, user_id),
            FOREIGN KEY (round_id) REFERENCES group_rounds(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS group_message_reactions (
            message_id INT NOT NULL,
            user_id INT NOT NULL,
            kind ENUM('clicked', 'wait', 'same', 'cheer') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (message_id, user_id, kind),
            FOREIGN KEY (message_id) REFERENCES group_session_messages(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        ALTER TABLE group_sessions
        ADD COLUMN IF NOT EXISTS focus_ends_at DATETIME NULL DEFAULT NULL COMMENT 'Shared focus timer; cleared when it fires or stops',
        ADD COLUMN IF NOT EXISTS focus_minutes SMALLINT NULL DEFAULT NULL
    ");

    $pdo->exec("
        ALTER TABLE group_session_messages
        ADD COLUMN IF NOT EXISTS round_id INT NULL DEFAULT NULL COMMENT 'Q message that carries a huddle round'
    ");
    $fk = $pdo->query("
        SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'group_session_messages'
          AND COLUMN_NAME = 'round_id' AND REFERENCED_TABLE_NAME = 'group_rounds'
    ")->fetchColumn();
    if ((int) $fk === 0) {
        $pdo->exec("
            ALTER TABLE group_session_messages
            ADD CONSTRAINT fk_gsm_round FOREIGN KEY (round_id) REFERENCES group_rounds(id) ON DELETE SET NULL
        ");
    }

    $missing = [];
    foreach (['group_rounds', 'group_round_answers', 'group_message_reactions'] as $t) {
        if (!$pdo->query("SHOW TABLES LIKE '{$t}'")->fetch()) $missing[] = $t;
    }
    foreach ([['group_sessions', 'focus_ends_at'], ['group_sessions', 'focus_minutes'], ['group_session_messages', 'round_id']] as [$table, $col]) {
        if (!$pdo->query("SHOW COLUMNS FROM {$table} LIKE '{$col}'")->fetch()) $missing[] = "{$table}.{$col}";
    }
    if ($missing) {
        echo "❌ Migration 020 failed: missing " . implode(', ', $missing) . "\n";
        exit(1);
    }
    echo "✅ Migration 020 complete: huddle rounds, answers, reactions, focus timer.\n";
} catch (PDOException $e) {
    echo "❌ Migration 020 failed: " . $e->getMessage() . "\n";
    exit(1);
}
