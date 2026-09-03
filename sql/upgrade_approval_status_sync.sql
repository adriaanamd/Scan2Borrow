-- ============================================================================
-- Scan2Borrow: Repair normalized borrowing item statuses after approval.
-- Run after upgrade_bulk_borrowing.sql. Safe to run more than once.
-- ============================================================================

USE `scan2borrow_2.0`;

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
