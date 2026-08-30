<?php

declare(strict_types=1);

namespace Tests\Unit\Renewal;

use App\Application\DTO\RenewalDecisionRequest;
use App\Application\DTO\RenewalRequest;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RenewalRequestTest extends TestCase
{
    public function testBorrowerRequestTrimsReasonAndRequiresPositiveIds(): void
    {
        $request = new RenewalRequest(7, 12, '  Project deadline  ');

        self::assertSame(7, $request->userId);
        self::assertSame(12, $request->loanId);
        self::assertSame('Project deadline', $request->reason);
    }

    public function testDecisionRequestAcceptsOnlyLibrarianActions(): void
    {
        $request = new RenewalDecisionRequest(12, 2, ' approve ', '  Approved for one period  ');

        self::assertSame('approve', $request->action);
        self::assertSame('Approved for one period', $request->note);
    }

    public function testMalformedRenewalRequestIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RenewalRequest(0, 12);
    }
}
