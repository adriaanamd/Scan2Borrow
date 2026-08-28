<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class Runtime
{
    public static function minimumPhpVersion(): string
    {
        return '8.3.0';
    }

    public static function assertSupported(string $version): void
    {
        if (version_compare($version, self::minimumPhpVersion(), '<')) {
            throw new RuntimeException(
                'PHP 8.3+ is required; detected ' . $version . '.',
            );
        }
    }
}
