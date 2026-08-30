<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Reservation\HoldRecord;
use DateTimeImmutable;

interface ReservationAvailabilityInterface
{
    public function advance(int $titleId, int $copyId, DateTimeImmutable $now): ?HoldRecord;
}
