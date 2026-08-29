<?php

declare(strict_types=1);

namespace Tests\Support;

use InvalidArgumentException;

final class FrontendPagePaths
{
    /**
     * @var array<string, string>
     */
    private const MAP = [
        'login' => 'features/auth/pages/login/login.html',
    ];

    public static function path(string $name): string
    {
        if (!isset(self::MAP[$name])) {
            throw new InvalidArgumentException('Unknown frontend page: ' . $name);
        }

        return dirname(__DIR__, 3)
            . DIRECTORY_SEPARATOR . 'frontend'
            . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::MAP[$name]);
    }
}
