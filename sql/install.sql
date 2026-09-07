-- ============================================================================
-- Scan2Borrow: complete fresh-install schema and seed data
--
-- Import this file once for a fresh or disposable reset installation.
-- It recreates Scan2Borrow-owned tables and must not be used on a database
-- whose data needs to be preserved. Existing databases should use the
-- individual sql/upgrade_*.sql migrations instead.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `scan2borrow_2.0`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `scan2borrow_2.0`;

SET FOREIGN_KEY_CHECKS = 0;

-- Recreate only Scan2Borrow-owned tables. This file is for a fresh/reset install.
DROP TABLE IF EXISTS `audit_events`;
DROP TABLE IF EXISTS `renewal_requests`;
DROP TABLE IF EXISTS `reservations`;
DROP TABLE IF EXISTS `barcode_print_batch_items`;
DROP TABLE IF EXISTS `barcode_print_batches`;
DROP TABLE IF EXISTS `audit_log`;
DROP TABLE IF EXISTS `profile_change_requests`;
DROP TABLE IF EXISTS `return_notifications`;
DROP TABLE IF EXISTS `visitor_security_logs`;
DROP TABLE IF EXISTS `visitor_visit_history`;
DROP TABLE IF EXISTS `visitor_notifications`;
DROP TABLE IF EXISTS `visitor_borrowing`;
DROP TABLE IF EXISTS `visitors`;
DROP TABLE IF EXISTS `otp_codes`;
DROP TABLE IF EXISTS `sms_logs`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `book_title_keywords`;
DROP TABLE IF EXISTS `book_keywords`;
DROP TABLE IF EXISTS `book_views`;
DROP TABLE IF EXISTS `search_history`;
DROP TABLE IF EXISTS `keywords`;
DROP TABLE IF EXISTS `borrowing_items`;
DROP TABLE IF EXISTS `borrowing_transactions`;
DROP TABLE IF EXISTS `borrowing`;
DROP TABLE IF EXISTS `book_copies`;
DROP TABLE IF EXISTS `book_titles`;
DROP TABLE IF EXISTS `books`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- Final table definitions
-- ============================================================================

-- ---- Users (borrowers + staff) ---------------------------------------------

CREATE TABLE `users` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `barcode`       VARCHAR(50)  NOT NULL UNIQUE,
    `firstname`     VARCHAR(80)  NOT NULL,
    `middlename`    VARCHAR(80)  DEFAULT NULL,
    `lastname`      VARCHAR(80)  NOT NULL,
    `department`    VARCHAR(120) DEFAULT NULL,
    `position`      VARCHAR(120) DEFAULT NULL,
    `course`        VARCHAR(100) DEFAULT NULL,
    `year_level`    VARCHAR(20)  DEFAULT NULL,
    `email`         VARCHAR(120) DEFAULT NULL,
    `contact_no`    VARCHAR(30)  DEFAULT NULL,
    `role`          ENUM('admin','librarian','student','teacher') NOT NULL DEFAULT 'student',
    `password_hash` VARCHAR(255) DEFAULT NULL,   -- staff only; borrowers log in by barcode
    `photo`         MEDIUMTEXT   DEFAULT NULL,    -- ID photo stored as a base64 data URI
    `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `borrowing_status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `failed_attempts` INT NOT NULL DEFAULT 0,
    `locked_until` DATETIME DEFAULT NULL,
    `last_login` DATETIME DEFAULT NULL,
    `reset_token` VARCHAR(255) DEFAULT NULL,
    `reset_expires` DATETIME DEFAULT NULL,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_borrowing_status` (`borrowing_status`),
    KEY `idx_user_borrowing_status` (`borrowing_status`)
) ENGINE=InnoDB;

-- ---- Borrower profile change requests -------------------------------------
CREATE TABLE `profile_change_requests` (
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

-- ---- Books (one row per physical copy / barcode) ---------------------------
CREATE TABLE `books` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `barcode`      VARCHAR(50)  NOT NULL UNIQUE,
    `accession_no` VARCHAR(50)  DEFAULT NULL,
    `isbn`         VARCHAR(30)  DEFAULT NULL,
    `title`        VARCHAR(200) NOT NULL,
    `author`       VARCHAR(150) DEFAULT NULL,
    `publisher`    VARCHAR(150) DEFAULT NULL,
    `description`  TEXT DEFAULT NULL,
    `cover_file`   VARCHAR(255) DEFAULT NULL,
    `cover_image`  VARCHAR(255) DEFAULT NULL,
    `category_name` VARCHAR(100) DEFAULT NULL,
    `floor_no`     VARCHAR(20)  DEFAULT NULL,
    `section_name` VARCHAR(80)  DEFAULT NULL,
    `shelf_no`     VARCHAR(20)  DEFAULT NULL,
    `row_no`       VARCHAR(20)  DEFAULT NULL,
    `due_date`     DATE         DEFAULT NULL,
    `return_date`  DATE         DEFAULT NULL,
    `status`       ENUM('Available','Borrowed','Reserved','Lost','Damaged') NOT NULL DEFAULT 'Available',
    `deleted_at`   DATETIME DEFAULT NULL,        -- soft delete (archived) timestamp
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---- Borrowing transactions ------------------------------------------------
CREATE TABLE `borrowing` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `transaction_code` VARCHAR(40) NOT NULL UNIQUE,
    `user_id`          INT NOT NULL,
    `book_id`          INT NOT NULL,
    `processed_by`     INT DEFAULT NULL,          -- librarian who handled it
    `approval_status`  ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
    `borrow_date`      DATETIME NOT NULL,
    `due_date`         DATE NOT NULL,
    `return_date`      DATETIME DEFAULT NULL,
    `return_status`    ENUM('none','pending','rejected') NOT NULL DEFAULT 'none',
    `return_requested_at` DATETIME DEFAULT NULL,
    `return_decided_at` DATETIME DEFAULT NULL,
    `return_decided_by` INT DEFAULT NULL,
    `return_decision_note` VARCHAR(500) DEFAULT NULL,
    `status`           ENUM('Pending','Borrowed','Returned','Overdue') NOT NULL DEFAULT 'Borrowed',
    `fine_amount`      DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `requested_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `approved_at`      TIMESTAMP NULL DEFAULT NULL,
    `approved_by`      INT DEFAULT NULL,
    `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_borrow_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_borrow_book` FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_borrow_staff` FOREIGN KEY (`processed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ,CONSTRAINT `fk_borrow_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ,CONSTRAINT `fk_borrow_return_decided_by` FOREIGN KEY (`return_decided_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ,KEY `idx_borrow_return_status` (`return_status`, `return_requested_at`)
    ,KEY `idx_borrowing_approval` (`approval_status`, `requested_at`)
) ENGINE=InnoDB;

