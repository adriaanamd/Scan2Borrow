<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class RenewalNotificationSchemaTest extends TestCase
{
    public function testReservationMigrationAllowsHoldAndRenewalNotificationTypes(): void
    {
        $migration = (string) file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'upgrade_reservations.sql');
        self::assertStringContainsString("'hold_available'", $migration);
        self::assertStringContainsString("'renewal_approved'", $migration);
        self::assertStringContainsString("'renewal_rejected'", $migration);
        self::assertStringContainsString('MODIFY `type` ENUM', $migration);
    }
}
