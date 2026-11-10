<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\BorrowerContactNotification;

interface BorrowerContactNotificationInterface
{
    public function dispatch(BorrowerContactNotification $notification): void;
}
