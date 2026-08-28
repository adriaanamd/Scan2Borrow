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
            'admin-overview.css',
        ] as $marker) {
            self::assertStringContainsString($marker, $dashboard, $marker . ' is missing from the dashboard.');
        }
    }

    public function testOverviewHooksDoNotLeakIntoOtherAdminPages(): void
    {
        foreach (['staff-books.html', 'staff-reports.html', 'admin-staff.html'] as $filename) {
            self::assertStringNotContainsString('data-overview', $this->page($filename), $filename . ' must not render dashboard Overview markup.');
        }
    }

    private function page(string $filename): string
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . $filename;
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }
}
