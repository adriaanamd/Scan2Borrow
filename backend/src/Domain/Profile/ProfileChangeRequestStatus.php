<?php

declare(strict_types=1);

namespace App\Domain\Profile;

enum ProfileChangeRequestStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending review',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
        };
    }
}
