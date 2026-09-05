<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class SchemaContractTest extends TestCase
{
    public function testBaseSchemaRetainsExistingTablesColumnsAndStatuses(): void
    {
        $sql = $this->readSql('database.sql');
        foreach ([
            'CREATE TABLE `users`',
            '`barcode`', '`department`', '`position`', '`role`', "ENUM('admin','librarian','student','teacher')",
            'CREATE TABLE `books`', '`isbn`', '`cover_file`', '`status`', "ENUM('Available','Borrowed','Reserved','Lost','Damaged')",
            'CREATE TABLE `borrowing`', '`transaction_code`', '`fine_amount`', "ENUM('Pending','Borrowed','Returned','Overdue')",
            'CREATE TABLE `profile_change_requests`', '`original_values`', '`requested_values`', '`reviewed_by`',
            "ENUM('pending','approved','rejected')", 'idx_profile_change_status_requested',
        ] as $marker) {
            self::assertStringContainsString($marker, $sql, "Base SQL contract disappeared: {$marker}");
        }
    }

    public function testFullDatabaseDumpRetainsRuntimeSchemaExtensions(): void
    {
        $sql = $this->readRootSql('scan2borrow_2_0.sql');
        foreach ([
            'CREATE TABLE `books`', '`accession_no`', '`category_name`',
            'CREATE TABLE `book_keywords`', 'CREATE TABLE `search_history`',
            'CREATE TABLE `notifications`', 'CREATE TABLE `return_notifications`',
            'CREATE TABLE `otp_codes`', 'CREATE TABLE `sms_logs`',
        ] as $marker) {
            self::assertStringContainsString($marker, $sql, "Full dump SQL contract disappeared: {$marker}");
        }
    }

    public function testSearchRecommendationMigrationCreatesNormalizedIndexes(): void
    {
        $migration = $this->readSql('upgrade_search_recommendations.sql');
        foreach ([
            'CREATE TABLE IF NOT EXISTS `search_history`',
            'KEY `idx_search_history_user_created` (`user_id`, `created_at`, `id`)',
            'CREATE TABLE IF NOT EXISTS `keywords`',
            'CREATE TABLE IF NOT EXISTS `book_title_keywords`',
            'UNIQUE KEY `uq_book_title_keyword` (`title_id`, `keyword_id`)',
            'KEY `idx_book_title_keywords_keyword_title` (`keyword_id`, `title_id`)',
            'FULLTEXT KEY `ft_book_titles_title` (`title`)',
            'FULLTEXT KEY `ft_book_titles_category` (`category_name`)',
            'FULLTEXT KEY `ft_book_titles_author` (`author`)',
            'FULLTEXT KEY `ft_book_titles_publisher_description` (`publisher`, `description`)',
            'KEY `idx_copies_status_deleted_title` (`status`, `deleted_at`, `title_id`)',
            'KEY `idx_transactions_user_return_id` (`user_id`, `return_date`, `id`)',
        ] as $marker) {
            self::assertStringContainsString($marker, $migration, "Recommendation migration missing marker: {$marker}");
        }
    }

    public function testUpgradeSqlRetainsGuestAndSecurityExtensions(): void
    {
        $sql = $this->readSql('upgrade.sql');
        foreach ([
            'CREATE TABLE IF NOT EXISTS `visitors`', '`visitor_number`', '`qr_token`', '`account_status`',
            'CREATE TABLE IF NOT EXISTS `visitor_borrowing`', '`request_status`', '`verification_photo`', '`return_verification_photo`',
            'CREATE TABLE IF NOT EXISTS `visitor_notifications`',
            'CREATE TABLE IF NOT EXISTS `visitor_visit_history`',
            'CREATE TABLE IF NOT EXISTS `visitor_security_logs`',
        ] as $marker) {
            self::assertStringContainsString($marker, $sql, "Upgrade SQL contract disappeared: {$marker}");
        }
    }

    public function testAllExistingUpgradeScriptsRemainAvailable(): void
    {
        foreach ([
            'upgrade.sql', 'upgrade_add_teacher_fields.sql', 'upgrade_approval_system.sql',
            'upgrade_borrowing_control.sql', 'upgrade_notification_system.sql',
            'upgrade_pending_status.sql', 'upgrade_security.sql', 'upgrade_bulk_borrowing.sql', 'sample_books_import.sql',
            'upgrade_barcode_printing.sql', 'upgrade_copy_audit_trail.sql',
            'upgrade_profile_change_requests.sql', 'upgrade_approval_status_sync.sql', 'upgrade_search_recommendations.sql',
        ] as $filename) {
            $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . $filename;
            self::assertFileExists($path, "Missing SQL migration or seed file: {$filename}");
            self::assertNotSame('', trim((string) file_get_contents($path)));
        }
    }

    public function testReturnApprovalMigrationDefinesPendingAndDecisionMetadata(): void
    {
        $base = $this->readSql('database.sql');
        $migration = $this->readSql('upgrade_return_approval.sql');

        foreach ([
            '`return_status`', "ENUM('none','pending','rejected')",
            '`return_requested_at`', '`return_decided_at`', '`return_decided_by`', '`return_decision_note`',
        ] as $marker) {
            self::assertStringContainsString($marker, $base, 'Fresh schema missing return approval marker: ' . $marker);
            self::assertStringContainsString($marker, $migration, 'Return approval migration missing marker: ' . $marker);
        }

        self::assertStringContainsString('Return Verification Pending', $migration);
        self::assertStringContainsString('ADD COLUMN IF NOT EXISTS', $migration);
        self::assertStringContainsString('upgrade_bulk_borrowing.sql', $migration);
        $guestSchema = $this->readSql('upgrade.sql');
        self::assertStringContainsString('return_decided_at', $guestSchema);
        self::assertStringContainsString('return_decided_by', $guestSchema);
        self::assertStringContainsString('return_decision_note', $guestSchema);
        self::assertStringContainsString('upgrade_return_approval.sql', $this->readRoot('README.md'));
        self::assertStringContainsString('information_schema.tables', $migration);
        self::assertStringContainsString('visitor_borrowing_exists', $migration);
    }

    private function readRoot(string $filename): string
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . $filename;
        self::assertFileExists($path);
        $contents = file_get_contents($path);
        self::assertIsString($contents);

        return $contents;
    }

    public function testApprovalStatusRepairMigrationIsAvailableAndIdempotentByPredicate(): void
    {
        $migration = str_replace(["\r\n", "\r"], "\n", $this->readSql('upgrade_approval_status_sync.sql'));

        foreach ([
            'USE `scan2borrow_2.0`;',
            'UPDATE `borrowing_items` AS item_record',
            'JOIN `borrowing_transactions` AS transaction_record',
            "transaction_record.`approval_status` = 'approved'",
            "item_record.`status` = 'Pending'",
            "item_record.`status` = 'Borrowed'",
            "transaction_record.`approval_status` = 'rejected'",
            "item_record.`status` = 'Returned'",
            'CURRENT_TIMESTAMP',
        ] as $marker) {
            self::assertStringContainsString($marker, $migration, 'Approval status repair migration missing marker: ' . $marker);
        }
    }

    public function testProfileChangeMigrationIsIdempotentAndLinkedToUsers(): void
    {
        $sql = $this->readSql('upgrade_profile_change_requests.sql');

        foreach ([
            'CREATE TABLE IF NOT EXISTS `profile_change_requests`',
            'CONSTRAINT `fk_profile_change_user`',
            'CONSTRAINT `fk_profile_change_reviewer`',
            '`original_photo`', '`requested_photo`', '`review_note`',
        ] as $marker) {
            self::assertStringContainsString($marker, $sql, 'Profile change migration missing marker: ' . $marker);
        }
    }

    public function testBulkBorrowingSchemaIsPresentInFreshSchemaAndMigration(): void
    {
        $base = $this->readSql('database.sql');
        $migration = $this->readSql('upgrade_bulk_borrowing.sql');

        foreach ([
            'CREATE TABLE `book_titles`', '`quantity`', 'CREATE TABLE `book_copies`',
            'CREATE TABLE `borrowing_transactions`', 'CREATE TABLE `borrowing_items`',
        ] as $marker) {
            self::assertStringContainsString($marker, $base, "Fresh schema missing bulk borrowing marker: {$marker}");
        }
        foreach ([
            'CREATE TABLE IF NOT EXISTS `book_titles`', 'CREATE TABLE IF NOT EXISTS `book_copies`',
            'CREATE TABLE IF NOT EXISTS `borrowing_transactions`', 'CREATE TABLE IF NOT EXISTS `borrowing_items`',
        ] as $marker) {
            self::assertStringContainsString($marker, $migration, "Migration missing bulk borrowing marker: {$marker}");
        }
    }

    public function testBulkMigrationHandlesLegacyCollationsAndGroupsDuplicateIsbnsAsOneTitle(): void
    {
        $migration = str_replace(["\r\n", "\r"], "\n", $this->readSql('upgrade_bulk_borrowing.sql'));

        foreach ([
            "GROUP BY\n    NULLIF(TRIM(source_book.`isbn`), '')",
            'existing_title.`title` COLLATE utf8mb4_unicode_ci = source_book.`title` COLLATE utf8mb4_unicode_ci',
            'existing_copy.`barcode` COLLATE utf8mb4_unicode_ci = source_book.`barcode` COLLATE utf8mb4_unicode_ci',
            'existing_transaction.`transaction_code` COLLATE utf8mb4_unicode_ci',
        ] as $marker) {
            self::assertStringContainsString($marker, $migration, 'Migration is not safe for legacy text collations: ' . $marker);
        }
    }

    public function testReservationMigrationDefinesFairTitleQueueAndExpiryIndexes(): void
    {
        $migration = $this->readSql('upgrade_reservations.sql');

        foreach ([
            'CREATE TABLE IF NOT EXISTS `reservations`', '`queue_sequence`', '`hold_expires_at`',
            "ENUM('queued','offered','claimed','fulfilled','expired','cancelled')",
            'UNIQUE KEY `uq_reservation_active_user_title`',
            'KEY `idx_reservations_title_queue`', 'KEY `idx_reservations_expiry`',
        ] as $marker) {
            self::assertStringContainsString($marker, $migration, 'Reservation migration missing marker: ' . $marker);
        }
    }

    public function testBarcodePrintingMigrationAddsIrreversibleCopyMarkerAndBatchSnapshots(): void
    {
        $migration = str_replace(["\r\n", "\r"], "\n", $this->readSql('upgrade_barcode_printing.sql'));

        foreach ([
            'ALTER TABLE `book_copies`', '`printed_at` DATETIME DEFAULT NULL',
            'CREATE TABLE IF NOT EXISTS `barcode_print_batches`', '`batch_token`', '`printed_by`',
            'CREATE TABLE IF NOT EXISTS `barcode_print_batch_items`', '`batch_id`', '`copy_id`',
            '`barcode`', '`accession_no`',
        ] as $marker) {
            self::assertStringContainsString($marker, $migration, "Barcode printing migration missing marker: {$marker}");
        }

        self::assertStringContainsString('ADD COLUMN IF NOT EXISTS `printed_at`', $migration);
        self::assertStringContainsString('UNIQUE KEY `uq_barcode_print_batch_copy`', $migration);
        self::assertStringContainsString('Run after `upgrade_bulk_borrowing.sql`', $migration);
    }

    private function readSql(string $filename): string
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . $filename;
        self::assertFileExists($path);
        $contents = file_get_contents($path);
        self::assertIsString($contents);

        return $contents;
    }

    private function readRootSql(string $filename): string
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . $filename;
        self::assertFileExists($path);
        $contents = file_get_contents($path);
        self::assertIsString($contents);

        return $contents;
    }
}
