<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class ContactNotificationSchemaContractTest extends TestCase
{
    public function testInstallAndUpgradeContainContactDeliverySchema(): void
    {
        $install = file_get_contents(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'install.sql');
        $upgrade = file_get_contents(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'upgrade.sql');

        self::assertIsString($install);
        self::assertIsString($upgrade);
        foreach ([$install, $upgrade] as $sql) {
            self::assertStringContainsString('contact_notification_deliveries', $sql);
            self::assertStringContainsString('borrow_requested', $sql);
            self::assertStringContainsString('borrow_approved', $sql);
            self::assertStringContainsString("'email'", $sql);
            self::assertStringContainsString("'sms'", $sql);
            self::assertStringContainsString("'pending'", $sql);
            self::assertStringContainsString("'sent'", $sql);
            self::assertStringContainsString("'failed'", $sql);
            self::assertStringContainsString("'skipped'", $sql);
            self::assertStringContainsString('recipient_type', $sql);
            self::assertStringContainsString('borrowing_reference', $sql);
        }
    }
}
