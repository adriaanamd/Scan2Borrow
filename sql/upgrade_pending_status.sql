-- ============================================================================
-- Scan2Borrow - Add Pending status to borrowing table
-- Run this ONLY if you already have an existing database
-- This adds 'Pending' to the status ENUM in the borrowing table
-- ============================================================================
USE `scan2borrow_2.0`;

-- Modify the status column to include 'Pending'
ALTER TABLE `borrowing`
    MODIFY COLUMN `status` ENUM('Pending','Borrowed','Returned','Overdue') NOT NULL DEFAULT 'Borrowed';

-- ============================================================================
-- Done! The borrowing table now supports 'Pending' status for approval workflow
-- ============================================================================