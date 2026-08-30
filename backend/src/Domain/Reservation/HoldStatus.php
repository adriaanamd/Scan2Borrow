<?php

declare(strict_types=1);

namespace App\Domain\Reservation;

enum HoldStatus: string
{
    case QUEUED = 'queued';
    case OFFERED = 'offered';
    case CLAIMED = 'claimed';
    case FULFILLED = 'fulfilled';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::QUEUED => 'Queued',
            self::OFFERED => 'Ready to claim',
            self::CLAIMED => 'Claimed',
            self::FULFILLED => 'Fulfilled',
            self::EXPIRED => 'Expired',
            self::CANCELLED => 'Cancelled',
        };
    }
}
