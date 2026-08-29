<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class FrontendModuleLayoutTest extends TestCase
{
    public function testBootstrapFixtureUsesOnePageMarkerAndModuleEntry(): void
    {
        $path = dirname(__DIR__, 3)
            . DIRECTORY_SEPARATOR . 'frontend'
            . DIRECTORY_SEPARATOR . 'tests'
            . DIRECTORY_SEPARATOR . 'fixtures'
            . DIRECTORY_SEPARATOR . 'student-dashboard.html';
        self::assertFileExists($path);

        $source = (string) file_get_contents($path);
        self::assertSame(1, substr_count($source, 'data-app-page='));
        self::assertSame(1, substr_count($source, 'type="module"'));
    }

    public function testAngularLikeFrontendBoundariesExist(): void
    {
        $root = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR;

        self::assertDirectoryExists($root . 'app');
        self::assertDirectoryExists($root . 'features');
        self::assertDirectoryExists($root . 'tests');
    }
}
