<?php

declare(strict_types=1);

namespace Tests\Unit\Reservation;

use App\Domain\Reservation\HoldStatus;
use PHPUnit\Framework\TestCase;

final class HoldStatusTest extends TestCase
{
    public function testReservationStatusesUseStableStorageValues(): void
    {
        self::assertSame(
            ['queued', 'offered', 'claimed', 'fulfilled', 'expired', 'cancelled'],
            array_map(static fn (HoldStatus $status): string => $status->value, HoldStatus::cases()),
        );
    }

    public function testLabelsDescribeEveryReservationState(): void
    {
        foreach (HoldStatus::cases() as $status) {
            self::assertNotSame('', $status->label());
        }
    }
}
