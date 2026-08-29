<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class FrontendModuleLayoutTest extends TestCase
{
    public function testAngularLikeFrontendBoundariesExist(): void
    {
        $root = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR;

        self::assertDirectoryExists($root . 'app');
        self::assertDirectoryExists($root . 'features');
        self::assertDirectoryExists($root . 'tests');
    }
}
