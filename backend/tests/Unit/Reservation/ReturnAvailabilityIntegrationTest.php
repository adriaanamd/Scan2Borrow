<?php

declare(strict_types=1);

namespace Tests\Unit\Reservation;

use App\Application\Services\ReturnService;
use App\Domain\Borrowing\LoanRecord;
use App\Infrastructure\Persistence\ReturnRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ReturnAvailabilityIntegrationTest extends TestCase
{
    public function testNormalizedCopyReturnWaitsForStaffApprovalBeforeAdvancingItsTitleQueue(): void
    {
        $repository = $this->createMock(ReturnRepositoryInterface::class);
        $repository->expects(self::once())->method('activeByTransaction')->with(1, 'COPY-11')->willReturn([]);
        $repository->expects(self::once())->method('findBookByBarcode')->with('COPY-11')->willReturn(['id' => 11, 'title' => 'Clean Code']);
        $repository->expects(self::once())->method('activeByBook')->with(1, 11)->willReturn(new LoanRecord(20, 11, 'TXN-1', new DateTimeImmutable('2026-08-29')));
        $repository->expects(self::once())->method('requestReturn')->with(20)->willReturn(true);

        $service = new ReturnService($repository);

        self::assertTrue($service->return(1, 'COPY-11')->successful());
    }
}