-- ---- Bulk borrowing catalog and transaction model ------------------------
-- `books` and `borrowing` remain for legacy compatibility. The normalized
-- tables and their seed backfills are included in this installer below.
CREATE TABLE `book_titles` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `isbn`        VARCHAR(30) DEFAULT NULL,
    `title`       VARCHAR(200) NOT NULL,
    `author`      VARCHAR(150) DEFAULT NULL,
    `publisher`   VARCHAR(150) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `cover_file`  VARCHAR(255) DEFAULT NULL,
    `category_name` VARCHAR(100) DEFAULT NULL,
    `quantity`    INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_book_titles_title` (`title`),
    KEY `idx_book_titles_isbn` (`isbn`),
    FULLTEXT KEY `ft_book_titles_title` (`title`),
    FULLTEXT KEY `ft_book_titles_category` (`category_name`),
    FULLTEXT KEY `ft_book_titles_author` (`author`),
    FULLTEXT KEY `ft_book_titles_publisher_description` (`publisher`, `description`)
) ENGINE=InnoDB;

CREATE TABLE `book_copies` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `title_id`     INT NOT NULL,
    `barcode`      VARCHAR(50) NOT NULL UNIQUE,
    `accession_no` VARCHAR(50) DEFAULT NULL,
    `floor_no`     VARCHAR(20) DEFAULT NULL,
    `section_name` VARCHAR(80) DEFAULT NULL,
    `shelf_no`     VARCHAR(20) DEFAULT NULL,
    `row_no`       VARCHAR(20) DEFAULT NULL,
    `due_date`     DATE DEFAULT NULL,
    `return_date`  DATE DEFAULT NULL,
    `status`       ENUM('Available','Borrowed','Reserved','Lost','Damaged') NOT NULL DEFAULT 'Available',
    `deleted_at`   DATETIME DEFAULT NULL,
    `printed_at`    DATETIME DEFAULT NULL,
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_copy_title` FOREIGN KEY (`title_id`) REFERENCES `book_titles`(`id`) ON DELETE CASCADE,
    KEY `idx_copies_title_status` (`title_id`, `status`, `deleted_at`),
    KEY `idx_copies_title_printed` (`title_id`, `printed_at`, `deleted_at`),
    KEY `idx_copies_status_deleted_title` (`status`, `deleted_at`, `title_id`)
) ENGINE=InnoDB;

