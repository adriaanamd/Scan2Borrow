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
            'upgrade_pending_status.sql', 'upgrade_security.sql', 'sample_books_import.sql',
        ] as $filename) {
            $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . $filename;
            self::assertFileExists($path, "Missing SQL migration or seed file: {$filename}");
            self::assertNotSame('', trim((string) file_get_contents($path)));
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
