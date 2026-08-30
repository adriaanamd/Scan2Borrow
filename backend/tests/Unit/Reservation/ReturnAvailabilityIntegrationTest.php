<?php

declare(strict_types=1);

namespace Tests\Unit\Reservation;

use App\Application\Services\ClockInterface;
use App\Application\Services\ReservationAvailabilityInterface;
use App\Application\Services\ReturnService;
use App\Domain\Borrowing\LoanRecord;
use App\Infrastructure\Persistence\ReturnRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ReturnAvailabilityIntegrationTest extends TestCase
{
    public function testNormalizedCopyReturnAdvancesItsTitleQueue(): void
    {
        $repository = $this->createMock(ReturnRepositoryInterface::class);
        $availability = $this->createMock(ReservationAvailabilityInterface::class);
        $repository->expects(self::once())->method('activeByTransaction')->with(1, 'COPY-11')->willReturn([]);
        $repository->expects(self::once())->method('findBookByBarcode')->with('COPY-11')->willReturn(['id' => 11, 'title' => 'Clean Code']);
        $repository->expects(self::once())->method('activeByBook')->with(1, 11)->willReturn(new LoanRecord(20, 11, 'TXN-1', new DateTimeImmutable('2026-08-29')));
        $repository->expects(self::once())->method('completeReturn')->with(20, 11, 0.0);
        $repository->expects(self::once())->method('titleIdForBook')->with(11)->willReturn(4);
        $availability->expects(self::once())->method('advance')->with(4, 11, new DateTimeImmutable('2026-08-30 10:00:00'));

        $service = new ReturnService($repository, new ReturnIntegrationClock(), 5.0, $availability);

        self::assertTrue($service->return(1, 'COPY-11')->successful());
    }
}

final class ReturnIntegrationClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-30 10:00:00');
    }
}
