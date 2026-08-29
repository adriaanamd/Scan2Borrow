<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class SourceAccessTest extends TestCase
{
    public function testApacheDeniesApplicationSourceDirectories(): void
    {
        $htaccessPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . '.htaccess';
        self::assertFileExists($htaccessPath);

        $rules = file_get_contents($htaccessPath);
        self::assertIsString($rules);
        self::assertStringContainsString('frontend/pages', $rules);
        self::assertStringContainsString('frontend/features/.+\\.html', $rules);
        self::assertStringNotContainsString('frontend/features)(?:/|$)', $rules);
        self::assertStringContainsString('backend/src', $rules);
        self::assertStringContainsString('backend/tests', $rules);
        self::assertStringContainsString('backend/config', $rules);
        self::assertStringContainsString('[F,L', $rules);
        self::assertStringContainsString('THE_REQUEST', $rules);
        self::assertStringContainsString('backend/public/index\\.php', $rules);
    }
}
