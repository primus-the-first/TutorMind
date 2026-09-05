-- SQL statements to alter the 'users' table for the new settings system.

ALTER TABLE `users`
ADD COLUMN `full_name` VARCHAR(255) DEFAULT NULL,
ADD COLUMN `learning_level` ENUM('Remember', 'Understand', 'Apply', 'Analyze', 'Evaluate', 'Create') DEFAULT 'Understand',
ADD COLUMN `response_style` ENUM('concise', 'detailed') DEFAULT 'concise',
ADD COLUMN `email_notifications` TINYINT(1) DEFAULT 1,
ADD COLUMN `study_reminders` TINYINT(1) DEFAULT 1,
ADD COLUMN `feature_announcements` TINYINT(1) DEFAULT 1,
ADD COLUMN `weekly_summary` TINYINT(1) DEFAULT 0,
ADD COLUMN `data_sharing` TINYINT(1) DEFAULT 1,
ADD COLUMN `dark_mode` TINYINT(1) DEFAULT 0,
ADD COLUMN `font_size` ENUM('small', 'medium', 'large') DEFAULT 'medium',
ADD COLUMN `chat_density` ENUM('compact', 'comfortable') DEFAULT 'comfortable',
ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Note: The 'created_at' field is assumed to exist. If not, add it:
-- ALTER TABLE `users` ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Note: The prompt mentioned 'full_name' as a field to edit, but it wasn't in the initial list.
-- I've added it here. If it already exists, this part of the query will fail, but the rest should succeed.
-- The prompt also mentioned 'updated_at', which is good practice to have.
