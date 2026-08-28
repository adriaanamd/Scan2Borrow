-- ============================================================================
-- Scan2Borrow - Security Hardening Upgrade
-- Adds security-focused fields and constraints
-- ============================================================================
USE `scan2borrow_2.0`;

-- Users: track login attempts for brute-force protection
ALTER TABLE `users`
    ADD COLUMN `failed_attempts` INT NOT NULL DEFAULT 0 AFTER `status`,
    ADD COLUMN `locked_until` DATETIME DEFAULT NULL AFTER `failed_attempts`,
    ADD COLUMN `last_login` DATETIME DEFAULT NULL AFTER `locked_until`;

-- Users: password reset tokens (for staff accounts)
ALTER TABLE `users`
    ADD COLUMN `reset_token` VARCHAR(255) DEFAULT NULL AFTER `last_login`,
    ADD COLUMN `reset_expires` DATETIME DEFAULT NULL AFTER `reset_token`;

-- Audit log: track important actions for security monitoring
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT DEFAULT NULL,
    `action`      VARCHAR(100) NOT NULL,
    `details`     TEXT DEFAULT NULL,
    `ip_address`  VARCHAR(45) DEFAULT NULL,
    `user_agent`  VARCHAR(255) DEFAULT NULL,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_action` (`user_id`, `action`, `created_at`)
) ENGINE=InnoDB;