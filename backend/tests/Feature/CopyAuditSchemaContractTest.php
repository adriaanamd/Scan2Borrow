<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class CopyAuditSchemaContractTest extends TestCase
{
    public function testFreshSchemaDefinesFiveCopyStatusesAndAuditTable(): void
    {
        $sql = $this->readSql('database.sql');

        self::assertStringContainsString("ENUM('Available','Borrowed','Reserved','Lost','Damaged')", $sql);
        self::assertStringContainsString('CREATE TABLE `audit_events`', $sql);
        self::assertStringContainsString('KEY `idx_audit_copy_occurred`', $sql);
    }

    public function testUpgradeDefinesIdempotentAuditSchemaAndLegacyGuard(): void
    {
        $sql = $this->readSql('upgrade_copy_audit_trail.sql');

        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `audit_events`', $sql);
        self::assertStringContainsString('uq_audit_legacy_source', $sql);
        self::assertStringContainsString('NOT EXISTS', $sql);
    }

    private function readSql(string $filename): string
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . $filename;
        $contents = file_get_contents($path);
        self::assertIsString($contents);

        return $contents;
    }
}
