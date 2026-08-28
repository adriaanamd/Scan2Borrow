-- ============================================================================
-- Scan2Borrow - Upgrade for the modern inventory console.
-- Run this ONLY if you already imported an older database.sql.
-- Fresh installs from database.sql already include this column.
-- ============================================================================
USE `scan2borrow_2.0`;

ALTER TABLE `books`
    ADD COLUMN `deleted_at` DATETIME DEFAULT NULL AFTER `status`;

-- Publisher field for books.
ALTER TABLE `books`
    ADD COLUMN `publisher` VARCHAR(150) DEFAULT NULL AFTER `author`;

-- Due date / return date fields for books.
ALTER TABLE `books`
    ADD COLUMN `due_date`    DATE DEFAULT NULL AFTER `row_no`,
    ADD COLUMN `return_date` DATE DEFAULT NULL AFTER `due_date`;

-- Users: ID photo stored directly in the DB as a base64 data URI.
-- (MariaDB / XAMPP supports IF NOT EXISTS / IF EXISTS here.)
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `photo` MEDIUMTEXT DEFAULT NULL AFTER `password_hash`;
-- If you previously added `photo` as VARCHAR(255), widen it so images fit:
ALTER TABLE `users`
    MODIFY COLUMN IF EXISTS `photo` MEDIUMTEXT DEFAULT NULL;

-- Borrowing: fine amount used by the overdue-monitoring routine.
ALTER TABLE `borrowing`
    ADD COLUMN `fine_amount` DECIMAL(8,2) NOT NULL DEFAULT 0.00 AFTER `status`;

-- Borrowing: transaction code (digital receipt) + librarian who processed it.
ALTER TABLE `borrowing`
    ADD COLUMN `transaction_code` VARCHAR(40) NULL AFTER `id`;
UPDATE `borrowing`
    SET `transaction_code` = CONCAT('TXN-', LPAD(`id`, 8, '0'))
    WHERE `transaction_code` IS NULL OR `transaction_code` = '';
ALTER TABLE `borrowing`
    ADD UNIQUE KEY `uq_txn_code` (`transaction_code`);
ALTER TABLE `borrowing`
    ADD COLUMN `processed_by` INT NULL AFTER `book_id`;

-- ============================================================================
-- Smart Book Recommendations: keywords, search history, book views
-- Keywords are book attributes (like "cooking", "pastry", "C++"), NOT borrowing data
-- ============================================================================

-- Keywords table: stores unique keywords/tags for books
CREATE TABLE IF NOT EXISTS `keywords` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(100) NOT NULL UNIQUE,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Book-Keyword relationship: many-to-many (books <-> keywords)
CREATE TABLE IF NOT EXISTS `book_keywords` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `book_id`    INT NOT NULL,
    `keyword_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_book_keyword` (`book_id`, `keyword_id`),
    CONSTRAINT `fk_bk_book` FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bk_keyword` FOREIGN KEY (`keyword_id`) REFERENCES `keywords`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Search history: tracks what users search for (for recommendations)
CREATE TABLE IF NOT EXISTS `search_history` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT NOT NULL,
    `search_query` VARCHAR(255) NOT NULL,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_sh_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_created` (`user_id`, `created_at`)
) ENGINE=InnoDB;

-- Book view history: tracks which books users view (for recommendations)
CREATE TABLE IF NOT EXISTS `book_views` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT NOT NULL,
    `book_id`     INT NOT NULL,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_bv_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bv_book` FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_created` (`user_id`, `created_at`)
) ENGINE=InnoDB;

