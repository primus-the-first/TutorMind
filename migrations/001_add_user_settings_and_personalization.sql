-- ============================================================================
-- TutorMind Database Migration: User Settings & Personalization
-- ============================================================================
-- This migration adds all user preference columns including:
-- 1. Learning preferences (from alter_users_table.sql)
-- 2. Notification settings
-- 3. UI preferences
-- 4. NEW: Personalization/onboarding fields
-- ============================================================================
-- Created: 2025-11-20
-- Description: Comprehensive update to users table for settings and onboarding
-- ============================================================================

-- Start transaction for safety
START TRANSACTION;

-- Add all columns to the users table
ALTER TABLE `users`
-- Learning Preferences (existing from alter_users_table.sql)
ADD COLUMN `learning_level` ENUM('Remember', 'Understand', 'Apply', 'Analyze', 'Evaluate', 'Create') DEFAULT 'Understand' COMMENT 'Bloom''s Taxonomy level for AI responses',
ADD COLUMN `response_style` ENUM('concise', 'detailed') DEFAULT 'concise' COMMENT 'Preferred AI response verbosity',

-- Notification Settings (existing from alter_users_table.sql)
ADD COLUMN `email_notifications` TINYINT(1) DEFAULT 1 COMMENT 'General email notifications',
ADD COLUMN `study_reminders` TINYINT(1) DEFAULT 1 COMMENT 'Study reminder notifications',
ADD COLUMN `feature_announcements` TINYINT(1) DEFAULT 1 COMMENT 'New feature announcements',
ADD COLUMN `weekly_summary` TINYINT(1) DEFAULT 0 COMMENT 'Weekly progress summary emails',

-- Privacy Settings (existing from alter_users_table.sql)
ADD COLUMN `data_sharing` TINYINT(1) DEFAULT 1 COMMENT 'Allow anonymous usage data collection',

-- UI/Display Preferences (existing from alter_users_table.sql)
ADD COLUMN `dark_mode` TINYINT(1) DEFAULT 0 COMMENT 'Dark mode preference',
ADD COLUMN `font_size` ENUM('small', 'medium', 'large') DEFAULT 'medium' COMMENT 'UI font size',
ADD COLUMN `chat_density` ENUM('compact', 'comfortable') DEFAULT 'comfortable' COMMENT 'Chat message spacing',

-- NEW: Personalization/Onboarding Fields
ADD COLUMN `country` VARCHAR(100) DEFAULT NULL COMMENT 'User''s country for context-aware examples',
ADD COLUMN `primary_language` VARCHAR(50) DEFAULT 'English' COMMENT 'User''s primary language',
ADD COLUMN `education_level` ENUM('Primary', 'Secondary', 'University', 'Graduate', 'Professional', 'Other') DEFAULT NULL COMMENT 'Current education level',
ADD COLUMN `field_of_study` VARCHAR(255) DEFAULT NULL COMMENT 'Academic field/programme (if applicable)',
ADD COLUMN `institution` VARCHAR(255) DEFAULT NULL COMMENT 'Educational institution (optional)',
ADD COLUMN `onboarding_completed` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether user has completed onboarding';

-- Commit the transaction
COMMIT;

-- ============================================================================
-- Verification Query (Optional - Run separately to verify changes)
-- ============================================================================
-- DESCRIBE users;

-- ============================================================================
-- Rollback Script (In case you need to undo this migration)
-- ============================================================================
-- START TRANSACTION;
-- ALTER TABLE `users`
--   DROP COLUMN `onboarding_completed`,
--   DROP COLUMN `institution`,
--   DROP COLUMN `field_of_study`,
--   DROP COLUMN `education_level`,
--   DROP COLUMN `primary_language`,
--   DROP COLUMN `country`,
--   DROP COLUMN `chat_density`,
--   DROP COLUMN `font_size`,
--   DROP COLUMN `dark_mode`,
--   DROP COLUMN `data_sharing`,
--   DROP COLUMN `weekly_summary`,
--   DROP COLUMN `feature_announcements`,
--   DROP COLUMN `study_reminders`,
--   DROP COLUMN `email_notifications`,
--   DROP COLUMN `response_style`,
--   DROP COLUMN `learning_level`;
-- COMMIT;
