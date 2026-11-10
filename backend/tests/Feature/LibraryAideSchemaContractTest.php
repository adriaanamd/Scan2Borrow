<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class LibraryAideSchemaContractTest extends TestCase
{
    public function testFreshInstallSeedsSeparateSystemAdminAndLibraryAideProfiles(): void
    {
        $install = $this->readSql('install.sql');
        $database = $this->readSql('database.sql');

        foreach ([$install, $database] as $sql) {
            self::assertStringContainsString("ENUM('system_admin','library_aide')", $sql);
            self::assertStringContainsString("'ADMIN001'", $sql);
            self::assertStringContainsString("'LIBRARY001'", $sql);
            self::assertStringContainsString("'library_aide'", $sql);
        }

        self::assertStringContainsString("'system_admin'", $install);
        self::assertStringContainsString(
            '$2y$12$6wjeleYKh0lntzDAIf7fuOhQN3KG9.tIQjd1x42uAnW3ECy2nUmSe',
            $install,
        );
    }

    public function testUpgradeBackfillsLegacyAdminsAndSeedsTheAideOnce(): void
    {
        $upgrade = $this->readSql('upgrade_library_aide_access.sql');

        self::assertStringContainsString(
            "ENUM('system_admin','library_aide')",
            $upgrade,
        );
        self::assertStringContainsString('UPDATE `users`', $upgrade);
        self::assertStringContainsString('SET `access_profile`', $upgrade);
        self::assertStringContainsString('INSERT INTO `users`', $upgrade);
        self::assertStringContainsString("WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `barcode` = 'LIBRARY001')", $upgrade);
    }

    private function readSql(string $filename): string
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . $filename;
        self::assertFileExists($path);
        $contents = file_get_contents($path);
        self::assertIsString($contents);

        return $contents;
    }
}
