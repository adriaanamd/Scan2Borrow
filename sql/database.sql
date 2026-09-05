-- ============================================================================
-- Scan2Borrow: An Automated Library Borrowing and Return System
-- Database schema + seed data
--
-- Import via phpMyAdmin, or:  mysql -u root -p < database.sql
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `scan2borrow_2.0`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `scan2borrow_2.0`;

-- ---- Users (borrowers + staff) ---------------------------------------------
DROP TABLE IF EXISTS `audit_events`;
DROP TABLE IF EXISTS `profile_change_requests`;
DROP TABLE IF EXISTS `borrowing_items`;
DROP TABLE IF EXISTS `borrowing_transactions`;
DROP TABLE IF EXISTS `borrowing`;
DROP TABLE IF EXISTS `book_copies`;
DROP TABLE IF EXISTS `book_titles`;
DROP TABLE IF EXISTS `books`;
DROP TABLE IF EXISTS `users`;

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
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
) ENGINE=InnoDB;

-- ---- Bulk borrowing catalog and transaction model ------------------------
-- `books` and `borrowing` remain in this base schema for upgrade compatibility.
-- Run sql/upgrade_bulk_borrowing.sql after importing this file to backfill the
-- normalized tables and activate the bulk-borrowing application model.
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
    KEY `idx_book_titles_isbn` (`isbn`)
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
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_copy_title` FOREIGN KEY (`title_id`) REFERENCES `book_titles`(`id`) ON DELETE CASCADE,
    KEY `idx_copies_title_status` (`title_id`, `status`, `deleted_at`)
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
    KEY `idx_transactions_approval` (`approval_status`, `requested_at`)
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
