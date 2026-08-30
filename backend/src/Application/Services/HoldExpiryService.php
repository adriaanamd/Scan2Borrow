<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Infrastructure\Persistence\HoldRepositoryInterface;
use App\Infrastructure\Persistence\ReservationCopyRepositoryInterface;

final readonly class HoldExpiryService
{
    public function __construct(
        private HoldRepositoryInterface $holds,
        private ReservationCopyRepositoryInterface $copies,
        private ReservationAvailabilityInterface $availability,
        private ClockInterface $clock,
    ) {
    }

    public function run(): int
    {
        $now = $this->clock->now();
        $expiredTitles = $this->holds->expireOffers($now);
        $advanced = 0;
        foreach ($expiredTitles as $titleId) {
            $copyId = $this->copies->availableCopyForTitle($titleId);
            if ($copyId !== null && $this->availability->advance($titleId, $copyId, $now) !== null) {
                $advanced++;
            }
        }

        return count($expiredTitles);
    }
}
