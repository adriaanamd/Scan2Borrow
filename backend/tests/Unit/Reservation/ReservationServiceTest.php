<?php

declare(strict_types=1);

namespace Tests\Unit\Reservation;

use App\Application\DTO\HoldActionRequest;
use App\Application\DTO\JoinHoldRequest;
use App\Application\Services\ReservationService;
use App\Domain\Reservation\HoldRecord;
use App\Domain\Reservation\HoldStatus;
use App\Infrastructure\Persistence\HoldRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class ReservationServiceTest extends TestCase
{
    public function testDuplicateActiveReservationIsRejectedBeforeInsert(): void
    {
        $repository = $this->createMock(HoldRepositoryInterface::class);
        $existing = HoldRecord::fromRow([
            'id' => 3, 'user_id' => 7, 'title_id' => 4, 'title' => 'Clean Code', 'status' => 'queued',
        ]);
        $repository->expects(self::once())->method('findActiveForUserTitle')->willReturn($existing);
        $repository->expects(self::never())->method('join');
        $service = new ReservationService($repository);

        $result = $service->join(new JoinHoldRequest(7, 4));

        self::assertFalse($result->successful());
        self::assertSame('You are already in the reservation queue for this title.', $result->message());
    }

    public function testJoinReturnsQueuePositionFromRepository(): void
    {
        $repository = $this->createMock(HoldRepositoryInterface::class);
        $record = HoldRecord::fromRow([
            'id' => 3, 'user_id' => 7, 'title_id' => 4, 'title' => 'Clean Code', 'status' => 'queued', 'queue_position' => 2,
        ]);
        $repository->expects(self::once())->method('findActiveForUserTitle')->willReturn(null);
        $repository->expects(self::once())->method('join')->with(7, 4)->willReturn($record);
        $service = new ReservationService($repository);

        $result = $service->join(new JoinHoldRequest(7, 4));

        self::assertTrue($result->successful());
        self::assertSame(2, $result->record()?->queuePosition());
        self::assertSame('You joined the queue for "Clean Code".', $result->message());
    }

    public function testCancelReportsConflictWhenRepositoryCannotChangeTheHold(): void
    {
        $repository = $this->createMock(HoldRepositoryInterface::class);
        $repository->expects(self::once())->method('cancel')->with(12, 7)->willReturn(false);
        $service = new ReservationService($repository);

        $result = $service->cancel(new HoldActionRequest(7, 12, 'cancel'));

        self::assertFalse($result->successful());
        self::assertSame('This reservation is no longer available for cancellation.', $result->message());
    }

    public function testClaimRequiresAnOfferedHoldReturnedByRepository(): void
    {
        $repository = $this->createMock(HoldRepositoryInterface::class);
        $record = HoldRecord::fromRow([
            'id' => 12, 'user_id' => 7, 'title_id' => 4, 'title' => 'Clean Code', 'status' => HoldStatus::CLAIMED->value,
        ]);
        $repository->expects(self::once())->method('claim')->with(12, 7)->willReturn($record);
        $service = new ReservationService($repository);

        $result = $service->claim(new HoldActionRequest(7, 12, 'claim'));

        self::assertTrue($result->successful());
        self::assertSame('Your hold for "Clean Code" is claimed. Please visit the library desk to borrow it.', $result->message());
    }
}
