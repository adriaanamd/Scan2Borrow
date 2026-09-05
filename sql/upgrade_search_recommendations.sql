-- ============================================================================
-- Scan2Borrow - Search-based borrower recommendation schema
-- Run after sql/upgrade_bulk_borrowing.sql on an existing database.
-- ============================================================================

USE `scan2borrow_2.0`;

CREATE TABLE IF NOT EXISTS `search_history` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`      INT NOT NULL,
    `search_query` VARCHAR(255) NOT NULL,
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_search_history_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    KEY `idx_search_history_user_created` (`user_id`, `created_at`, `id`)
) ENGINE=InnoDB;

ALTER TABLE `search_history`
    ADD INDEX IF NOT EXISTS `idx_search_history_user_created` (`user_id`, `created_at`, `id`);

CREATE TABLE IF NOT EXISTS `keywords` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `name`       VARCHAR(100) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `book_title_keywords` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `title_id`   INT NOT NULL,
    `keyword_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_book_title_keyword` (`title_id`, `keyword_id`),
    KEY `idx_book_title_keywords_keyword_title` (`keyword_id`, `title_id`),
    CONSTRAINT `fk_book_title_keywords_title` FOREIGN KEY (`title_id`) REFERENCES `book_titles`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_book_title_keywords_keyword` FOREIGN KEY (`keyword_id`) REFERENCES `keywords`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE `book_titles`
    ADD FULLTEXT KEY IF NOT EXISTS `ft_book_titles_title` (`title`),
    ADD FULLTEXT KEY IF NOT EXISTS `ft_book_titles_category` (`category_name`),
    ADD FULLTEXT KEY IF NOT EXISTS `ft_book_titles_author` (`author`),
    ADD FULLTEXT KEY IF NOT EXISTS `ft_book_titles_publisher_description` (`publisher`, `description`);

ALTER TABLE `book_copies`
    ADD INDEX IF NOT EXISTS `idx_copies_status_deleted_title` (`status`, `deleted_at`, `title_id`);

ALTER TABLE `borrowing_transactions`
    ADD INDEX IF NOT EXISTS `idx_transactions_user_return_id` (`user_id`, `return_date`, `id`);
