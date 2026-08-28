<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

interface VisitorNotificationRepositoryInterface
{
    public function notifyVisitor(int $visitorId, string $title, string $message): void;

    public function notifyStaff(int $borrowingId, string $message): void;
}
