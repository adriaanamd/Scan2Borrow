<?php

declare(strict_types=1);

namespace Tests\Unit\Reservation;

use App\Application\DTO\HoldActionRequest;
use App\Application\DTO\JoinHoldRequest;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HoldRequestTest extends TestCase
{
    public function testJoinRequestRequiresPositiveUserAndTitleIds(): void
    {
        self::assertSame(7, (new JoinHoldRequest(7, 4))->userId);
        self::assertSame(4, (new JoinHoldRequest(7, 4))->titleId);

        $this->expectException(InvalidArgumentException::class);
        new JoinHoldRequest(0, 4);
    }

    public function testHoldActionAllowsOnlyBorrowerActions(): void
    {
        self::assertSame('claim', (new HoldActionRequest(7, 12, 'claim'))->action);
        self::assertSame('cancel', (new HoldActionRequest(7, 12, 'cancel'))->action);

        $this->expectException(InvalidArgumentException::class);
        new HoldActionRequest(7, 12, 'advance');
    }
}
