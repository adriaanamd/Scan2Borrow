<?php

declare(strict_types=1);

namespace App\Bootstrap;

final class ApplicationFactory
{
    public static function create(string $environment = 'production'): Application
    {
        return new Application($environment);
    }
}
