<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

interface CirculationNotificationRepositoryInterface
{
    public function notifyBorrower(int $userId, string $type, string $title, string $message, int $relatedId): void;

    public function notifyStaff(string $type, string $title, string $message, int $relatedId): void;
}
