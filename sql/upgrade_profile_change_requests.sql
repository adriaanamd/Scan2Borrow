-- Scan2Borrow profile change approval workflow.
-- Run after the users table exists. Safe to run more than once.
USE `scan2borrow_2.0`;

CREATE TABLE IF NOT EXISTS `profile_change_requests` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`          INT NOT NULL,
    `status`           ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `original_values`  JSON NOT NULL,
    `requested_values` JSON NOT NULL,
    `original_photo`   VARCHAR(255) DEFAULT NULL,
    `requested_photo`  VARCHAR(255) DEFAULT NULL,
    `review_note`      VARCHAR(500) DEFAULT NULL,
    `requested_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `reviewed_at`      DATETIME DEFAULT NULL,
    `reviewed_by`      INT DEFAULT NULL,
    CONSTRAINT `fk_profile_change_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_profile_change_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    KEY `idx_profile_change_status_requested` (`status`, `requested_at`),
    KEY `idx_profile_change_user_status` (`user_id`, `status`)
) ENGINE=InnoDB;
