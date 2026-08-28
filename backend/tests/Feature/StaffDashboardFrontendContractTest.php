<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class StaffDashboardFrontendContractTest extends TestCase
{
    public function testDashboardControllerRendersOverviewPayloadWithVanillaMethods(): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'staff.js';
        $source = (string) file_get_contents($path);

        foreach ([
            'renderOverview',
            'renderActivity',
            'renderCategoryTrend',
            'renderStatus',
            'renderTopBorrowers',
            'renderCategories',
            'renderGenres',
            'renderRecentActivity',
            'borrowing_activity',
            'category_borrowing_activity',
            'loan_status',
            'top_borrowers',
            'category_breakdown',
            'top_genres',
            'recent_activity',
            'this.renderOverview(data.overview || {}, data.recent || [])',
            'ring.style.background = ""',
        ] as $marker) {
            self::assertStringContainsString($marker, $source, $marker . ' is missing from staff.js.');
        }

        self::assertStringNotContainsString('chart.js', strtolower($source));
        self::assertStringNotContainsString('echarts', strtolower($source));
        self::assertStringNotContainsString('plotly', strtolower($source));
    }

    public function testTopBorrowersKeepsViewAllControlForShortRankings(): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'staff.js';
        $source = (string) file_get_contents($path);

        self::assertStringContainsString('toggle.hidden = !rows.length;', $source);
    }

    public function testTopBorrowersViewAllOpensFullRankingModal(): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'staff.js';
        $source = (string) file_get_contents($path);

        self::assertStringContainsString('overview-borrowers-modal', $source);
        self::assertStringContainsString('renderTopBorrowersModal', $source);
        self::assertStringContainsString('bootstrap.Modal.getOrCreateInstance', $source);
    }

    public function testPendingApprovalCountUpdatesTheVisibleDashboardControl(): void
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'staff.js';
        $source = (string) file_get_contents($path);

        self::assertStringContainsString('pending-approvals-count', $source);
        self::assertStringContainsString('pendingRows.length', $source);
    }

}
