-- Migration: Split full_name into first_name and last_name
-- Date: 2025-11-22
-- Description: This migration adds first_name and last_name columns and migrates data from full_name

-- Step 1: Add new columns
ALTER TABLE `users` 
ADD COLUMN `first_name` VARCHAR(100) DEFAULT NULL AFTER `username`,
ADD COLUMN `last_name` VARCHAR(100) DEFAULT NULL AFTER `first_name`;

-- Step 2: Migrate existing data
-- Split full_name into first_name and last_name
-- Assumes format: "FirstName LastName" or "FirstName MiddleName LastName"
UPDATE `users`
SET 
    `first_name` = TRIM(SUBSTRING_INDEX(`full_name`, ' ', 1)),
    `last_name` = TRIM(SUBSTRING_INDEX(`full_name`, ' ', -1))
WHERE `full_name` IS NOT NULL;

-- Step 3: For names with middle names, keep only first and last
-- If full_name has more than 2 words, take first word as first_name and last word as last_name
UPDATE `users`
SET 
    `last_name` = TRIM(SUBSTRING_INDEX(`full_name`, ' ', -1))
WHERE `full_name` LIKE '% % %';

-- Step 4 (Optional): Drop the old full_name column
-- Uncomment the line below if you want to remove the full_name column after migration
-- ALTER TABLE `users` DROP COLUMN `full_name`;

-- Verification query (run this to check the results)
-- SELECT id, username, first_name, last_name, full_name FROM users;
