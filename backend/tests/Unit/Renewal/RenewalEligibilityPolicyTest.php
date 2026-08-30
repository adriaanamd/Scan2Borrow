<?php

declare(strict_types=1);

namespace Tests\Unit\Renewal;

use App\Application\Services\RenewalEligibilityPolicy;
use App\Domain\Renewal\RenewalLoanSnapshot;
use App\Infrastructure\Persistence\RenewalEligibilityRepositoryInterface;
use App\Infrastructure\Persistence\RenewalRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RenewalEligibilityPolicyTest extends TestCase
{
    public function testActiveHoldBlocksRenewalBeforeARequestIsCreated(): void
    {
        $source = $this->createMock(RenewalEligibilityRepositoryInterface::class);
        $source->method('loanForRenewal')->willReturn(new RenewalLoanSnapshot(88, 7, 4, 'Clean Code', new DateTimeImmutable('2026-08-30')));
        $source->method('activeHoldCountForTitle')->with(4)->willReturn(1);
        $renewals = $this->createMock(RenewalRepositoryInterface::class);

        $result = (new RenewalEligibilityPolicy($source, $renewals, 7))->check(7, 88);

        self::assertFalse($result->eligible());
        self::assertSame('This title has an active hold and cannot be renewed.', $result->message());
    }

    public function testGoodStandingLoanCanReceiveOneStandardRenewal(): void
    {
        $source = $this->createMock(RenewalEligibilityRepositoryInterface::class);
        $source->method('loanForRenewal')->willReturn(new RenewalLoanSnapshot(88, 7, 4, 'Clean Code', new DateTimeImmutable('2026-08-30')));
        $source->method('activeHoldCountForTitle')->willReturn(0);
        $source->method('accountInGoodStanding')->with(7)->willReturn(true);
        $renewals = $this->createMock(RenewalRepositoryInterface::class);
        $renewals->method('hasApprovedForLoan')->willReturn(false);
        $renewals->method('hasPendingForLoan')->willReturn(false);

        $result = (new RenewalEligibilityPolicy($source, $renewals, 7))->check(7, 88);

        self::assertTrue($result->eligible());
        self::assertSame('2026-09-06', $result->requestedDueDate()?->format('Y-m-d'));
    }
}
