<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

interface ProfileChangeNotificationInterface
{
    public function notifyAdministrators(int $requestId, string $message): void;

    public function notifyBorrower(int $userId, int $requestId, string $title, string $message): void;
}
