<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class EntryPointContractTest extends TestCase
{
    public function testSingleBackendEntryPointBootstrapsApplication(): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php';
        self::assertFileExists($path);
        $source = file_get_contents($path);
        self::assertIsString($source);
        self::assertStringContainsString('ApplicationFactory', $source);
        self::assertStringContainsString('->run()', $source);
        self::assertStringNotContainsString('<html', strtolower($source));
    }
}
