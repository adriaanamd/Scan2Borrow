-- Migration: add department and position columns to users table
ALTER TABLE `users`
    ADD COLUMN `department` VARCHAR(120) DEFAULT NULL,
    ADD COLUMN `position` VARCHAR(120) DEFAULT NULL;

-- You can run this using:
-- mysql -u root -p scan2borrow_2.0 < sql/upgrade_add_teacher_fields.sql