CREATE TABLE `borrowing_transactions` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `transaction_code` VARCHAR(40) NOT NULL UNIQUE,
    `user_id`          INT NOT NULL,
    `processed_by`     INT DEFAULT NULL,
    `approval_status`  ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
    `borrow_date`      DATETIME NOT NULL,
    `due_date`         DATE NOT NULL,
    `return_date`      DATETIME DEFAULT NULL,
    `return_status`    ENUM('none','pending','rejected') NOT NULL DEFAULT 'none',
    `return_requested_at` DATETIME DEFAULT NULL,
    `return_decided_at` DATETIME DEFAULT NULL,
    `return_decided_by` INT DEFAULT NULL,
    `return_decision_note` VARCHAR(500) DEFAULT NULL,
    `status`           ENUM('Pending','Borrowed','Returned','Overdue') NOT NULL DEFAULT 'Borrowed',
    `fine_amount`      DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `requested_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `approved_at`      TIMESTAMP NULL DEFAULT NULL,
    `approved_by`      INT DEFAULT NULL,
    `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_transaction_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_transaction_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_transaction_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_transaction_return_decided_by` FOREIGN KEY (`return_decided_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    KEY `idx_transactions_user_status` (`user_id`, `status`, `return_date`),
    KEY `idx_transactions_approval` (`approval_status`, `requested_at`),
    KEY `idx_transactions_return_status` (`return_status`, `return_requested_at`),
    KEY `idx_transactions_user_return_id` (`user_id`, `return_date`, `id`)
) ENGINE=InnoDB;

CREATE TABLE `borrowing_items` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `transaction_id` INT NOT NULL,
    `copy_id`        INT NOT NULL,
    `return_date`    DATETIME DEFAULT NULL,
    `return_status`  ENUM('none','pending','rejected') NOT NULL DEFAULT 'none',
    `return_requested_at` DATETIME DEFAULT NULL,
    `return_decided_at` DATETIME DEFAULT NULL,
    `return_decided_by` INT DEFAULT NULL,
    `return_decision_note` VARCHAR(500) DEFAULT NULL,
    `status`         ENUM('Pending','Borrowed','Returned','Overdue') NOT NULL DEFAULT 'Borrowed',
    `fine_amount`    DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_item_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `borrowing_transactions`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_item_copy` FOREIGN KEY (`copy_id`) REFERENCES `book_copies`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_item_return_decided_by` FOREIGN KEY (`return_decided_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `uq_transaction_copy` (`transaction_id`, `copy_id`),
    KEY `idx_items_copy_active` (`copy_id`, `return_date`)
) ENGINE=InnoDB;

CREATE TABLE `keywords` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(100) NOT NULL UNIQUE,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE `book_keywords` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `book_id`    INT NOT NULL,
    `keyword_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_book_keyword` (`book_id`, `keyword_id`),
    CONSTRAINT `fk_bk_book` FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bk_keyword` FOREIGN KEY (`keyword_id`) REFERENCES `keywords`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `book_title_keywords` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `title_id`   INT NOT NULL,
    `keyword_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_book_title_keyword` (`title_id`, `keyword_id`),
    KEY `idx_book_title_keywords_keyword_title` (`keyword_id`, `title_id`),
    CONSTRAINT `fk_book_title_keywords_title` FOREIGN KEY (`title_id`) REFERENCES `book_titles`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_book_title_keywords_keyword` FOREIGN KEY (`keyword_id`) REFERENCES `keywords`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE `search_history` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT NOT NULL,
    `search_query` VARCHAR(255) NOT NULL,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_sh_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_created` (`user_id`, `created_at`),
    KEY `idx_search_history_user_created` (`user_id`, `created_at`, `id`)
) ENGINE=InnoDB;

CREATE TABLE `book_views` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT NOT NULL,
    `book_id`     INT NOT NULL,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_bv_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bv_book` FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_created` (`user_id`, `created_at`)
) ENGINE=InnoDB;

CREATE TABLE `visitors` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `visitor_number` VARCHAR(30) DEFAULT NULL,
    `qr_token` CHAR(32) DEFAULT NULL,
    `firstname` VARCHAR(100) NOT NULL,
    `middlename` VARCHAR(100) DEFAULT NULL,
    `lastname` VARCHAR(100) NOT NULL,
    `suffix` VARCHAR(20) DEFAULT NULL,
    `gender` VARCHAR(30) NOT NULL,
    `birthdate` DATE NOT NULL,
    `contact_no` VARCHAR(30) NOT NULL,
    `email` VARCHAR(150) DEFAULT NULL,
    `house_no` VARCHAR(100) NOT NULL,
    `street` VARCHAR(150) NOT NULL,
    `barangay` VARCHAR(150) NOT NULL,
    `municipality` VARCHAR(150) NOT NULL,
    `province` VARCHAR(150) NOT NULL,
    `purpose` VARCHAR(30) NOT NULL,
    `purpose_other` VARCHAR(255) DEFAULT NULL,
    `id_type` VARCHAR(100) NOT NULL,
    `id_barcode` VARCHAR(255) NOT NULL,
    `photo` MEDIUMTEXT DEFAULT NULL,
    `is_verified` TINYINT(1) NOT NULL DEFAULT 1,
    `verified_at` DATETIME NOT NULL,
    `registration_expires_at` DATE DEFAULT NULL,
    `account_status` ENUM('Active','Borrowing','Suspended','Expired') NOT NULL DEFAULT 'Active',
    `last_login_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_visitor_id_barcode` (`id_barcode`),
    UNIQUE KEY `uq_visitor_number` (`visitor_number`),
    UNIQUE KEY `uq_visitor_qr_token` (`qr_token`)
) ENGINE=InnoDB;

