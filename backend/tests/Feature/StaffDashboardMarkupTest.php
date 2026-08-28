<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class StaffDashboardMarkupTest extends TestCase
{
    public function testDashboardContainsOverviewHooksAndStylesheet(): void
    {
        $dashboard = $this->page('staff-dashboard.html');

        foreach ([
            'data-overview',
            'overview-activity',
            'overview-category-trend',
            'overview-status',
            'overview-status-ring',
            'overview-status-legend',
            'overview-borrowers-list',
            'overview-categories',
            'overview-genres',
            'overview-recent',
            'overview-borrowers-toggle',
            'overview-borrowers-modal',
            'overview-borrowers-modal-list',
            'admin-overview.css',
        ] as $marker) {
            self::assertStringContainsString($marker, $dashboard, $marker . ' is missing from the dashboard.');
        }
    }

    public function testDashboardExposesPendingApprovalControl(): void
    {
        $dashboard = $this->page('staff-dashboard.html');

        self::assertStringContainsString('id="pending-approvals-trigger"', $dashboard);
        self::assertStringContainsString('data-bs-target="#approvalModal"', $dashboard);
        self::assertStringContainsString('id="pending-approvals-count"', $dashboard);
        self::assertStringContainsString('>Pending Approvals<', $dashboard);
    }

    public function testMainStaffDashboardExposesAdminOnlyApiDocsNavigation(): void
    {
        $dashboard = $this->page('staff-dashboard.html');

        self::assertStringContainsString('data-admin-api-docs', $dashboard);
        self::assertStringContainsString('href="/scan2borrow/admin/api-docs"', $dashboard);
        self::assertStringContainsString('API Docs', $dashboard);
    }

    public function testEveryStaffPageExposesTheAdminOnlyApiDocsNavigation(): void
    {
        foreach ([
            'staff-books.html',
            'staff-borrower.html',
            'staff-dashboard.html',
            'staff-guest-requests.html',
            'staff-notify.html',
            'staff-overdue.html',
            'staff-reports.html',
            'staff-students.html',
        ] as $filename) {
            $page = $this->page($filename);

            self::assertStringContainsString('data-admin-api-docs', $page, $filename);
            self::assertStringContainsString('href="/scan2borrow/admin/api-docs"', $page, $filename);
        }
    }

    public function testOverviewHooksDoNotLeakIntoOtherAdminPages(): void
    {
        foreach (['staff-books.html', 'staff-reports.html', 'admin-staff.html'] as $filename) {
            self::assertStringNotContainsString('data-overview', $this->page($filename), $filename . ' must not render dashboard Overview markup.');
        }
    }

    public function testOverviewPieChartsUseExpandedPanelSpace(): void
    {
        $styles = $this->styles();

        self::assertStringContainsString('grid-template-columns: 12rem minmax(0, 1fr);', $styles);
        self::assertStringContainsString('height: 12rem;', $styles);
        self::assertStringContainsString('width: 12rem;', $styles);
    }

    private function page(string $filename): string
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . $filename;
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }

    private function styles(): string
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'admin-overview.css';
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }
}
