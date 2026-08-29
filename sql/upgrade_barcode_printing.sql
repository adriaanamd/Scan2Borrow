-- ============================================================================
-- Scan2Borrow - Irreversible physical-copy barcode export history.
-- Run after `upgrade_bulk_borrowing.sql`.
-- ============================================================================

USE `scan2borrow_2.0`;

ALTER TABLE `book_copies`
    ADD COLUMN IF NOT EXISTS `printed_at` DATETIME DEFAULT NULL AFTER `deleted_at`;

ALTER TABLE `book_copies`
    ADD KEY IF NOT EXISTS `idx_copies_title_printed` (`title_id`, `printed_at`, `deleted_at`);

CREATE TABLE IF NOT EXISTS `barcode_print_batches` (
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
CREATE TABLE IF NOT EXISTS `barcode_print_batch_items` (
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
