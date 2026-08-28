<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Infrastructure\Configuration\Environment;

final readonly class DatabaseConfig
{
    public function __construct(
        public string $host,
        public string $database,
        public string $username,
        public string $password,
        public int $port = 3306,
    ) {
    }

    public static function fromEnvironment(): self
    {
        Environment::load(dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . '.env');

        return new self(
            self::env('SCAN2BORROW_DB_HOST', self::env('DB_HOST', '127.0.0.1')),
            self::env('SCAN2BORROW_DB_NAME', self::env('DB_NAME', 'scan2borrow_2.0')),
            self::env('SCAN2BORROW_DB_USER', self::env('DB_USER', 'root')),
            self::env('SCAN2BORROW_DB_PASSWORD', self::env('DB_PASS', '')),
            (int) self::env('SCAN2BORROW_DB_PORT', self::env('DB_PORT', '3306')),
        );
    }

    private static function env(string $name, string $default): string
    {
        $value = getenv($name);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
