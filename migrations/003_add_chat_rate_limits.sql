CREATE TABLE IF NOT EXISTS chat_rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    request_count INT NOT NULL DEFAULT 1,
    window_start INT NOT NULL,
    UNIQUE KEY unique_user (user_id),
    INDEX idx_window_start (window_start),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
