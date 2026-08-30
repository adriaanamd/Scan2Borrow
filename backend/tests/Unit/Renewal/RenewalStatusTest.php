<?php

declare(strict_types=1);

namespace Tests\Unit\Renewal;

use App\Domain\Renewal\RenewalStatus;
use PHPUnit\Framework\TestCase;

final class RenewalStatusTest extends TestCase
{
    public function testRenewalStatusesUseStableStorageValues(): void
    {
        self::assertSame(['pending', 'approved', 'rejected', 'cancelled'], array_column(RenewalStatus::cases(), 'value'));
    }

    public function testRenewalStatusLabelsAreBorrowerReadable(): void
    {
        self::assertSame('Awaiting librarian approval', RenewalStatus::PENDING->label());
        self::assertSame('Approved', RenewalStatus::APPROVED->label());
        self::assertSame('Rejected', RenewalStatus::REJECTED->label());
        self::assertSame('Cancelled', RenewalStatus::CANCELLED->label());
    }
}
