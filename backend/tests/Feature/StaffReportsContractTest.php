<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Tests\Support\FrontendPagePaths;

final class StaffReportsContractTest extends TestCase
{
    public function testReportsPageHasOneDataDrivenPrintableReportDocument(): void
    {
        $source = file_get_contents(FrontendPagePaths::path('staff-reports'));
        self::assertIsString($source);

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
        $source = $this->read('frontend/features/staff/pages/reports/reports.page.js');

        self::assertStringContainsString('render(response.data?.report || {}, filters.from, filters.to)', $source);
        self::assertStringContainsString('await this.printReportWhenReady();', $source);
        self::assertStringContainsString('requestAnimationFrame', $source);
        self::assertStringContainsString('staff-report-document', $source);
    }

    public function testFeatureOwnedReportAndNotificationServicesPreserveContracts(): void
    {
        $root = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'features' . DIRECTORY_SEPARATOR . 'staff' . DIRECTORY_SEPARATOR . 'services';
        $report = file_get_contents($root . DIRECTORY_SEPARATOR . 'report.service.js');
        $notification = file_get_contents($root . DIRECTORY_SEPARATOR . 'notification.service.js');
        self::assertIsString($report);
        self::assertIsString($notification);
        foreach (['/api/staff/reports', '/api/staff/reports/export', "print: '1'", 'type', 'from', 'to'] as $marker) self::assertStringContainsString($marker, $report);
        foreach (['/api/staff/notifications', '/api/staff/notifications/viewed', '/api/staff/notify', 'notification_id', 'notification_type'] as $marker) self::assertStringContainsString($marker, $notification);
    }

    public function testCanonicalReportsAndOverduePagesUseFeatureEntries(): void
    {
        foreach ([
            ['reports/reports.html', 'staff-reports', 'reports/entry.js', ['staff-report-document', 'staff-report-table']],
            ['overdue/overdue.html', 'staff-overdue', 'overdue/entry.js', ['No overdue books', 'table']],
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

    public function testCanonicalNotificationAndGuestRequestPagesUseFeatureEntries(): void
    {
        foreach ([
            ['notify/notify.html', 'staff-notify', 'notify/entry.js', ['notify-email', 'notify-contact', 'send-email']],
            ['guest-requests/guest-requests.html', 'staff-guest-requests', 'guest-requests/entry.js', ['reviewModal', 'review-notes']],
        ] as [$relativePath, $pageName, $entry, $markers]) {
            $html = $this->read('frontend/features/staff/pages/' . $relativePath);
            self::assertStringContainsString('data-app-page="' . $pageName . '"', $html);
            self::assertStringContainsString('frontend/features/staff/pages/' . $entry, $html);
            foreach ($markers as $marker) self::assertStringContainsString($marker, $html);
        }
    }

    private function read(string $relativePath): string
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }
}