-- Guest/visitor registrations are kept separate from borrower accounts.
CREATE TABLE IF NOT EXISTS `visitors` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
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
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_visitor_id_barcode` (`id_barcode`)
) ENGINE=InnoDB;

ALTER TABLE `visitors` ADD COLUMN IF NOT EXISTS `photo` MEDIUMTEXT DEFAULT NULL AFTER `id_barcode`;
ALTER TABLE `visitors`
    ADD COLUMN IF NOT EXISTS `visitor_number` VARCHAR(30) NULL AFTER `id`,
    ADD COLUMN IF NOT EXISTS `qr_token` CHAR(32) NULL AFTER `visitor_number`,
    ADD COLUMN IF NOT EXISTS `registration_expires_at` DATE NULL AFTER `verified_at`,
    ADD COLUMN IF NOT EXISTS `account_status` ENUM('Active','Borrowing','Suspended','Expired') NOT NULL DEFAULT 'Active' AFTER `registration_expires_at`,
    ADD COLUMN IF NOT EXISTS `last_login_at` DATETIME NULL AFTER `account_status`;
ALTER TABLE `visitors` ADD UNIQUE KEY IF NOT EXISTS `uq_visitor_number` (`visitor_number`);
ALTER TABLE `visitors` ADD UNIQUE KEY IF NOT EXISTS `uq_visitor_qr_token` (`qr_token`);
UPDATE `visitors`
SET `visitor_number` = CONCAT('VIS-', YEAR(`created_at`), '-', LPAD(`id`, 6, '0'))
WHERE `visitor_number` IS NULL OR `visitor_number` = '';
UPDATE `visitors`
SET `qr_token` = MD5(CONCAT('scan2borrow-visitor-', `id`, '-', `created_at`))
WHERE `qr_token` IS NULL OR `qr_token` = '';
UPDATE `visitors`
SET `registration_expires_at` = DATE_ADD(DATE(`created_at`), INTERVAL 1 YEAR)
WHERE `registration_expires_at` IS NULL;
CREATE TABLE IF NOT EXISTS `visitor_borrowing` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `visitor_id` INT NOT NULL,
    `book_id` INT NOT NULL,
    `borrow_date` DATE NOT NULL,
    `due_date` DATE NOT NULL,
    `return_date` DATE DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_visitor_borrowing_visitor` FOREIGN KEY (`visitor_id`) REFERENCES `visitors`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_visitor_borrowing_book` FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE,
    INDEX `idx_visitor_active` (`visitor_id`, `return_date`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `visitor_notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `visitor_id` INT NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_visitor_notification_visitor` FOREIGN KEY (`visitor_id`) REFERENCES `visitors`(`id`) ON DELETE CASCADE,
    INDEX `idx_visitor_notification` (`visitor_id`, `is_read`, `created_at`)
) ENGINE=InnoDB;

ALTER TABLE `visitor_borrowing`
    ADD COLUMN IF NOT EXISTS `request_status` VARCHAR(30) NOT NULL DEFAULT 'Ready for Release' AFTER `return_date`,
    ADD COLUMN IF NOT EXISTS `verification_photo` MEDIUMTEXT DEFAULT NULL AFTER `request_status`,
    ADD COLUMN IF NOT EXISTS `return_verification_photo` MEDIUMTEXT DEFAULT NULL AFTER `verification_photo`,
    ADD COLUMN IF NOT EXISTS `requested_at` DATETIME DEFAULT NULL AFTER `return_verification_photo`,
    ADD COLUMN IF NOT EXISTS `released_at` DATETIME DEFAULT NULL AFTER `requested_at`,
    ADD COLUMN IF NOT EXISTS `return_requested_at` DATETIME DEFAULT NULL AFTER `released_at`,
    ADD COLUMN IF NOT EXISTS `review_notes` VARCHAR(255) DEFAULT NULL AFTER `return_requested_at`;


CREATE TABLE IF NOT EXISTS `visitor_visit_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `visitor_id` INT NOT NULL,
    `time_in` DATETIME NOT NULL,
    `time_out` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_visit_history_visitor` FOREIGN KEY (`visitor_id`) REFERENCES `visitors`(`id`) ON DELETE CASCADE,
    INDEX `idx_visit_history_visitor` (`visitor_id`, `time_in`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `visitor_security_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `visitor_id` INT NOT NULL,
    `activity` VARCHAR(100) NOT NULL,
    `details` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_visitor_security_visitor` FOREIGN KEY (`visitor_id`) REFERENCES `visitors`(`id`) ON DELETE CASCADE,
    INDEX `idx_visitor_security` (`visitor_id`, `created_at`)
) ENGINE=InnoDB;
