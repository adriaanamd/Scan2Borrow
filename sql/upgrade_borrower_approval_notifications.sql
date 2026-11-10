-- Scan2Borrow: borrower approval notification enum upgrade
USE `scan2borrow_2.0`;

SET @notification_type_column = (
    SELECT COLUMN_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'notifications'
      AND COLUMN_NAME = 'type'
    LIMIT 1
);

SET @notification_type_sql = IF(
    @notification_type_column IS NULL
    OR LOCATE('''borrow_approved''', @notification_type_column) > 0,
    'SELECT 1',
    'ALTER TABLE `notifications` MODIFY COLUMN `type` ENUM(''borrow_request'',''overdue_alert'',''return_alert'',''hold_available'',''renewal_approved'',''renewal_rejected'',''borrow_approved'') NOT NULL DEFAULT ''borrow_request'''
);

PREPARE notification_type_statement FROM @notification_type_sql;
EXECUTE notification_type_statement;
DEALLOCATE PREPARE notification_type_statement;
