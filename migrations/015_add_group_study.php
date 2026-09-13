<?php
/**
 * Migration 015: Group study sessions.
 *
 * Kept fully separate from `conversations`/`messages` on purpose — those
 * tables are wired single-owner (WHERE user_id = ?) in a dozen call sites
 * throughout includes/server_mysql.php, and overloading them with a
 * multi-student concept risks a private 1:1 conversation leaking group
 * content or vice versa. A group session is its own small schema instead:
 *
 *   group_sessions              — one row per study room (host, topic, join code, current teacher)
 *   group_session_participants  — who's in the room
 *   group_session_turns         — one row per teaching turn (who taught, when, how it ended)
 *   group_session_messages      — the actual transcript (student explanations + AI facilitation)
 */

require_once __DIR__ . '/../includes/db_mysql.php';

try {
    $pdo = getDbConnection();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS group_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            host_user_id INT NOT NULL,
            topic VARCHAR(255) NOT NULL,
            join_code CHAR(6) NOT NULL,
            status ENUM('waiting', 'active', 'completed') NOT NULL DEFAULT 'waiting',
            current_teacher_user_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_join_code (join_code),
            INDEX idx_host_user (host_user_id),
            FOREIGN KEY (host_user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (current_teacher_user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS group_session_participants (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            user_id INT NOT NULL,
            display_name VARCHAR(100) NOT NULL,
            has_taught TINYINT(1) NOT NULL DEFAULT 0,
            joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_session_user (session_id, user_id),
            INDEX idx_session (session_id),
            FOREIGN KEY (session_id) REFERENCES group_sessions(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS group_session_turns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            teacher_user_id INT NOT NULL,
            status ENUM('teaching', 'challenge_open', 'completed') NOT NULL DEFAULT 'teaching',
            started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ended_at TIMESTAMP NULL,
            INDEX idx_session (session_id),
            FOREIGN KEY (session_id) REFERENCES group_sessions(id) ON DELETE CASCADE,
            FOREIGN KEY (teacher_user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS group_session_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            turn_id INT NULL,
            sender_type ENUM('student', 'ai') NOT NULL,
            student_user_id INT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session_created (session_id, created_at),
            FOREIGN KEY (session_id) REFERENCES group_sessions(id) ON DELETE CASCADE,
            FOREIGN KEY (turn_id) REFERENCES group_session_turns(id) ON DELETE SET NULL,
            FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✅ Migration 015 complete: group_sessions, group_session_participants, group_session_turns, group_session_messages created.\n";
} catch (PDOException $e) {
    echo "❌ Migration 015 failed: " . $e->getMessage() . "\n";
    exit(1);
}
