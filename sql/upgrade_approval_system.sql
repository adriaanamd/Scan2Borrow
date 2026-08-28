-- ============================================================================
-- Scan2Borrow: Approval Request System for Book Borrowing
-- ============================================================================

-- Add approval-related columns to borrowing table
ALTER TABLE `borrowing`
    ADD COLUMN `approval_status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved' 
        COMMENT 'For approval workflow: pending=awaiting staff approval, approved=can borrow, rejected=denied'
    AFTER `processed_by`,
    ADD COLUMN `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP 
        COMMENT 'When the borrow request was submitted',
    ADD COLUMN `approved_at` TIMESTAMP NULL DEFAULT NULL 
        COMMENT 'When staff approved/rejected the request',
    ADD COLUMN `approved_by` INT NULL DEFAULT NULL 
        COMMENT 'Staff member who approved/rejected',
    ADD CONSTRAINT `fk_approval_staff` FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;

-- Create notifications table for staff alerts
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL COMMENT 'Staff member who receives the notification',
    `type` ENUM('borrow_request','overdue_alert','return_alert') NOT NULL DEFAULT 'borrow_request',
    `title` VARCHAR(200) NOT NULL,
    `message` TEXT NOT NULL,
    `related_id` INT DEFAULT NULL COMMENT 'ID of related borrowing request',
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Create index for faster notification queries
CREATE INDEX idx_notif_user_unread ON notifications(user_id, is_read, created_at DESC);
CREATE INDEX idx_borrowing_approval ON borrowing(approval_status, requested_at);

-- Update existing borrowing records to 'approved' status (backward compatibility)
UPDATE borrowing SET approval_status = 'approved' WHERE approval_status IS NULL OR approval_status = '';
