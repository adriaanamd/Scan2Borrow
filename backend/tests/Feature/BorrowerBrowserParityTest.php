<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class BorrowerBrowserParityTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const INTERACTION_MARKERS = [
        'class BorrowerDashboardController',
        'borrowForm',
        'returnForm',
        'new bootstrap.Modal',
        'fetch(',
        'JsBarcode',
        'successReceiptLink',
        'escapeHtml',
    ];

    public function testBorrowerControllerKeepsBrowserInteractionHooks(): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'borrower-dashboard.js';
        self::assertFileExists($path);
        $script = file_get_contents($path);
        self::assertIsString($script);

        foreach (self::INTERACTION_MARKERS as $marker) {
            self::assertStringContainsString($marker, $script, "Missing borrower interaction: {$marker}");
        }

        self::assertStringContainsString('/api/student/dashboard', $script);
        self::assertStringNotContainsString('studhome.php', $script);
        self::assertStringNotContainsString('send_notification.php', $script);
    }

    public function testCanonicalStaffBorrowerPagesUseFeatureEntries(): void
    {
        foreach ([
            ['borrowers/borrowers.html', 'staff-borrowers', 'borrowers/entry.js', ['Borrowers', 'name="search"']],
            ['borrower-detail/borrower-detail.html', 'staff-borrower-detail', 'borrower-detail/entry.js', ['Borrower Details', 'borrower-history', 'change-photo']],
        ] as [$relativePath, $pageName, $entry, $markers]) {
            $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'features' . DIRECTORY_SEPARATOR . 'staff' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            self::assertFileExists($path);
            $html = file_get_contents($path);
            self::assertIsString($html);
            self::assertStringContainsString('data-app-page="' . $pageName . '"', $html);
            self::assertStringContainsString('frontend/features/staff/pages/' . $entry, $html);
            foreach ($markers as $marker) self::assertStringContainsString($marker, $html);
        }
    }

    public function testBorrowerServicePreservesPhotoPermissionAndNotificationBoundaries(): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'features' . DIRECTORY_SEPARATOR . 'staff' . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . 'borrower.service.js';
        $script = file_get_contents($path);
        self::assertIsString($script);
        foreach (['/api/staff/borrowers', '/api/staff/borrower', '/api/staff/borrower/photo', 'photo_data', '/api/staff/notify'] as $marker) self::assertStringContainsString($marker, $script);
    }
}
