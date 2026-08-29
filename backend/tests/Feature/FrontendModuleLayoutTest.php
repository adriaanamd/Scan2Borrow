<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Tests\Support\FrontendPagePaths;

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

    public function testCanonicalPagesExposeOneFeatureModuleAndNoLegacyPageControllers(): void
    {
        foreach (FrontendPagePaths::all() as $pageName => $relativePath) {
            $path = FrontendPagePaths::path($pageName);
            self::assertFileExists($path, $relativePath);

            $source = (string) file_get_contents($path);
            self::assertSame(1, substr_count($source, 'data-app-page='), $pageName);
            self::assertSame(1, substr_count(strtolower($source), 'type="module"'), $pageName);
            self::assertStringNotContainsString('/frontend/assets/js/pages/', $source, $pageName);
            self::assertStringNotContainsString('/frontend/assets/js/guest/', $source, $pageName);

            preg_match_all('/<script\\b[^>]*>/i', $source, $tags);
            $moduleTags = array_values(array_filter(
                $tags[0],
                static fn (string $tag): bool => preg_match('/\\btype=["\']module["\']/i', $tag) === 1,
            ));
            self::assertCount(1, $moduleTags, $pageName);
            self::assertMatchesRegularExpression('/\\bsrc=["\']\/scan2borrow\/frontend\\/(?:app|features)\\/.+\\.js["\']/i', $moduleTags[0], $pageName);
        }
    }
}
