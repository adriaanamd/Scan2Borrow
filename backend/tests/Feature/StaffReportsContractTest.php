<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class StaffReportsContractTest extends TestCase
{
    public function testReportsPageHasOneDataDrivenPrintableReportDocument(): void
    {
        $source = $this->read('frontend/pages/staff-reports.html');

        foreach ([
            'id="staff-report-document"',
            'id="staff-report-title"',
            'id="staff-report-period"',
            'id="staff-report-generated"',
            'id="staff-report-table"',
        ] as $marker) {
            self::assertStringContainsString($marker, $source, $marker . ' is missing from the reports page.');
        }

        self::assertSame(1, substr_count(strtolower($source), '<!doctype html>'));
        self::assertStringNotContainsString('onload="window.print()"', $source);
        self::assertStringNotContainsString('legacy-print-template', $source);
    }

    public function testReportsPrintsOnlyAfterTheRealReportHasRendered(): void
    {
        $source = $this->read('frontend/assets/js/pages/staff.js');

        self::assertStringContainsString('renderReport(report, from, to)', $source);
        self::assertStringContainsString('await this.printReportWhenReady();', $source);
        self::assertStringContainsString('requestAnimationFrame', $source);
        self::assertStringContainsString('staff-report-document', $source);
    }

    private function read(string $relativePath): string
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }
}