CREATE TABLE `visitor_borrowing` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `visitor_id` INT NOT NULL,
    `book_id` INT NOT NULL,
    `borrow_date` DATE NOT NULL,
    `due_date` DATE NOT NULL,
    `return_date` DATE DEFAULT NULL,
    `request_status` VARCHAR(30) NOT NULL DEFAULT 'Ready for Release',
    `verification_photo` MEDIUMTEXT DEFAULT NULL,
    `return_verification_photo` MEDIUMTEXT DEFAULT NULL,
    `requested_at` DATETIME DEFAULT NULL,
    `released_at` DATETIME DEFAULT NULL,
    `return_requested_at` DATETIME DEFAULT NULL,
    `return_decided_at` DATETIME DEFAULT NULL,
    `return_decided_by` INT DEFAULT NULL,
    `return_decision_note` VARCHAR(500) DEFAULT NULL,
    `review_notes` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_visitor_borrowing_visitor` FOREIGN KEY (`visitor_id`) REFERENCES `visitors`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_visitor_borrowing_book` FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE,
    INDEX `idx_visitor_active` (`visitor_id`, `return_date`)
) ENGINE=InnoDB;

CREATE TABLE `visitor_notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `visitor_id` INT NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_visitor_notification_visitor` FOREIGN KEY (`visitor_id`) REFERENCES `visitors`(`id`) ON DELETE CASCADE,
    INDEX `idx_visitor_notification` (`visitor_id`, `is_read`, `created_at`)
) ENGINE=InnoDB;

CREATE TABLE `visitor_visit_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `visitor_id` INT NOT NULL,
    `time_in` DATETIME NOT NULL,
    `time_out` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_visit_history_visitor` FOREIGN KEY (`visitor_id`) REFERENCES `visitors`(`id`) ON DELETE CASCADE,
    INDEX `idx_visit_history_visitor` (`visitor_id`, `time_in`)
) ENGINE=InnoDB;

CREATE TABLE `visitor_security_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `visitor_id` INT NOT NULL,
    `activity` VARCHAR(100) NOT NULL,
    `details` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_visitor_security_visitor` FOREIGN KEY (`visitor_id`) REFERENCES `visitors`(`id`) ON DELETE CASCADE,
    INDEX `idx_visitor_security` (`visitor_id`, `created_at`)
) ENGINE=InnoDB;

CREATE TABLE `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL COMMENT 'Staff member who receives the notification',
    `type` ENUM('borrow_request','overdue_alert','return_alert','hold_available','renewal_approved','renewal_rejected') NOT NULL DEFAULT 'borrow_request',
    `title` VARCHAR(200) NOT NULL,
    `message` TEXT NOT NULL,
    `related_id` INT DEFAULT NULL COMMENT 'ID of related borrowing request',
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    KEY `idx_notif_user_unread` (`user_id`, `is_read`, `created_at`)
) ENGINE=InnoDB;

CREATE TABLE `sms_logs` (
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

CREATE TABLE `otp_codes` (
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

CREATE TABLE `return_notifications` (
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
    INDEX `idx_viewed` (`is_viewed`, `created_at`)
) ENGINE=InnoDB;

CREATE TABLE `audit_log` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT DEFAULT NULL,
    `action`      VARCHAR(100) NOT NULL,
    `details`     TEXT DEFAULT NULL,
    `ip_address`  VARCHAR(45) DEFAULT NULL,
    `user_agent`  VARCHAR(255) DEFAULT NULL,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_action` (`user_id`, `action`, `created_at`)
) ENGINE=InnoDB;

CREATE TABLE `barcode_print_batches` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `batch_token` CHAR(32) NOT NULL,
    `title_id`    INT NOT NULL,
    `printed_by`  INT NOT NULL,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_barcode_batch_title` FOREIGN KEY (`title_id`) REFERENCES `book_titles`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_barcode_batch_staff` FOREIGN KEY (`printed_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uq_barcode_print_batch_token` (`batch_token`),
    KEY `idx_barcode_print_batches_title` (`title_id`, `created_at`)
) ENGINE=InnoDB;

