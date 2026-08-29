<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Services\GuestPortalService;
use App\Infrastructure\Persistence\GuestPortalRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GuestPortalContractTest extends TestCase
{
    /** @var GuestPortalRepositoryInterface&MockObject */
    private GuestPortalRepositoryInterface $portal;

    protected function setUp(): void
    {
        $this->portal = $this->createMock(GuestPortalRepositoryInterface::class);
    }

    public function testDashboardSummaryPreservesVisitorPayloadAndNotifications(): void
    {
        $this->portal->expects(self::once())->method('dashboardSummary')->with(7)->willReturn([
            'active' => 1,
            'returned' => 2,
            'overdue' => 0,
            'total' => 3,
        ]);
        $this->portal->expects(self::once())->method('notifications')->with(7)->willReturn([
            ['id' => 5, 'title' => 'Borrow request approved', 'message' => 'Ready for release.', 'is_read' => 0],
        ]);

        $data = (new GuestPortalService($this->portal))->dashboard(7);

        self::assertSame(1, $data['summary']['active']);
        self::assertSame(5, $data['notifications'][0]['id']);
        self::assertSame('Borrow request approved', $data['notifications'][0]['title']);
    }

    public function testBrowsePreservesFiltersAndHistoryPreservesDateAndStatusFilters(): void
    {
        $filters = ['search' => 'clean', 'category_name' => 'Computer Science', 'floor' => '2', 'status' => 'Available'];
        $this->portal->expects(self::once())->method('browse')->with($filters)->willReturn(['books' => [], 'total' => 0]);
        $this->portal->expects(self::once())->method('history')->with(7, 'returned', '2026-01-01', '2026-01-31')->willReturn([]);
        $service = new GuestPortalService($this->portal);

        self::assertSame(['books' => [], 'total' => 0], $service->browse($filters));
        self::assertSame([], $service->history(7, 'returned', '2026-01-01', '2026-01-31'));
    }

    public function testReceiptRequiresVisitorOwnership(): void
    {
        $this->portal->expects(self::once())->method('receipt')->with(7, 12)->willReturn(null);

        $receipt = (new GuestPortalService($this->portal))->receipt(7, 12);

        self::assertNull($receipt);
    }

    public function testCanonicalDashboardAndProfileTemplatesKeepGuestContext(): void
    {
        foreach ([
            ['dashboard/dashboard.html', 'guest-dashboard', ['visitor-name', 'visit-history', 'security-log']],
            ['profile/profile.html', 'guest-profile', ['guest-profile-form', 'purpose_other', 'Save Changes']],
        ] as [$relativePath, $pageName, $markers]) {
            $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'features' . DIRECTORY_SEPARATOR . 'guest' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            self::assertFileExists($path);
            $html = file_get_contents($path);
            self::assertIsString($html);
            self::assertStringContainsString('data-app-page="' . $pageName . '"', $html);
            foreach ($markers as $marker) {
                self::assertStringContainsString($marker, $html);
            }
        }
    }
}
