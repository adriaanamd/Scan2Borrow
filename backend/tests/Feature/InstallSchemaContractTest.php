<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class InstallSchemaContractTest extends TestCase
{
    public function testInstallScriptIsStandaloneAndContainsTheFinalTableManifest(): void
    {
        $sql = $this->readInstallSql();

        foreach ([
            'CREATE DATABASE IF NOT EXISTS `scan2borrow_2.0`',
            'USE `scan2borrow_2.0`',
            'SET FOREIGN_KEY_CHECKS = 0',
            'SET FOREIGN_KEY_CHECKS = 1',
            'INSERT INTO `users`',
            'INSERT INTO `books`',
            'INSERT INTO `book_titles`',
            'INSERT INTO `book_copies`',
            'INSERT INTO `borrowing_transactions`',
            'INSERT INTO `borrowing_items`',
            'CREATE TABLE `book_title_keywords`',
            'CREATE TABLE `barcode_print_batches`',
            'CREATE TABLE `barcode_print_batch_items`',
            'CREATE TABLE `reservations`',
            'CREATE TABLE `renewal_requests`',
            'CREATE TABLE `audit_events`',
        ] as $marker) {
            self::assertStringContainsString($marker, $sql, "Installer missing marker: {$marker}");
        }

        foreach ([
            'users', 'books', 'borrowing', 'book_titles', 'book_copies',
            'borrowing_transactions', 'borrowing_items', 'keywords',
            'book_keywords', 'book_title_keywords', 'search_history', 'book_views',
            'visitors', 'visitor_borrowing', 'visitor_notifications',
            'visitor_visit_history', 'visitor_security_logs', 'notifications',
            'sms_logs', 'otp_codes', 'return_notifications', 'audit_log',
            'profile_change_requests', 'barcode_print_batches',
            'barcode_print_batch_items', 'reservations', 'renewal_requests',
            'audit_events',
        ] as $table) {
            self::assertStringContainsString(
                "CREATE TABLE `{$table}`",
                $sql,
                "Installer missing final table: {$table}"
            );
        }
    }

    public function testInstallScriptHasNoExternalSourceDependencyAndOrdersForeignKeys(): void
    {
        $sql = $this->readInstallSql();

        self::assertDoesNotMatchRegularExpression('/^\s*SOURCE\s+/mi', $sql);

        $positions = [];
        foreach ([
            'users', 'books', 'borrowing', 'book_titles', 'book_copies',
            'borrowing_transactions', 'borrowing_items',
            'barcode_print_batches', 'barcode_print_batch_items',
            'reservations', 'renewal_requests', 'audit_events',
        ] as $table) {
            $marker = "CREATE TABLE `{$table}`";
            $position = strpos($sql, $marker);
            self::assertNotFalse($position, "Cannot order missing table: {$table}");
            $positions[$table] = $position;
        }

        self::assertLessThan($positions['borrowing'], $positions['books']);
        self::assertLessThan($positions['book_copies'], $positions['book_titles']);
        self::assertLessThan($positions['borrowing_items'], $positions['book_copies']);
        self::assertLessThan($positions['barcode_print_batch_items'], $positions['barcode_print_batches']);
        self::assertLessThan($positions['reservations'], $positions['book_copies']);
        self::assertLessThan($positions['renewal_requests'], $positions['borrowing_items']);
        self::assertLessThan($positions['audit_events'], $positions['barcode_print_batch_items']);
    }

    public function testFreshInstallDocumentationPointsToTheCanonicalInstaller(): void
    {
        $readme = file_get_contents(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'README.md');
        self::assertIsString($readme);
        self::assertStringContainsString('sql/install.sql', $readme);
        self::assertStringContainsString('existing database', strtolower($readme));
    }

    private function readInstallSql(): string
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'install.sql';
        self::assertFileExists($path);
        $sql = file_get_contents($path);
        self::assertIsString($sql);

        return $sql;
    }
}
