<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Services\GuestApprovalService;
use App\Infrastructure\Persistence\GuestApprovalRepositoryInterface;
use App\Infrastructure\Persistence\VisitorNotificationRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GuestApprovalServiceTest extends TestCase
{
    /** @var GuestApprovalRepositoryInterface&MockObject */
    private GuestApprovalRepositoryInterface $requests;

    /** @var VisitorNotificationRepositoryInterface&MockObject */
    private VisitorNotificationRepositoryInterface $notifications;

    protected function setUp(): void
    {
        $this->requests = $this->createMock(GuestApprovalRepositoryInterface::class);
        $this->notifications = $this->createMock(VisitorNotificationRepositoryInterface::class);
    }

    public function testApproveReleasesRequestAndNotifiesVisitor(): void
    {
        $this->requests->method('findPending')->willReturn([
            'id' => 41, 'visitor_id' => 7, 'title' => 'Clean Code', 'due_date' => '2026-09-04',
        ]);
        $this->requests->expects(self::once())->method('approve')->with(41, 'Keep the cover visible.');
        $this->notifications->expects(self::once())->method('notifyVisitor')->with(7, 'Borrow request approved', self::stringContains('Clean Code'));

        $result = (new GuestApprovalService($this->requests, $this->notifications))->approve(41, 'Keep the cover visible.');

        self::assertTrue($result->isSuccessful());
        self::assertSame('Released', $result->status());
        self::assertSame("Approved \u{2014} the guest can now view and print their receipt.", $result->message());
    }

    public function testRejectRequiresReasonAndPreservesRejectedStatus(): void
    {
        $service = new GuestApprovalService($this->requests, $this->notifications);
        $missingReason = $service->reject(41, '');
        self::assertFalse($missingReason->isSuccessful());
        self::assertSame('A reason is required to reject a request.', $missingReason->message());

        $this->requests->method('findPending')->willReturn(['id' => 41, 'visitor_id' => 7, 'title' => 'Clean Code']);
        $this->requests->expects(self::once())->method('reject')->with(41, 'Duplicate request.');
        $this->notifications->expects(self::once())->method('notifyVisitor')->with(7, 'Borrow request rejected', self::stringContains('Duplicate request.'));

        $result = $service->reject(41, 'Duplicate request.');

        self::assertTrue($result->isSuccessful());
        self::assertSame('Rejected', $result->status());
        self::assertSame('Request rejected.', $result->message());
    }

    public function testReviewReportsMissingRequestWithoutMutatingState(): void
    {
        $this->requests->method('findPending')->willReturn(null);
        $this->requests->expects(self::never())->method('approve');
        $this->requests->expects(self::never())->method('reject');

        $result = (new GuestApprovalService($this->requests, $this->notifications))->approve(404, '');

        self::assertFalse($result->isSuccessful());
        self::assertSame('Request not found.', $result->message());
    }
}
