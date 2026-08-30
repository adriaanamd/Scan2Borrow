<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Reservation\HoldRecord;
use App\Infrastructure\Persistence\CirculationNotificationRepositoryInterface;
use App\Infrastructure\Persistence\HoldRepositoryInterface;
use DateTimeImmutable;

final readonly class ReservationAvailabilityService implements ReservationAvailabilityInterface
{
    public function __construct(
        private HoldRepositoryInterface $holds,
        private CirculationNotificationRepositoryInterface $notifications,
    ) {
    }

    public function advance(int $titleId, int $copyId, DateTimeImmutable $now): ?HoldRecord
    {
        $queued = $this->holds->nextEligibleQueued($titleId);
        if ($queued === null || $copyId <= 0) {
            return null;
        }

        $expiresAt = $now->modify('+24 hours');
        if (!$this->holds->offer($queued->id(), $copyId, $now, $expiresAt)) {
            return null;
        }

        $offered = $this->holds->find($queued->id()) ?? $queued;
        $this->notifications->notifyBorrower(
            $offered->userId(),
            'hold_available',
            'Hold ready: ' . $offered->title(),
            'Your hold for "' . $offered->title() . '" is ready to claim until ' . $expiresAt->format('M d, Y h:i A') . '.',
            $offered->id(),
        );

        return $offered;
    }
}
