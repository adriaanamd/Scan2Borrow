-- ============================================================================
-- Scan2Borrow: librarian-approved return requests
-- Run after upgrade_bulk_borrowing.sql. Safe to run more than once.
-- ============================================================================

USE `scan2borrow_2.0`;

ALTER TABLE `borrowing`
    ADD COLUMN IF NOT EXISTS `return_status` ENUM('none','pending','rejected') NOT NULL DEFAULT 'none' AFTER `return_date`,
    ADD COLUMN IF NOT EXISTS `return_requested_at` DATETIME DEFAULT NULL AFTER `return_status`,
    ADD COLUMN IF NOT EXISTS `return_decided_at` DATETIME DEFAULT NULL AFTER `return_requested_at`,
    ADD COLUMN IF NOT EXISTS `return_decided_by` INT DEFAULT NULL AFTER `return_decided_at`,
    ADD COLUMN IF NOT EXISTS `return_decision_note` VARCHAR(500) DEFAULT NULL AFTER `return_decided_by`,
    ADD INDEX IF NOT EXISTS `idx_borrow_return_status` (`return_status`, `return_requested_at`);

ALTER TABLE `borrowing_transactions`
    ADD COLUMN IF NOT EXISTS `return_status` ENUM('none','pending','rejected') NOT NULL DEFAULT 'none' AFTER `return_date`,
    ADD COLUMN IF NOT EXISTS `return_requested_at` DATETIME DEFAULT NULL AFTER `return_status`,
    ADD COLUMN IF NOT EXISTS `return_decided_at` DATETIME DEFAULT NULL AFTER `return_requested_at`,
    ADD COLUMN IF NOT EXISTS `return_decided_by` INT DEFAULT NULL AFTER `return_decided_at`,
    ADD COLUMN IF NOT EXISTS `return_decision_note` VARCHAR(500) DEFAULT NULL AFTER `return_decided_by`,
    ADD INDEX IF NOT EXISTS `idx_transactions_return_status` (`return_status`, `return_requested_at`);

ALTER TABLE `borrowing_items`
    ADD COLUMN IF NOT EXISTS `return_status` ENUM('none','pending','rejected') NOT NULL DEFAULT 'none' AFTER `return_date`,
    ADD COLUMN IF NOT EXISTS `return_requested_at` DATETIME DEFAULT NULL AFTER `return_status`,
    ADD COLUMN IF NOT EXISTS `return_decided_at` DATETIME DEFAULT NULL AFTER `return_requested_at`,
    ADD COLUMN IF NOT EXISTS `return_decided_by` INT DEFAULT NULL AFTER `return_decided_at`,
    ADD COLUMN IF NOT EXISTS `return_decision_note` VARCHAR(500) DEFAULT NULL AFTER `return_decided_by`,
    ADD INDEX IF NOT EXISTS `idx_items_return_status` (`return_status`, `return_requested_at`);

ALTER TABLE `visitor_borrowing`
    ADD COLUMN IF NOT EXISTS `return_decided_at` DATETIME DEFAULT NULL AFTER `return_requested_at`,
    ADD COLUMN IF NOT EXISTS `return_decided_by` INT DEFAULT NULL AFTER `return_decided_at`,
    ADD COLUMN IF NOT EXISTS `return_decision_note` VARCHAR(500) DEFAULT NULL AFTER `return_decided_by`;

-- Guest returns use the existing request_status value: Return Verification Pending.