CREATE TABLE `barcode_print_batch_items` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `batch_id`       INT NOT NULL,
    `copy_id`        INT NOT NULL,
    `title`          VARCHAR(200) NOT NULL,
    `author`         VARCHAR(150) DEFAULT NULL,
    `barcode`        VARCHAR(50) NOT NULL,
    `accession_no`   VARCHAR(50) DEFAULT NULL,
    `floor_no`       VARCHAR(20) DEFAULT NULL,
    `section_name`   VARCHAR(80) DEFAULT NULL,
    `shelf_no`       VARCHAR(20) DEFAULT NULL,
    `row_no`         VARCHAR(20) DEFAULT NULL,
    CONSTRAINT `fk_barcode_batch_item_batch` FOREIGN KEY (`batch_id`) REFERENCES `barcode_print_batches`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_barcode_batch_item_copy` FOREIGN KEY (`copy_id`) REFERENCES `book_copies`(`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uq_barcode_print_batch_copy` (`batch_id`, `copy_id`),
    KEY `idx_barcode_print_batch_items_copy` (`copy_id`)
) ENGINE=InnoDB;

CREATE TABLE `reservations` (
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

CREATE TABLE `renewal_requests` (
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

CREATE TABLE `audit_events` (
    `id`                INT AUTO_INCREMENT PRIMARY KEY,
    `copy_id`           INT DEFAULT NULL,
    `actor_user_id`     INT DEFAULT NULL,
    `event_type`        ENUM('acquired','status_changed','loaned','returned','barcode_printed','archived','restored','deleted') NOT NULL,
    `from_status`       ENUM('Available','Borrowed','Reserved','Lost','Damaged') DEFAULT NULL,
    `to_status`         ENUM('Available','Borrowed','Reserved','Lost','Damaged') DEFAULT NULL,
    `reason`            VARCHAR(500) DEFAULT NULL,
    `transaction_id`    INT DEFAULT NULL,
    `borrowing_item_id` INT DEFAULT NULL,
    `print_batch_id`    INT DEFAULT NULL,
    `legacy_source`     VARCHAR(190) DEFAULT NULL,
    `metadata`          JSON NOT NULL,
    `occurred_at`       DATETIME NOT NULL,
    `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_audit_copy` FOREIGN KEY (`copy_id`) REFERENCES `book_copies`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_audit_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_audit_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `borrowing_transactions`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_audit_item` FOREIGN KEY (`borrowing_item_id`) REFERENCES `borrowing_items`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_audit_print_batch` FOREIGN KEY (`print_batch_id`) REFERENCES `barcode_print_batches`(`id`) ON DELETE SET NULL,
    UNIQUE KEY `uq_audit_legacy_source` (`legacy_source`),
    KEY `idx_audit_copy_occurred` (`copy_id`, `occurred_at`, `id`)
) ENGINE=InnoDB;

-- ============================================================================
-- Seed data
-- ============================================================================

-- Default librarian/admin account.
--   Login barcode: ADMIN001
--   Password:      admin123   (CHANGE THIS after first login)
INSERT INTO `users`
    (barcode, firstname, middlename, lastname, course, year_level, email, contact_no, role, password_hash, status)
VALUES
    ('ADMIN001', 'Library', '', 'Administrator', NULL, NULL, 'admin@scan2borrow.local', NULL, 'admin',
     '$2y$10$PO07qZD2aFvEM44Lm1A6zOaYyntI/8ZH2Wq7emzRfdq/7hN4D0xB.', 'active');

-- Sample students (log in by scanning their ID barcode).
INSERT INTO `users`
    (barcode, firstname, middlename, lastname, course, year_level, email, contact_no, role, status)
VALUES
    ('2024001', 'Juan',  'Cruz',   'Dela Cruz', 'BSIT', '3', 'juan@example.com',  '09170000001', 'student', 'active'),
    ('2024002', 'Maria', 'Santos', 'Reyes',     'BSIT', '2', 'maria@example.com', '09170000002', 'student', 'active'),
    ('2024003', 'Pedro', 'Lim',    'Garcia',    'BSCS', '4', 'pedro@example.com', '09170000003', 'student', 'active');

-- Sample books.
INSERT INTO `books`
    (barcode, isbn, title, author, category_name, floor_no, section_name, shelf_no, row_no, status)
VALUES
    ('BK-0001', '9780262033848', 'Introduction to Algorithms',          'Cormen et al.',      'Computer Science', '2', 'IT Section',      'A1', '1', 'Available'),
    ('BK-0002', '9780132350884', 'Clean Code',                          'Robert C. Martin',   'Computer Science', '2', 'IT Section',      'A1', '2', 'Available'),
    ('BK-0003', '9780596007126', 'Head First Design Patterns',          'Freeman & Robson',   'Computer Science', '2', 'IT Section',      'A2', '1', 'Available'),
    ('BK-0004', '9780743273565', 'The Great Gatsby',                    'F. Scott Fitzgerald','Literature',       '1', 'Fiction Section', 'B3', '2', 'Available'),
    ('BK-0005', '9780061120084', 'To Kill a Mockingbird',               'Harper Lee',         'Literature',       '1', 'Fiction Section', 'B3', '3', 'Available');

-- ============================================================================
-- Normalize the seeded legacy model into the active title/copy model
-- ============================================================================

-- Build one catalog title for each ISBN, or for each title/author/publisher
-- identity when the legacy row has no ISBN. Existing titles are reused so the
-- script can be safely resumed after an interrupted import.
INSERT INTO `book_titles`
    (`isbn`, `title`, `author`, `publisher`, `description`, `cover_file`, `category_name`, `quantity`)
SELECT
    NULLIF(TRIM(source_book.`isbn`), ''), MIN(source_book.`title`), MIN(source_book.`author`),
    MIN(source_book.`publisher`), MIN(source_book.`description`), MIN(source_book.`cover_file`),
    MIN(source_book.`category_name`), COUNT(*)
FROM `books` AS source_book
WHERE NULLIF(TRIM(source_book.`isbn`), '') IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `book_titles` AS existing_title
    WHERE NULLIF(TRIM(existing_title.`isbn`), '') COLLATE utf8mb4_unicode_ci
        = NULLIF(TRIM(source_book.`isbn`), '') COLLATE utf8mb4_unicode_ci
 )
GROUP BY
    NULLIF(TRIM(source_book.`isbn`), '');

INSERT INTO `book_titles`
    (`isbn`, `title`, `author`, `publisher`, `description`, `cover_file`, `category_name`, `quantity`)
SELECT
    NULL, source_book.`title`, source_book.`author`, source_book.`publisher`, MIN(source_book.`description`),
    MIN(source_book.`cover_file`), MIN(source_book.`category_name`), COUNT(*)
FROM `books` AS source_book
WHERE NULLIF(TRIM(source_book.`isbn`), '') IS NULL
  AND NOT EXISTS (
    SELECT 1 FROM `book_titles` AS existing_title
    WHERE NULLIF(TRIM(existing_title.`isbn`), '') IS NULL
      AND existing_title.`title` COLLATE utf8mb4_unicode_ci = source_book.`title` COLLATE utf8mb4_unicode_ci
      AND COALESCE(existing_title.`author`, '') COLLATE utf8mb4_unicode_ci = COALESCE(source_book.`author`, '') COLLATE utf8mb4_unicode_ci
      AND COALESCE(existing_title.`publisher`, '') COLLATE utf8mb4_unicode_ci = COALESCE(source_book.`publisher`, '') COLLATE utf8mb4_unicode_ci
  )
GROUP BY source_book.`title`, source_book.`author`, source_book.`publisher`;

INSERT INTO `book_copies`
    (`title_id`, `barcode`, `accession_no`, `floor_no`, `section_name`, `shelf_no`, `row_no`, `due_date`, `return_date`, `status`, `deleted_at`)
SELECT
    title_record.`id`, source_book.`barcode`, source_book.`accession_no`, source_book.`floor_no`,
    source_book.`section_name`, source_book.`shelf_no`, source_book.`row_no`, source_book.`due_date`,
    source_book.`return_date`, source_book.`status`, source_book.`deleted_at`
FROM `books` AS source_book
JOIN `book_titles` AS title_record
  ON ((NULLIF(TRIM(source_book.`isbn`), '') IS NOT NULL
       AND NULLIF(TRIM(title_record.`isbn`), '') COLLATE utf8mb4_unicode_ci = NULLIF(TRIM(source_book.`isbn`), '') COLLATE utf8mb4_unicode_ci)
   OR (NULLIF(TRIM(source_book.`isbn`), '') IS NULL
       AND NULLIF(TRIM(title_record.`isbn`), '') IS NULL
       AND title_record.`title` COLLATE utf8mb4_unicode_ci = source_book.`title` COLLATE utf8mb4_unicode_ci
       AND COALESCE(title_record.`author`, '') COLLATE utf8mb4_unicode_ci = COALESCE(source_book.`author`, '') COLLATE utf8mb4_unicode_ci
       AND COALESCE(title_record.`publisher`, '') COLLATE utf8mb4_unicode_ci = COALESCE(source_book.`publisher`, '') COLLATE utf8mb4_unicode_ci))
WHERE NOT EXISTS (
     SELECT 1 FROM `book_copies` AS existing_copy
     WHERE existing_copy.`barcode` COLLATE utf8mb4_unicode_ci = source_book.`barcode` COLLATE utf8mb4_unicode_ci
);

UPDATE `book_titles` AS title_record
SET title_record.`quantity` = (
    SELECT COUNT(*) FROM `book_copies` AS copy_record
    WHERE copy_record.`title_id` = title_record.`id`
);

INSERT INTO `borrowing_transactions`
    (`transaction_code`, `user_id`, `processed_by`, `approval_status`, `borrow_date`, `due_date`, `return_date`, `status`, `fine_amount`, `requested_at`, `approved_at`, `approved_by`)
SELECT legacy.`transaction_code`, legacy.`user_id`, MAX(legacy.`processed_by`),
       MAX(legacy.`approval_status`), MIN(legacy.`borrow_date`), MAX(legacy.`due_date`),
       CASE WHEN SUM(legacy.`return_date` IS NULL) > 0 THEN NULL ELSE MAX(legacy.`return_date`) END,
       CASE WHEN SUM(legacy.`return_date` IS NULL) > 0 THEN MAX(legacy.`status`) ELSE 'Returned' END,
       SUM(legacy.`fine_amount`), MIN(legacy.`requested_at`), MAX(legacy.`approved_at`), MAX(legacy.`approved_by`)
FROM `borrowing` AS legacy
WHERE NOT EXISTS (
    SELECT 1 FROM `borrowing_transactions` AS existing_transaction
    WHERE existing_transaction.`transaction_code` COLLATE utf8mb4_unicode_ci
        = legacy.`transaction_code` COLLATE utf8mb4_unicode_ci
)
GROUP BY legacy.`transaction_code`, legacy.`user_id`;

INSERT INTO `borrowing_items` (`transaction_id`, `copy_id`, `return_date`, `status`, `fine_amount`)
SELECT transaction_record.`id`, copy_record.`id`, legacy.`return_date`, legacy.`status`, legacy.`fine_amount`
FROM `borrowing` AS legacy
JOIN `borrowing_transactions` AS transaction_record
  ON transaction_record.`transaction_code` COLLATE utf8mb4_unicode_ci
      = legacy.`transaction_code` COLLATE utf8mb4_unicode_ci
JOIN `books` AS legacy_book ON legacy_book.`id` = legacy.`book_id`
JOIN `book_copies` AS copy_record
  ON copy_record.`barcode` COLLATE utf8mb4_unicode_ci = legacy_book.`barcode` COLLATE utf8mb4_unicode_ci
WHERE NOT EXISTS (
    SELECT 1 FROM `borrowing_items` AS existing_item
    WHERE existing_item.`transaction_id` = transaction_record.`id`
      AND existing_item.`copy_id` = copy_record.`id`
);

UPDATE `book_copies` AS copy_record
JOIN `borrowing_items` AS item_record ON item_record.`copy_id` = copy_record.`id`
JOIN `borrowing_transactions` AS transaction_record ON transaction_record.`id` = item_record.`transaction_id`
SET copy_record.`status` = CASE
    WHEN transaction_record.`approval_status` = 'pending' AND item_record.`return_date` IS NULL THEN 'Reserved'
    WHEN item_record.`return_date` IS NULL THEN 'Borrowed'
    ELSE 'Available'
END
WHERE item_record.`return_date` IS NULL OR copy_record.`status` <> 'Available';

-- Repair normalized approval item states represented by the final schema.
UPDATE `borrowing_items` AS item_record
JOIN `borrowing_transactions` AS transaction_record
  ON transaction_record.`id` = item_record.`transaction_id`
SET item_record.`status` = 'Borrowed'
WHERE transaction_record.`approval_status` = 'approved'
  AND item_record.`return_date` IS NULL
  AND item_record.`status` = 'Pending';

UPDATE `borrowing_items` AS item_record
JOIN `borrowing_transactions` AS transaction_record
  ON transaction_record.`id` = item_record.`transaction_id`
SET item_record.`status` = 'Returned',
    item_record.`return_date` = COALESCE(item_record.`return_date`, CURRENT_TIMESTAMP)
WHERE transaction_record.`approval_status` = 'rejected'
  AND item_record.`return_date` IS NULL;

-- Backfill immutable copy audit events for seeded or migrated records.
INSERT INTO `audit_events`
    (`copy_id`, `event_type`, `metadata`, `legacy_source`, `occurred_at`)
SELECT c.id, 'acquired', JSON_OBJECT(
    'barcode', c.barcode, 'accession_no', c.accession_no, 'title_id', c.title_id,
    'provenance', 'historical backfill'
), CONCAT('copy:', c.id, ':acquired'), COALESCE(c.created_at, CURRENT_TIMESTAMP)
FROM book_copies c
WHERE NOT EXISTS (
    SELECT 1 FROM audit_events a WHERE a.legacy_source = CONCAT('copy:', c.id, ':acquired')
);

INSERT INTO `audit_events`
    (`copy_id`, `actor_user_id`, `event_type`, `transaction_id`, `borrowing_item_id`, `metadata`, `legacy_source`, `occurred_at`)
SELECT c.id, COALESCE(t.processed_by, t.approved_by), 'loaned', t.id, i.id, JSON_OBJECT(
    'barcode', c.barcode, 'transaction_code', t.transaction_code, 'borrower_id', t.user_id,
    'provenance', 'historical backfill'
), CONCAT('borrowing_item:', i.id, ':loaned'), COALESCE(i.created_at, t.borrow_date, CURRENT_TIMESTAMP)
FROM borrowing_items i
JOIN borrowing_transactions t ON t.id = i.transaction_id
JOIN book_copies c ON c.id = i.copy_id
WHERE NOT EXISTS (
    SELECT 1 FROM audit_events a WHERE a.legacy_source = CONCAT('borrowing_item:', i.id, ':loaned')
);

INSERT INTO `audit_events`
    (`copy_id`, `actor_user_id`, `event_type`, `transaction_id`, `borrowing_item_id`, `metadata`, `legacy_source`, `occurred_at`)
SELECT c.id, COALESCE(t.processed_by, t.approved_by), 'returned', t.id, i.id, JSON_OBJECT(
    'barcode', c.barcode, 'transaction_code', t.transaction_code, 'borrower_id', t.user_id,
    'provenance', 'historical backfill'
), CONCAT('borrowing_item:', i.id, ':returned'), i.return_date
FROM borrowing_items i
JOIN borrowing_transactions t ON t.id = i.transaction_id
JOIN book_copies c ON c.id = i.copy_id
WHERE i.return_date IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM audit_events a WHERE a.legacy_source = CONCAT('borrowing_item:', i.id, ':returned')
  );

INSERT INTO `audit_events`
    (`copy_id`, `actor_user_id`, `event_type`, `print_batch_id`, `metadata`, `legacy_source`, `occurred_at`)
SELECT i.copy_id, b.printed_by, 'barcode_printed', b.id, JSON_OBJECT(
    'barcode', i.barcode, 'batch_token', b.batch_token, 'provenance', 'historical backfill'
), CONCAT('barcode_print_batch_item:', i.id), b.created_at
FROM barcode_print_batch_items i
JOIN barcode_print_batches b ON b.id = i.batch_id
WHERE NOT EXISTS (
    SELECT 1 FROM audit_events a WHERE a.legacy_source = CONCAT('barcode_print_batch_item:', i.id)
);

INSERT INTO `audit_events`
    (`copy_id`, `actor_user_id`, `event_type`, `transaction_id`, `metadata`, `legacy_source`, `occurred_at`)
SELECT c.id, b.processed_by, 'loaned', b.id, JSON_OBJECT(
    'barcode', c.barcode, 'transaction_code', b.transaction_code, 'borrower_id', b.user_id,
    'provenance', 'legacy borrowing backfill'
), CONCAT('legacy_borrowing:', b.id, ':loaned'), COALESCE(b.borrow_date, CURRENT_TIMESTAMP)
FROM borrowing b
JOIN books old_book ON old_book.id = b.book_id
JOIN book_copies c ON c.barcode = old_book.barcode
WHERE NOT EXISTS (
    SELECT 1 FROM audit_events a WHERE a.legacy_source = CONCAT('legacy_borrowing:', b.id, ':loaned')
);

INSERT INTO `audit_events`
    (`copy_id`, `actor_user_id`, `event_type`, `transaction_id`, `metadata`, `legacy_source`, `occurred_at`)
SELECT c.id, b.processed_by, 'returned', b.id, JSON_OBJECT(
    'barcode', c.barcode, 'transaction_code', b.transaction_code, 'borrower_id', b.user_id,
    'provenance', 'legacy borrowing backfill'
), CONCAT('legacy_borrowing:', b.id, ':returned'), b.return_date
FROM borrowing b
JOIN books old_book ON old_book.id = b.book_id
JOIN book_copies c ON c.barcode = old_book.barcode
WHERE b.return_date IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM audit_events a WHERE a.legacy_source = CONCAT('legacy_borrowing:', b.id, ':returned')
  );

SET FOREIGN_KEY_CHECKS = 1;
