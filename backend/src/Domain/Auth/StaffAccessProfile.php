<?php

declare(strict_types=1);

namespace App\Domain\Auth;

enum StaffAccessProfile: string
{
    case SYSTEM_ADMIN = 'system_admin';
    case LIBRARY_AIDE = 'library_aide';
}
