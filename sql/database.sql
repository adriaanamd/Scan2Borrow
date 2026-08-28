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
DROP TABLE IF EXISTS `borrowing`;
DROP TABLE IF EXISTS `books`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `barcode`       VARCHAR(50)  NOT NULL UNIQUE,
    `firstname`     VARCHAR(80)  NOT NULL,
    `middlename`    VARCHAR(80)  DEFAULT NULL,
    `lastname`      VARCHAR(80)  NOT NULL,
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

-- ---- Books (one row per physical copy / barcode) ---------------------------
CREATE TABLE `books` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `barcode`      VARCHAR(50)  NOT NULL UNIQUE,
    `isbn`         VARCHAR(30)  DEFAULT NULL,
    `title`        VARCHAR(200) NOT NULL,
    `author`       VARCHAR(150) DEFAULT NULL,
    `publisher`    VARCHAR(150) DEFAULT NULL,
    `description`  TEXT DEFAULT NULL,
    `cover_file`   VARCHAR(255) DEFAULT NULL,
    `cover_image`  VARCHAR(255) DEFAULT NULL,
    `category`     VARCHAR(100) DEFAULT NULL,
    `floor_no`     VARCHAR(20)  DEFAULT NULL,
    `section_name` VARCHAR(80)  DEFAULT NULL,
    `shelf_no`     VARCHAR(20)  DEFAULT NULL,
    `row_no`       VARCHAR(20)  DEFAULT NULL,
    `due_date`     DATE         DEFAULT NULL,
    `return_date`  DATE         DEFAULT NULL,
    `status`       ENUM('Available','Borrowed','Reserved') NOT NULL DEFAULT 'Available',
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
    `borrow_date`      DATETIME NOT NULL,
    `due_date`         DATE NOT NULL,
    `return_date`      DATETIME DEFAULT NULL,
    `status`           ENUM('Pending','Borrowed','Returned','Overdue') NOT NULL DEFAULT 'Borrowed',
    `fine_amount`      DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_borrow_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_borrow_book` FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_borrow_staff` FOREIGN KEY (`processed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
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
    (barcode, isbn, title, author, category, floor_no, section_name, shelf_no, row_no, status)
VALUES
    ('BK-0001', '9780262033848', 'Introduction to Algorithms',          'Cormen et al.',      'Computer Science', '2', 'IT Section',      'A1', '1', 'Available'),
    ('BK-0002', '9780132350884', 'Clean Code',                          'Robert C. Martin',   'Computer Science', '2', 'IT Section',      'A1', '2', 'Available'),
    ('BK-0003', '9780596007126', 'Head First Design Patterns',          'Freeman & Robson',   'Computer Science', '2', 'IT Section',      'A2', '1', 'Available'),
    ('BK-0004', '9780743273565', 'The Great Gatsby',                    'F. Scott Fitzgerald','Literature',       '1', 'Fiction Section', 'B3', '2', 'Available'),
    ('BK-0005', '9780061120084', 'To Kill a Mockingbird',               'Harper Lee',         'Literature',       '1', 'Fiction Section', 'B3', '3', 'Available');
