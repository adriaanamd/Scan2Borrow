<?php

declare(strict_types=1);

namespace Tests\Unit\Renewal;

use App\Application\DTO\RenewalDecisionRequest;
use App\Application\Services\ClockInterface;
use App\Application\Services\RenewalApprovalService;
use App\Domain\Renewal\RenewalRecord;
use App\Infrastructure\Persistence\CirculationNotificationRepositoryInterface;
use App\Infrastructure\Persistence\RenewalRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RenewalApprovalServiceTest extends TestCase
{
    public function testApprovalUpdatesTheLoanAndNotifiesTheBorrower(): void
    {
        $record = RenewalRecord::fromRow([
            'id' => 12, 'loan_id' => 88, 'user_id' => 7, 'title' => 'Clean Code', 'status' => 'approved',
            'original_due_date' => '2026-08-30', 'requested_due_date' => '2026-09-06',
        ]);
        $repository = $this->createMock(RenewalRepositoryInterface::class);
        $repository->expects(self::once())->method('approve')->with(12, 2, 'Approved once.', self::isInstanceOf(DateTimeImmutable::class))->willReturn($record);
        $notifications = $this->createMock(CirculationNotificationRepositoryInterface::class);
        $notifications->expects(self::once())->method('notifyBorrower')->with(7, 'renewal_approved', 'Renewal approved', self::stringContains('Clean Code'), 12);
        $clock = new class implements ClockInterface { public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-08-30 12:00:00'); } };

        $result = (new RenewalApprovalService($repository, $notifications, $clock))->decide(new RenewalDecisionRequest(12, 2, 'approve', 'Approved once.'));

        self::assertTrue($result->successful());
        self::assertSame('Renewal approved through 2026-09-06.', $result->message());
    }

    public function testDecisionConflictIsReportedWithoutNotification(): void
    {
        $repository = $this->createMock(RenewalRepositoryInterface::class);
        $repository->method('reject')->willReturn(null);
        $notifications = $this->createMock(CirculationNotificationRepositoryInterface::class);
        $notifications->expects(self::never())->method('notifyBorrower');
        $clock = $this->createMock(ClockInterface::class);

        $result = (new RenewalApprovalService($repository, $notifications, $clock))->decide(new RenewalDecisionRequest(12, 2, 'reject'));

        self::assertFalse($result->successful());
        self::assertSame('This renewal is no longer awaiting a decision.', $result->message());
    }
}
