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
}
