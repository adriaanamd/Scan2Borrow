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
            'renderStatus',
            'renderTopBorrowers',
            'borrowing_activity',
            'loan_status',
            'top_borrowers',
            'this.renderOverview(data.overview || {})',
            'ring.style.background = ""',
        ] as $marker) {
            self::assertStringContainsString($marker, $source, $marker . ' is missing from staff.js.');
        }

        self::assertStringNotContainsString('chart.js', strtolower($source));
        self::assertStringNotContainsString('echarts', strtolower($source));
        self::assertStringNotContainsString('plotly', strtolower($source));
    }
}
