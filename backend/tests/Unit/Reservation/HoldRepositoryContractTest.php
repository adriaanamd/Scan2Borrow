<?php

declare(strict_types=1);

namespace Tests\Unit\Reservation;

use App\Infrastructure\Persistence\HoldRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class HoldRepositoryContractTest extends TestCase
{
    public function testRepositoryExposesTheCompleteReservationWorkflowContract(): void
    {
        $expected = [
            'findActiveForUserTitle', 'listForUser', 'join', 'cancel', 'claim', 'fulfil',
            'nextEligibleQueued', 'offer', 'expire', 'listStaff', 'expireOffers',
        ];

        foreach ($expected as $method) {
            self::assertTrue(method_exists(HoldRepositoryInterface::class, $method), $method . ' is missing.');
        }

        $offer = new ReflectionMethod(HoldRepositoryInterface::class, 'offer');
        self::assertSame(DateTimeImmutable::class, $offer->getParameters()[2]->getType()?->getName());
        self::assertSame(DateTimeImmutable::class, $offer->getParameters()[3]->getType()?->getName());
    }
}
