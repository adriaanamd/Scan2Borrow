<?php

declare(strict_types=1);

namespace Tests\Feature;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\FrontendPagePaths;

final class FrontendPagePathsTest extends TestCase
{
    public function testResolvesCanonicalFeatureTemplate(): void
    {
        $root = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR;

        self::assertSame(
            $root . 'features' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'login' . DIRECTORY_SEPARATOR . 'login.html',
            FrontendPagePaths::path('login'),
        );
    }

    public function testRejectsUnknownPageName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FrontendPagePaths::path('missing');
    }
}
