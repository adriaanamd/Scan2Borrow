<?php

declare(strict_types=1);

namespace Tests\Unit\Borrowing;

use App\Application\Services\ClockInterface;
use App\Application\Services\ReservationAvailabilityInterface;
use App\Application\Services\ReturnApprovalService;
use App\Infrastructure\Persistence\ReturnApprovalRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ReturnApprovalServiceTest extends TestCase
{
    /** @var ReturnApprovalRepositoryInterface&MockObject */
    private ReturnApprovalRepositoryInterface $repository;

    /** @var ReservationAvailabilityInterface&MockObject */
    private ReservationAvailabilityInterface $availability;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ReturnApprovalRepositoryInterface::class);
        $this->availability = $this->createMock(ReservationAvailabilityInterface::class);
    }

    public function testApprovalComputesFinePassesStaffActorAndAdvancesAvailability(): void
    {
        $pending = [
            'type' => 'borrower_item',
            'id' => 8,
            'copy_id' => 4,
            'title_id' => 3,
            'due_date' => '2026-08-25',
        ];
        $this->repository->expects(self::once())->method('findPending')->with('borrower_item', 8)->willReturn($pending);
        $this->repository->expects(self::once())->method('decide')->with('borrower_item', 8, 'approve', 19, 10.0, '')->willReturn(true);
        $this->availability->expects(self::once())->method('advance')->with(3, 4, new DateTimeImmutable('2026-08-28 10:00:00'));

        $result = $this->service()->decide('borrower_item', 8, 'approve', 19, '');

        self::assertTrue($result->successful());
        self::assertSame('Return approved. The book is now available.', $result->message());
    }

    public function testRejectionRequiresAReasonBeforeReadingTheRequest(): void
    {
        $this->repository->expects(self::never())->method('findPending');

        $result = $this->service()->decide('borrower_item', 8, 'reject', 19, '  ');

        self::assertFalse($result->successful());
        self::assertSame('A reason is required to reject a return.', $result->message());
    }

    public function testUnknownReturnAndStaleDecisionAreSafeFailures(): void
    {
        $this->repository->expects(self::exactly(2))->method('findPending')->willReturnOnConsecutiveCalls(null, [
            'type' => 'legacy_borrowing', 'id' => 10, 'due_date' => '2026-08-28',
        ]);
        $this->repository->expects(self::once())->method('decide')->with('legacy_borrowing', 10, 'approve', 19, 0.0, '')->willReturn(false);

        $unknown = $this->service()->decide('borrower_item', 8, 'approve', 19, '');
        $stale = $this->service()->decide('legacy_borrowing', 10, 'approve', 19, '');

        self::assertSame('Return request not found.', $unknown->message());
        self::assertSame('Return request is no longer pending.', $stale->message());
    }

    public function testGuestApprovalDoesNotApplyBorrowerFine(): void
    {
        $this->repository->expects(self::once())->method('findPending')->with('guest', 22)->willReturn([
            'type' => 'guest', 'id' => 22, 'due_date' => '2026-08-01',
        ]);
        $this->repository->expects(self::once())->method('decide')->with('guest', 22, 'approve', 19, 0.0, '')->willReturn(true);
        $this->availability->expects(self::never())->method('advance');

        $result = $this->service()->decide('guest', 22, 'approve', 19, '');

        self::assertTrue($result->successful());
    }

    private function service(): ReturnApprovalService
    {
        return new ReturnApprovalService(
            $this->repository,
            new FixedReturnApprovalClock(),
            5.0,
            $this->availability,
        );
    }
}

final class FixedReturnApprovalClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-28 10:00:00');
    }
}
