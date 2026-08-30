<?php

declare(strict_types=1);

namespace Tests\Unit\Renewal;

use App\Application\DTO\RenewalRequest;
use App\Application\Services\RenewalEligibilityInterface;
use App\Application\Services\RenewalService;
use App\Application\Services\RenewalEligibilityResult;
use App\Domain\Renewal\RenewalLoanSnapshot;
use App\Domain\Renewal\RenewalRecord;
use App\Infrastructure\Persistence\RenewalRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RenewalServiceTest extends TestCase
{
    public function testEligibleBorrowerRequestIsPersistedWithOneStandardPeriod(): void
    {
        $loan = new RenewalLoanSnapshot(88, 7, 4, 'Clean Code', new DateTimeImmutable('2026-08-30'));
        $policy = $this->createMock(RenewalEligibilityInterface::class);
        $policy->expects(self::once())->method('check')->with(7, 88)->willReturn(
            RenewalEligibilityResult::allowed($loan, new DateTimeImmutable('2026-09-06')),
        );
        $record = RenewalRecord::fromRow([
            'id' => 12, 'loan_id' => 88, 'user_id' => 7, 'title' => 'Clean Code', 'status' => 'pending',
            'original_due_date' => '2026-08-30', 'requested_due_date' => '2026-09-06',
        ]);
        $repository = $this->createMock(RenewalRepositoryInterface::class);
        $repository->expects(self::once())->method('create')->with(88, 7, self::isInstanceOf(DateTimeImmutable::class), new DateTimeImmutable('2026-09-06'), 'Project deadline')->willReturn($record);

        $result = (new RenewalService($policy, $repository))->request(new RenewalRequest(7, 88, 'Project deadline'));

        self::assertTrue($result->successful());
        self::assertSame('Renewal request submitted for librarian approval.', $result->message());
    }

    public function testIneligibleRequestNeverReachesRepository(): void
    {
        $policy = $this->createMock(RenewalEligibilityInterface::class);
        $policy->method('check')->willReturn(RenewalEligibilityResult::denied('This loan is no longer active.'));
        $repository = $this->createMock(RenewalRepositoryInterface::class);
        $repository->expects(self::never())->method('create');

        $result = (new RenewalService($policy, $repository))->request(new RenewalRequest(7, 88));

        self::assertFalse($result->successful());
        self::assertSame('This loan is no longer active.', $result->message());
    }
}
