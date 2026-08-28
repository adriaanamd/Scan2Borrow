-- ============================================================================
-- Scan2Borrow: Notification System Upgrade
-- Adds SMS logs, OTP codes, and return notifications
-- ============================================================================
USE `scan2borrow_2.0`;

-- SMS Logs table: tracks all SMS sent to prevent duplicates
CREATE TABLE IF NOT EXISTS `sms_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL COMMENT 'Student who received the SMS',
    `borrowing_id` INT DEFAULT NULL COMMENT 'Related borrowing transaction',
    `type` ENUM('borrow_confirmation','due_date_reminder','otp_verification') NOT NULL,
    `phone_number` VARCHAR(30) NOT NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
    `sent_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_sms_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sms_borrowing` FOREIGN KEY (`borrowing_id`) REFERENCES `borrowing`(`id`) ON DELETE CASCADE,
    INDEX `idx_borrowing_type` (`borrowing_id`, `type`),
    INDEX `idx_user_created` (`user_id`, `created_at`)
) ENGINE=InnoDB;

-- OTP Codes table: for student registration verification
CREATE TABLE IF NOT EXISTS `otp_codes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL COMMENT 'Temporary user ID during registration',
    `barcode` VARCHAR(50) DEFAULT NULL COMMENT 'Temporary barcode during registration',
    `otp_code` VARCHAR(6) NOT NULL,
    `phone_number` VARCHAR(30) NOT NULL,
    `user_data` JSON NOT NULL COMMENT 'Stores registration data temporarily',
    `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `is_used` TINYINT(1) NOT NULL DEFAULT 0,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_otp_code` (`otp_code`, `is_verified`, `is_used`),
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB;

-- Return Notifications table: tracks return confirmations for admin dashboard
CREATE TABLE IF NOT EXISTS `return_notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `borrowing_id` INT NOT NULL,
    `user_id` INT NOT NULL COMMENT 'Student who returned',
    `book_id` INT NOT NULL,
    `message` TEXT NOT NULL,
    `is_viewed` TINYINT(1) NOT NULL DEFAULT 0,
    `viewed_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_return_notif_borrowing` FOREIGN KEY (`borrowing_id`) REFERENCES `borrowing`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_return_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_return_notif_book` FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE,
    INDEX `idx_viewed` (`is_viewed`, `created_at DESC`)
) ENGINE=InnoDB;

-- Add borrowing_status column to users if not exists (for enabling/disabling borrowing)
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `borrowing_status` ENUM('active','inactive') NOT NULL DEFAULT 'active' 
    COMMENT 'Controls whether user can borrow books'
    AFTER `status`;

-- Add index for faster borrowing status checks
CREATE INDEX IF NOT EXISTS `idx_user_borrowing_status` ON `users`(`borrowing_status`);