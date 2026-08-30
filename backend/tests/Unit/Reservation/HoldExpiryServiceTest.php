<?php

declare(strict_types=1);

namespace Tests\Unit\Reservation;

use App\Application\Services\ClockInterface;
use App\Application\Services\HoldExpiryService;
use App\Application\Services\ReservationAvailabilityInterface;
use App\Domain\Reservation\HoldRecord;
use App\Infrastructure\Persistence\HoldRepositoryInterface;
use App\Infrastructure\Persistence\ReservationCopyRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class HoldExpiryServiceTest extends TestCase
{
    public function testExpiredOffersAdvanceEachReleasedCopyToTheNextQueueEntry(): void
    {
        $holds = $this->createMock(HoldRepositoryInterface::class);
        $holds->expects(self::once())->method('expireOffers')->willReturn([4]);
        $copies = $this->createMock(ReservationCopyRepositoryInterface::class);
        $copies->expects(self::once())->method('availableCopyForTitle')->with(4)->willReturn(11);
        $availability = $this->createMock(ReservationAvailabilityInterface::class);
        $availability->expects(self::once())->method('advance')->with(4, 11, self::isInstanceOf(DateTimeImmutable::class))->willReturn(
            HoldRecord::fromRow(['id' => 9, 'user_id' => 7, 'title_id' => 4, 'title' => 'Clean Code', 'status' => 'offered']),
        );
        $clock = new class implements ClockInterface {
            public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-08-30 10:00:00'); }
        };

        $expired = (new HoldExpiryService($holds, $copies, $availability, $clock))->run();

        self::assertSame(1, $expired);
    }
}
