<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class Runtime
{
    /**
     * @var array<string, string>
     */
    private const MINIMUM_PATCHED_VERSIONS = [
        '8.2' => '8.2.35',
        '8.3' => '8.3.36',
        '8.4' => '8.4.27',
        '8.5' => '8.5.12',
    ];

    public static function minimumPhpVersion(): string
    {
        return self::MINIMUM_PATCHED_VERSIONS['8.2'];
    }

    public static function assertSupported(string $version): void
    {
        $branch = self::branch($version);
        $minimumVersion = $branch === null ? null : self::MINIMUM_PATCHED_VERSIONS[$branch] ?? null;

        if ($minimumVersion === null || version_compare($version, $minimumVersion, '<')) {
            throw new RuntimeException(
                'A patched PHP release is required; detected ' . $version . '.',
            );
        }
    }

    private static function branch(string $version): ?string
    {
        $parts = explode('.', $version);
        if (count($parts) < 2 || !ctype_digit($parts[0]) || !ctype_digit($parts[1])) {
            return null;
        }

        return $parts[0] . '.' . $parts[1];
    }
}
