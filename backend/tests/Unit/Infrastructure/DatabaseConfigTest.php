<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Infrastructure\Database\DatabaseConfig;
use PHPUnit\Framework\TestCase;

final class DatabaseConfigTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $environment = [];

    protected function setUp(): void
    {
        foreach (['SCAN2BORROW_DB_HOST', 'SCAN2BORROW_DB_NAME', 'SCAN2BORROW_DB_USER', 'SCAN2BORROW_DB_PASSWORD', 'SCAN2BORROW_DB_PORT', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_PORT'] as $key) {
            $this->environment[$key] = getenv($key);
            putenv($key);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->environment as $key => $value) {
            if ($value === false) {
                putenv($key);
            } else {
                putenv($key . '=' . $value);
            }
        }
    }

    public function testLegacyXamppEnvironmentNamesRemainSupported(): void
    {
        putenv('DB_HOST=localhost');
        putenv('DB_NAME=legacy_database');
        putenv('DB_USER=legacy_user');
        putenv('DB_PASS=legacy_password');
        putenv('DB_PORT=3307');

        $config = DatabaseConfig::fromEnvironment();

        self::assertSame('localhost', $config->host);
        self::assertSame('legacy_database', $config->database);
        self::assertSame('legacy_user', $config->username);
        self::assertSame('legacy_password', $config->password);
        self::assertSame(3307, $config->port);
    }
}
