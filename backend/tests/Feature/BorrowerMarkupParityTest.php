<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Tests\Support\FrontendPagePaths;

final class BorrowerMarkupParityTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const REQUIRED_MARKERS = [
        'id="borrowModal"',
        'id="borrowForm"',
        'name="book_barcode"',
        'id="returnModal"',
        'name="return_input"',
        'data-scan-target="book_barcode"',
        'data-scan-target="return_input"',
        'id="successModal"',
        'id="successMessage"',
        'id="successTxnCode"',
        'id="successReceiptLink"',
        'Book Capacity',
        'Achievements',
        'Recommended for You',
    ];

    public function testStudentDashboardPreservesDomContract(): void
    {
        $path = FrontendPagePaths::path('student-dashboard');
        self::assertFileExists($path);
        $html = file_get_contents($path);
        self::assertIsString($html);

        foreach (self::REQUIRED_MARKERS as $marker) {
            self::assertStringContainsString($marker, $html, "Missing borrower marker: {$marker}");
        }

        self::assertStringContainsString('frontend/features/student/pages/dashboard/student-dashboard.page.js', $html);
        self::assertStringContainsString('frontend/assets/css/style.css', $html);
    }

    public function testCanonicalStudentDashboardUsesFeatureOwnedModule(): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'features' . DIRECTORY_SEPARATOR . 'student' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'dashboard' . DIRECTORY_SEPARATOR . 'dashboard.html';
        self::assertFileExists($path);
        $html = file_get_contents($path);
        self::assertIsString($html);
        self::assertStringContainsString('data-app-page="student-dashboard"', $html);
        self::assertStringContainsString('frontend/features/student/pages/dashboard/student-dashboard.page.js', $html);
        foreach (self::REQUIRED_MARKERS as $marker) {
            self::assertStringContainsString($marker, $html, "Missing canonical borrower marker: {$marker}");
        }
    }
}
