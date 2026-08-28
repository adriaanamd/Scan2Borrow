-- ============================================================================
-- Scan2Borrow - Add borrowing control for users
-- Run this to add borrowing_status field to users table
-- ============================================================================
USE `scan2borrow_2.0`;

-- Add borrowing_status field to control if user can borrow books
ALTER TABLE `users`
    ADD COLUMN `borrowing_status` ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER `status`;

-- Add index for faster queries
ALTER TABLE `users`
    ADD INDEX `idx_borrowing_status` (`borrowing_status`);