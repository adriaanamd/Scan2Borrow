-- Scan2Borrow: add the database-backed course catalog to an existing install.

CREATE TABLE IF NOT EXISTS `courses` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `name`       VARCHAR(120) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_courses_name` (`name`)
) ENGINE=InnoDB;

INSERT INTO `courses` (`name`)
SELECT `seed`.`name`
FROM (
    SELECT 'BSIT' AS `name`
    UNION ALL SELECT 'BSEd Major in Science'
    UNION ALL SELECT 'BSEd Major in English'
    UNION ALL SELECT 'BEeD'
    UNION ALL SELECT 'BSBA Major in Financial Management'
    UNION ALL SELECT 'BSBA Major in Human Resources Management'
    UNION ALL SELECT 'BSA'
) AS `seed`
WHERE NOT EXISTS (
    SELECT 1
    FROM `courses` AS `existing`
    WHERE `existing`.`name` = `seed`.`name`
);
