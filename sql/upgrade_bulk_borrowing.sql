-- ============================================================================
-- Scan2Borrow - Bulk borrowing catalog/copy and transaction migration.
-- Run after the existing upgrade scripts when preparing an existing database.
-- It is also required after a legacy database.sql import so its book and
-- borrowing rows are backfilled into the normalized model.
-- ============================================================================

USE `scan2borrow_2.0`;

CREATE TABLE IF NOT EXISTS `book_titles` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `isbn`          VARCHAR(30) DEFAULT NULL,
    `title`         VARCHAR(200) NOT NULL,
    `author`        VARCHAR(150) DEFAULT NULL,
    `publisher`     VARCHAR(150) DEFAULT NULL,
    `description`   TEXT DEFAULT NULL,
    `cover_file`    VARCHAR(255) DEFAULT NULL,
    `category_name` VARCHAR(100) DEFAULT NULL,
    `quantity`      INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_book_titles_title` (`title`),
    KEY `idx_book_titles_isbn` (`isbn`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `book_copies` (
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
    `status`       ENUM('Available','Borrowed','Reserved') NOT NULL DEFAULT 'Available',
    `deleted_at`   DATETIME DEFAULT NULL,
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_copy_title` FOREIGN KEY (`title_id`) REFERENCES `book_titles`(`id`) ON DELETE CASCADE,
    KEY `idx_copies_title_status` (`title_id`, `status`, `deleted_at`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `borrowing_transactions` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `transaction_code` VARCHAR(40) NOT NULL UNIQUE,
    `user_id`          INT NOT NULL,
    `processed_by`     INT DEFAULT NULL,
    `approval_status`  ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
    `borrow_date`      DATETIME NOT NULL,
    `due_date`         DATE NOT NULL,
    `return_date`      DATETIME DEFAULT NULL,
    `status`           ENUM('Pending','Borrowed','Returned','Overdue') NOT NULL DEFAULT 'Borrowed',
    `fine_amount`      DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `requested_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `approved_at`      TIMESTAMP NULL DEFAULT NULL,
    `approved_by`      INT DEFAULT NULL,
    `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_transaction_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_transaction_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_transaction_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    KEY `idx_transactions_user_status` (`user_id`, `status`, `return_date`),
    KEY `idx_transactions_approval` (`approval_status`, `requested_at`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `borrowing_items` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `transaction_id` INT NOT NULL,
    `copy_id`        INT NOT NULL,
    `return_date`    DATETIME DEFAULT NULL,
    `status`         ENUM('Pending','Borrowed','Returned','Overdue') NOT NULL DEFAULT 'Borrowed',
    `fine_amount`    DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_item_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `borrowing_transactions`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_item_copy` FOREIGN KEY (`copy_id`) REFERENCES `book_copies`(`id`) ON DELETE RESTRICT,
    UNIQUE KEY `uq_transaction_copy` (`transaction_id`, `copy_id`),
    KEY `idx_items_copy_active` (`copy_id`, `return_date`)
) ENGINE=InnoDB;

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

