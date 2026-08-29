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
            '`barcode`', '`role`', "ENUM('admin','librarian','student','teacher')",
            'CREATE TABLE `books`', '`isbn`', '`cover_file`', '`status`', "ENUM('Available','Borrowed','Reserved')",
            'CREATE TABLE `borrowing`', '`transaction_code`', '`fine_amount`', "ENUM('Pending','Borrowed','Returned','Overdue')",
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
        ] as $filename) {
            $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . $filename;
            self::assertFileExists($path, "Missing SQL migration or seed file: {$filename}");
            self::assertNotSame('', trim((string) file_get_contents($path)));
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
