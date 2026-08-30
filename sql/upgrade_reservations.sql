-- Scan2Borrow: title-level reservation queue and 24-hour hold offers.
-- Run after upgrade_bulk_borrowing.sql.

USE `scan2borrow_2.0`;

-- Circulation notifications are shared by holds and renewals. The existing
-- approval migration creates this enum with only legacy staff alert types.
ALTER TABLE `notifications`
    MODIFY `type` ENUM('borrow_request','overdue_alert','return_alert','hold_available','renewal_approved','renewal_rejected') NOT NULL;

CREATE TABLE IF NOT EXISTS `reservations` (
    `id`                  INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`             INT NOT NULL,
    `title_id`            INT NOT NULL,
    `queue_sequence`      BIGINT UNSIGNED NOT NULL,
    `status`              ENUM('queued','offered','claimed','fulfilled','expired','cancelled') NOT NULL DEFAULT 'queued',
    `offered_copy_id`     INT DEFAULT NULL,
    `offered_at`          DATETIME DEFAULT NULL,
    `hold_expires_at`     DATETIME DEFAULT NULL,
    `claimed_at`          DATETIME DEFAULT NULL,
    `fulfilled_at`        DATETIME DEFAULT NULL,
    `expired_at`          DATETIME DEFAULT NULL,
    `cancelled_at`        DATETIME DEFAULT NULL,
    `cancelled_by`        INT DEFAULT NULL,
    `active_user_title`   VARCHAR(80) GENERATED ALWAYS AS (
        CASE WHEN `status` IN ('queued','offered','claimed')
             THEN CONCAT(`user_id`, ':', `title_id`) ELSE NULL END
    ) STORED,
    `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_reservation_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reservation_title` FOREIGN KEY (`title_id`) REFERENCES `book_titles`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reservation_copy` FOREIGN KEY (`offered_copy_id`) REFERENCES `book_copies`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_reservation_cancelled_by` FOREIGN KEY (`cancelled_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `uq_reservation_active_user_title` (`active_user_title`),
    UNIQUE KEY `uq_reservation_queue_sequence` (`queue_sequence`),
    KEY `idx_reservations_title_queue` (`title_id`, `status`, `queue_sequence`),
    KEY `idx_reservations_expiry` (`status`, `hold_expires_at`)
) ENGINE=InnoDB;
