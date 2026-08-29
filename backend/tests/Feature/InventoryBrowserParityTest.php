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
        'class InventoryPage',
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
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'features' . DIRECTORY_SEPARATOR . 'staff' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'inventory' . DIRECTORY_SEPARATOR . 'inventory.page.js';
        self::assertFileExists($path);
        $script = file_get_contents($path);
        self::assertIsString($script);

        foreach (self::INTERACTION_MARKERS as $marker) {
            self::assertStringContainsString($marker, $script, "Missing inventory interaction: {$marker}");
        }

        self::assertStringContainsString('/api/books', $script);
        self::assertStringNotContainsString('books_api.php', $script);
    }

    public function testInventoryRendererUsesTitleQuantitiesAndCopyActions(): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'features' . DIRECTORY_SEPARATOR . 'staff' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'inventory' . DIRECTORY_SEPARATOR . 'inventory.page.js';
        $script = file_get_contents($path);
        self::assertIsString($script);

        foreach ([
            'book.title_id || book.id',
            'book.quantity',
            'book.available_quantity',
            'book.reserved_quantity',
            'book.borrowed_quantity',
            'data-act="copies"',
            'this.copyPanel.open',
        ] as $marker) {
            self::assertStringContainsString($marker, $script, "Missing title/copy inventory behavior: {$marker}");
        }

        self::assertStringNotContainsString('book.accession_no', $script);
    }

    public function testFeatureOwnedInventoryBoundariesExposeServiceAndDrawerContracts(): void
    {
        $root = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'features' . DIRECTORY_SEPARATOR . 'staff';
        $service = file_get_contents($root . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . 'inventory.service.js');
        $drawer = file_get_contents($root . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'book-drawer' . DIRECTORY_SEPARATOR . 'book-drawer.component.js');
        self::assertIsString($service);
        self::assertIsString($drawer);
        foreach (['/scan2borrow/api/books', 'action', 'list'] as $marker) {
            self::assertStringContainsString($marker, $service);
        }
        foreach (['book-form', 'bookDrawer', 'cover_file'] as $marker) {
            self::assertStringContainsString($marker, $drawer);
        }
    }

    public function testCopyPanelAndTitleActionsArePresentInInventoryPage(): void
    {
        $root = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'features' . DIRECTORY_SEPARATOR . 'staff';
        $markup = file_get_contents($root . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'inventory' . DIRECTORY_SEPARATOR . 'inventory.html');
        $panel = file_get_contents($root . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'copy-panel' . DIRECTORY_SEPARATOR . 'copy-panel.component.js');
        self::assertIsString($markup);
        self::assertIsString($panel);

        foreach (['id="copyModal"', 'id="copy-body"', 'id="title-seed-fields"', 'name="title_id"'] as $marker) {
            self::assertStringContainsString($marker, $markup, "Missing inventory markup: {$marker}");
        }
        foreach (['/scan2borrow/api/book-copies', 'copy_id', 'data-copy-action', 'Scan2BorrowConfirmation'] as $marker) {
            self::assertStringContainsString($marker, $panel, "Missing copy panel behavior: {$marker}");
        }
    }
}
