-- Scan2Borrow - borrower renewal requests and librarian decisions.
-- Run after upgrade_bulk_borrowing.sql.

USE `scan2borrow_2.0`;

CREATE TABLE IF NOT EXISTS `renewal_requests` (
    `id`                  INT AUTO_INCREMENT PRIMARY KEY,
    `loan_id`             INT NOT NULL,
    `user_id`             INT NOT NULL,
    `original_due_date`   DATE NOT NULL,
    `requested_due_date`  DATE NOT NULL,
    `status`              ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    `reason`              VARCHAR(500) DEFAULT NULL,
    `decision_note`       VARCHAR(500) DEFAULT NULL,
    `requested_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `decided_at`          DATETIME DEFAULT NULL,
    `approved_by`         INT DEFAULT NULL,
    `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_renewal_loan` FOREIGN KEY (`loan_id`) REFERENCES `borrowing_items`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_renewal_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_renewal_approver` FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `uq_renewal_approved_loan` (`loan_id`, `status`),
    KEY `idx_renewal_pending` (`status`, `requested_at`),
    KEY `idx_renewal_user` (`user_id`, `created_at`)
) ENGINE=InnoDB;
