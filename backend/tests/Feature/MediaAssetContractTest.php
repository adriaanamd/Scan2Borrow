<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Tests\Support\FrontendPagePaths;

final class MediaAssetContractTest extends TestCase
{
    public function testVanillaMediaResolverNormalizesStoredUploadPaths(): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'media.js';
        self::assertFileExists($path);

        $source = (string) file_get_contents($path);
        self::assertStringContainsString('class Scan2BorrowMedia', $source);
        self::assertStringContainsString('uploads/', $source);
        self::assertStringContainsString('/scan2borrow/', $source);
        self::assertStringContainsString('data:image', $source);
        self::assertStringContainsString('window.Scan2BorrowMedia', $source);
    }

    /** @dataProvider imageConsumerProvider */
    public function testImageConsumersUseTheSharedMediaResolver(string $script, string $page): void
    {
        $root = dirname(__DIR__, 3);
        $scriptPath = $root . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $script);
        $pageName = pathinfo($page, PATHINFO_FILENAME);
        $pagePath = FrontendPagePaths::path($pageName);

        $scriptSource = (string) file_get_contents($scriptPath);
        $pageSource = (string) file_get_contents($pagePath);

        self::assertStringContainsString('Scan2BorrowMedia', $scriptSource, $script);
        self::assertStringContainsString('core/media.js', $pageSource, $page);
    }

    /** @return list<array{string, string}> */
    public static function imageConsumerProvider(): array
    {
        return [
            ['features/student/pages/search/student-search.page.js', 'student-search'],
            ['features/staff/pages/inventory/inventory.page.js', 'staff-books'],
            ['features/staff/pages/dashboard/staff-dashboard.page.js', 'staff-dashboard'],
            ['features/staff/pages/borrower-detail/borrower-detail.page.js', 'staff-borrower'],
            ['features/staff/pages/guest-requests/guest-requests.page.js', 'staff-guest-requests'],
            ['features/guest/pages/browse/guest-browse.page.js', 'guest-browse'],
            ['features/guest/pages/borrowed/guest-borrowed.page.js', 'guest-borrowed'],
        ];
    }

    public function testGuestRequestInlinePhotoHandlersUseTheSharedResolver(): void
    {
        $path = FrontendPagePaths::path('staff-guest-requests');
        $source = (string) file_get_contents($path);

        self::assertStringContainsString('Scan2BorrowMedia.resolve', $source);
    }
}
