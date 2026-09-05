<?php

declare(strict_types=1);

namespace Tests\Unit\Reservation;

use App\Application\Services\ReservationAvailabilityService;
use App\Domain\Reservation\HoldRecord;
use App\Infrastructure\Persistence\CirculationNotificationRepositoryInterface;
use App\Infrastructure\Persistence\HoldRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ReservationAvailabilityServiceTest extends TestCase
{
    public function testAdvanceOffersTheOldestQueuedBorrowerForTwentyFourHours(): void
    {
        $holds = $this->createMock(HoldRepositoryInterface::class);
        $notifications = $this->createMock(CirculationNotificationRepositoryInterface::class);
        $record = HoldRecord::fromRow([
            'id' => 12, 'user_id' => 7, 'title_id' => 4, 'title' => 'Clean Code', 'status' => 'queued', 'queue_position' => 1,
        ]);
        $now = new DateTimeImmutable('2026-08-30 10:00:00');
        $holds->expects(self::once())->method('nextEligibleQueued')->with(4)->willReturn($record);
        $holds->expects(self::once())->method('offer')->with(12, 11, $now, $now->modify('+24 hours'))->willReturn(true);
        $holds->expects(self::once())->method('find')->with(12)->willReturn(HoldRecord::fromRow([
            'id' => 12, 'user_id' => 7, 'title_id' => 4, 'title' => 'Clean Code', 'status' => 'offered', 'queue_position' => 1,
        ]));
        $notifications->expects(self::once())->method('notifyBorrower')->with(
            7,
            'hold_available',
            'Hold ready: Clean Code',
            'Your hold for "Clean Code" is ready to claim until Aug 31, 2026 10:00 AM.',
            12,
        );

        $result = (new ReservationAvailabilityService($holds, $notifications))->advance(4, 11, $now);

        self::assertNotNull($result);
        self::assertSame('offered', $result->status()->value);
    }

    public function testAdvanceDoesNothingWhenQueueIsEmptyOrOfferAlreadyChanged(): void
    {
        $holds = $this->createMock(HoldRepositoryInterface::class);
        $notifications = $this->createMock(CirculationNotificationRepositoryInterface::class);
        $holds->expects(self::once())->method('nextEligibleQueued')->with(4)->willReturn(null);
        $holds->expects(self::never())->method('offer');
        $notifications->expects(self::never())->method('notifyBorrower');

        self::assertNull((new ReservationAvailabilityService($holds, $notifications))->advance(4, 11, new DateTimeImmutable('2026-08-30 10:00:00')));
    }

    public function testAdvanceReportsWhenQueuedOfferCannotBePersisted(): void
    {
        $holds = $this->createMock(HoldRepositoryInterface::class);
        $notifications = $this->createMock(CirculationNotificationRepositoryInterface::class);
        $record = HoldRecord::fromRow([
            'id' => 12, 'user_id' => 7, 'title_id' => 4, 'title' => 'Clean Code', 'status' => 'queued', 'queue_position' => 1,
        ]);
        $holds->expects(self::once())->method('nextEligibleQueued')->with(4)->willReturn($record);
        $holds->expects(self::once())->method('offer')->willReturn(false);
        $notifications->expects(self::never())->method('notifyBorrower');

        $this->expectException(\RuntimeException::class);
        (new ReservationAvailabilityService($holds, $notifications))->advance(4, 11, new DateTimeImmutable('2026-08-30 10:00:00'));
    }
}
