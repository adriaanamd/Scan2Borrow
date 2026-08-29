<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class InventoryBrowserParityTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const INTERACTION_MARKERS = [
        'class InventoryPageController',
        'new Set()',
        'setTimeout',
        'new bootstrap.Offcanvas',
        'URL.createObjectURL',
        'data-bulk',
        'new FormData',
        'toast(',
        'renderPager',
        'renderSortIndicators',
    ];

    public function testInventoryControllerKeepsBrowserInteractionHooks(): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'inventory.js';
        self::assertFileExists($path);
        $script = file_get_contents($path);
        self::assertIsString($script);

        foreach (self::INTERACTION_MARKERS as $marker) {
            self::assertStringContainsString($marker, $script, "Missing inventory interaction: {$marker}");
        }

        self::assertStringContainsString('/api/books', $script);
        self::assertStringNotContainsString('books_api.php', $script);
    }

    public function testInventoryRendererIncludesAccessionBeforeStatusAndLocationColumns(): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'inventory.js';
        $script = file_get_contents($path);
        self::assertIsString($script);

        $accession = strpos($script, 'book.accession_no');
        $publisher = strpos($script, 'book.publisher');
        $status = strpos($script, 'this.badge(book.status)');
        $location = strpos($script, 'book.floor_no');

        self::assertIsInt($accession);
        self::assertIsInt($publisher);
        self::assertIsInt($status);
        self::assertIsInt($location);
        self::assertLessThan($publisher, $accession);
        self::assertLessThan($status, $publisher);
        self::assertLessThan($location, $status);
    }

    public function testFeatureOwnedInventoryBoundariesExposeServiceAndDrawerContracts(): void
    {
        $root = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'features' . DIRECTORY_SEPARATOR . 'staff';
        $service = file_get_contents($root . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . 'inventory.service.js');
        $drawer = file_get_contents($root . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'book-drawer' . DIRECTORY_SEPARATOR . 'book-drawer.component.js');
        self::assertIsString($service);
        self::assertIsString($drawer);
        foreach (['/scan2borrow/api/books', 'action', 'ids'] as $marker) {
            self::assertStringContainsString($marker, $service);
        }
        foreach (['book-form', 'bookDrawer', 'cover_file'] as $marker) {
            self::assertStringContainsString($marker, $drawer);
        }
    }
}
