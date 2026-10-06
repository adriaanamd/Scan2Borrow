-- ============================================================================
-- Scan2Borrow: librarian-approved return requests
-- Run after upgrade_bulk_borrowing.sql when the normalized model is installed.
-- Legacy-only databases are also supported. Safe to run more than once.
-- ============================================================================

USE `scan2borrow_2.0`;

ALTER TABLE `borrowing`
    ADD COLUMN IF NOT EXISTS `return_status` ENUM('none','pending','rejected') NOT NULL DEFAULT 'none' AFTER `return_date`,
    ADD COLUMN IF NOT EXISTS `return_requested_at` DATETIME DEFAULT NULL AFTER `return_status`,
    ADD COLUMN IF NOT EXISTS `return_decided_at` DATETIME DEFAULT NULL AFTER `return_requested_at`,
    ADD COLUMN IF NOT EXISTS `return_decided_by` INT DEFAULT NULL AFTER `return_decided_at`,
    ADD COLUMN IF NOT EXISTS `return_decision_note` VARCHAR(500) DEFAULT NULL AFTER `return_decided_by`,
    ADD INDEX IF NOT EXISTS `idx_borrow_return_status` (`return_status`, `return_requested_at`);

-- Existing installations may still use only the legacy borrowing table. Apply
-- normalized return metadata when those optional tables have already been
-- created by upgrade_bulk_borrowing.sql, without blocking the legacy path.
SET @borrowing_transactions_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'borrowing_transactions'
);
SET @transaction_return_approval_sql := IF(
    @borrowing_transactions_exists > 0,
    'ALTER TABLE `borrowing_transactions`
        ADD COLUMN IF NOT EXISTS `return_status` ENUM(''none'',''pending'',''rejected'') NOT NULL DEFAULT ''none'' AFTER `return_date`,
        ADD COLUMN IF NOT EXISTS `return_requested_at` DATETIME DEFAULT NULL AFTER `return_status`,
        ADD COLUMN IF NOT EXISTS `return_decided_at` DATETIME DEFAULT NULL AFTER `return_requested_at`,
        ADD COLUMN IF NOT EXISTS `return_decided_by` INT DEFAULT NULL AFTER `return_decided_at`,
        ADD COLUMN IF NOT EXISTS `return_decision_note` VARCHAR(500) DEFAULT NULL AFTER `return_decided_by`,
        ADD INDEX IF NOT EXISTS `idx_transactions_return_status` (`return_status`, `return_requested_at`)',
    'SELECT 1'
);
PREPARE transaction_return_approval_statement FROM @transaction_return_approval_sql;
EXECUTE transaction_return_approval_statement;
DEALLOCATE PREPARE transaction_return_approval_statement;

SET @borrowing_items_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'borrowing_items'
);
SET @item_return_approval_sql := IF(
    @borrowing_items_exists > 0,
    'ALTER TABLE `borrowing_items`
        ADD COLUMN IF NOT EXISTS `return_status` ENUM(''none'',''pending'',''rejected'') NOT NULL DEFAULT ''none'' AFTER `return_date`,
        ADD COLUMN IF NOT EXISTS `return_requested_at` DATETIME DEFAULT NULL AFTER `return_status`,
        ADD COLUMN IF NOT EXISTS `return_decided_at` DATETIME DEFAULT NULL AFTER `return_requested_at`,
        ADD COLUMN IF NOT EXISTS `return_decided_by` INT DEFAULT NULL AFTER `return_decided_at`,
        ADD COLUMN IF NOT EXISTS `return_decision_note` VARCHAR(500) DEFAULT NULL AFTER `return_decided_by`,
        ADD INDEX IF NOT EXISTS `idx_items_return_status` (`return_status`, `return_requested_at`)',
    'SELECT 1'
);
PREPARE item_return_approval_statement FROM @item_return_approval_sql;
EXECUTE item_return_approval_statement;
DEALLOCATE PREPARE item_return_approval_statement;

-- Clean current base installs do not include the legacy guest tables. Apply
-- these columns when that optional guest schema is present, without making the
-- borrower return migration fail on a clean base import.
SET @visitor_borrowing_exists := (
    SELECT COUNT(*)
    FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'visitor_borrowing'
);
SET @guest_return_approval_sql := IF(
    @visitor_borrowing_exists > 0,
    'ALTER TABLE `visitor_borrowing`
        ADD COLUMN IF NOT EXISTS `return_decided_at` DATETIME DEFAULT NULL AFTER `return_requested_at`,
        ADD COLUMN IF NOT EXISTS `return_decided_by` INT DEFAULT NULL AFTER `return_decided_at`,
        ADD COLUMN IF NOT EXISTS `return_decision_note` VARCHAR(500) DEFAULT NULL AFTER `return_decided_by`',
    'SELECT 1'
);
PREPARE guest_return_approval_statement FROM @guest_return_approval_sql;
EXECUTE guest_return_approval_statement;
DEALLOCATE PREPARE guest_return_approval_statement;

-- Guest returns use the existing request_status value: Return Verification Pending.
