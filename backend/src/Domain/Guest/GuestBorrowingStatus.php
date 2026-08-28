<?php

declare(strict_types=1);

namespace App\Domain\Guest;

enum GuestBorrowingStatus: string
{
    case PENDING = 'Pending';
    case READY_FOR_RELEASE = 'Ready for Release';
    case RELEASED = 'Released';
    case RETURN_VERIFICATION_PENDING = 'Return Verification Pending';
    case RETURNED = 'Returned';
    case REJECTED = 'Rejected';
}
