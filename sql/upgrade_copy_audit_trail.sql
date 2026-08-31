-- Scan2Borrow - immutable physical-copy business audit trail.
-- Run after upgrade_bulk_borrowing.sql and upgrade_barcode_printing.sql.
USE `scan2borrow_2.0`;

ALTER TABLE `book_copies`
    MODIFY COLUMN `status` ENUM('Available','Borrowed','Reserved','Lost','Damaged') NOT NULL DEFAULT 'Available';

ALTER TABLE `books`
    MODIFY COLUMN `status` ENUM('Available','Borrowed','Reserved','Lost','Damaged') NOT NULL DEFAULT 'Available';

CREATE TABLE IF NOT EXISTS `audit_events` (
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
