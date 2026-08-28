<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class InventoryMarkupParityTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const REQUIRED_MARKERS = [
        'id="inv-search"',
        'id="inv-status"',
        'id="inv-select-all"',
        'id="inventory-grid"',
        'id="bookDrawer"',
        'id="bookForm"',
        'id="book-id"',
        'name="barcode"',
        'name="accession_no"',
        'name="cover_file"',
        'id="cover-preview-wrap"',
        'data-scan-target="barcode"',
        'data-bs-toggle="offcanvas"',
    ];

    public function testStaticInventoryPagePreservesLegacyDomContract(): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'staff-books.html';
        self::assertFileExists($path);
        $html = file_get_contents($path);
        self::assertIsString($html);

        foreach (self::REQUIRED_MARKERS as $marker) {
            self::assertStringContainsString($marker, $html, "Missing inventory marker: {$marker}");
        }

        self::assertStringContainsString('frontend/assets/js/pages/inventory.js', $html);
        self::assertStringContainsString('frontend/assets/css/style.css', $html);
    }
}
