-- ============================================================================
-- Scan2Borrow - Library aide access profiles
-- ============================================================================
USE `scan2borrow_2.0`;

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `access_profile` ENUM('system_admin','library_aide') NULL AFTER `role`;

UPDATE `users`
SET `access_profile` = 'system_admin'
WHERE `role` = 'admin' AND (`access_profile` IS NULL OR `access_profile` = '');

INSERT INTO `users`
    (`barcode`, `firstname`, `middlename`, `lastname`, `course`, `year_level`, `email`, `contact_no`, `role`, `password_hash`, `status`, `access_profile`)
SELECT
    'LIBRARY001', 'Library', '', 'Aide', NULL, NULL, 'library@scan2borrow.local', NULL, 'admin',
    '$2y$12$6wjeleYKh0lntzDAIf7fuOhQN3KG9.tIQjd1x42uAnW3ECy2nUmSe', 'active', 'library_aide'
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `barcode` = 'LIBRARY001');
