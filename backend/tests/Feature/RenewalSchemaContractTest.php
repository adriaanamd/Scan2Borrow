<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class RenewalSchemaContractTest extends TestCase
{
    public function testRenewalMigrationDefinesAuditedRequestsAndOneApprovedRenewalGuard(): void
    {
        $migration = (string) file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'upgrade_renewals.sql');

        foreach ([
            'CREATE TABLE IF NOT EXISTS `renewal_requests`',
            '`loan_id`',
            "ENUM('pending','approved','rejected','cancelled')",
            '`original_due_date`',
            '`requested_due_date`',
            '`approved_by`',
            'uq_renewal_approved_loan',
            'idx_renewal_pending',
        ] as $marker) {
            self::assertStringContainsString($marker, $migration, 'Renewal migration missing marker: ' . $marker);
        }
    }
}
