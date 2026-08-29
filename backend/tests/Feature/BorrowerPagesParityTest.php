<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class BorrowerPagesParityTest extends TestCase
{
    /**
     * @param list<string> $markers
     */
    private function assertPageContains(string $filename, array $markers): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . $filename;
        self::assertFileExists($path);
        $html = file_get_contents($path);
        self::assertIsString($html);

        foreach ($markers as $marker) {
            self::assertStringContainsString($marker, $html, "Missing {$filename} marker: {$marker}");
        }
    }

    public function testSearchPagePreservesCatalogFiltersCardsAndBorrowModal(): void
    {
        $this->assertPageContains('student-search.html', [
            'Book Catalog',
            'id="searchForm"',
            'name="category_name"',
            'name="status"',
            'name="floor"',
            'name="sort"',
            'id="borrowFormModal"',
            'id="modal-book-barcode"',
            'id="modal-book-title"',
            'id="borrow-error"',
            'Borrow Now',
        ]);
        $script = file_get_contents(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'student-search.js');
        self::assertIsString($script);
        foreach (['book-card-shell', 'book-face-front', 'book-face-back', 'body.append("action", "borrow")', 'body.append("csrf"'] as $marker) {
            self::assertStringContainsString($marker, $script);
        }
    }

    public function testCanonicalStudentSearchUsesFeatureOwnedModule(): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'features' . DIRECTORY_SEPARATOR . 'student' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'search' . DIRECTORY_SEPARATOR . 'search.html';
        self::assertFileExists($path);
        $html = file_get_contents($path);
        self::assertIsString($html);
        self::assertStringContainsString('data-app-page="student-search"', $html);
        self::assertStringContainsString('frontend/features/student/pages/search/student-search.page.js', $html);
        foreach (['Book Catalog', 'id="searchForm"', 'name="category_name"', 'name="status"', 'name="floor"', 'name="sort"', 'id="borrowFormModal"', 'Borrow Now'] as $marker) {
            self::assertStringContainsString($marker, $html, "Missing canonical search marker: {$marker}");
        }
    }

    public function testHistoryPagePreservesBorrowingRecordTable(): void
    {
        $this->assertPageContains('student-history.html', [
            'Your complete borrowing record.',
            '<th>Code</th>',
            '<th>Returned</th>',
            '<th>Fine</th>',
            'No borrowing history yet.',
        ]);
    }

    public function testTeacherDashboardPreservesTeacherSpecificPanelsAndDueDate(): void
    {
        $this->assertPageContains('teacher-dashboard.html', [
            'Teacher Card',
            'Reading Velocity',
            'Fine Risk Prediction',
            'Subject Expertise',
            'Teacher Profile',
            'Smart Insights',
            'name="due_date"',
            'Teachers can borrow books for up to',
            'data-scan-target="book_barcode"',
        ]);
    }

    public function testReceiptPagePreservesPrintableReceiptContract(): void
    {
        $this->assertPageContains('receipt.html', [
            'Borrowing Receipt',
            'Scan2Borrow Library',
            'class="no-print',
            'window.print()',
        ]);
        $script = file_get_contents(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'receipt.js');
        self::assertIsString($script);
        foreach (['Transaction', 'Accession Number', 'Validity of the Book'] as $marker) {
            self::assertStringContainsString($marker, $script);
        }
    }

    public function testCanonicalStudentHistoryAndReceiptUseFeatureModules(): void
    {
        $pages = [
            ['history/history.html', 'student-history', 'frontend/features/student/pages/history/student-history.page.js', ['Your complete borrowing record.', 'No borrowing history yet.']],
            ['receipt/receipt.html', 'student-receipt', 'frontend/features/student/pages/receipt/receipt.page.js', ['Borrowing Receipt', 'class="no-print', 'window.print()']],
        ];
        foreach ($pages as [$relativePath, $pageName, $modulePath, $markers]) {
            $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'features' . DIRECTORY_SEPARATOR . 'student' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . $relativePath;
            self::assertFileExists($path);
            $html = file_get_contents($path);
            self::assertIsString($html);
            self::assertStringContainsString('data-app-page="' . $pageName . '"', $html);
            self::assertStringContainsString($modulePath, $html);
            foreach ($markers as $marker) {
                self::assertStringContainsString($marker, $html, "Missing canonical marker: {$marker}");
            }
        }
    }
}
