-- Migration 005: Pomodoro sessions and Active Recall quizzes
-- Supports the Pomodoro Timer + Active Recall Testing feature

CREATE TABLE IF NOT EXISTS pomodoro_sessions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    conversation_id INT NULL,
    duration_minutes INT NOT NULL DEFAULT 25,
    mode            ENUM('gentle','standard','challenge') NOT NULL DEFAULT 'standard',
    completed       TINYINT(1) NOT NULL DEFAULT 0,
    messages_count  INT NOT NULL DEFAULT 0,
    started_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at        TIMESTAMP NULL,
    INDEX idx_user_id   (user_id),
    INDEX idx_started_at (started_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS recall_quizzes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    session_id      INT NULL,                          -- links to pomodoro_sessions.id (nullable)
    user_id         INT NOT NULL,
    conversation_id INT NULL,
    question_type   ENUM('recognition','cued','free_recall','application') NOT NULL DEFAULT 'cued',
    question        TEXT NOT NULL,
    options         JSON NULL,                         -- array of strings, recognition only
    correct_answer  TEXT NULL,
    key_points      JSON NULL,                         -- array of strings used for grading
    context_snippet TEXT NULL,                         -- brief summary of what was covered
    user_answer     TEXT NULL,
    ai_feedback     TEXT NULL,
    score           DECIMAL(3,2) NULL,                 -- 0.00 – 1.00
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    answered_at     TIMESTAMP NULL,
    INDEX idx_user_id        (user_id),
    INDEX idx_conversation_id (conversation_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
