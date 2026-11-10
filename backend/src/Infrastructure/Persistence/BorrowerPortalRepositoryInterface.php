<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

interface BorrowerPortalRepositoryInterface
{
    /** @return array<string, mixed> */
    public function dashboard(int $userId): array;

    /** @return list<array<string, mixed>> */
    public function activity(int $userId): array;

    /** @return array{notifications: list<array<string, mixed>>, unread_count: int} */
    public function notifications(int $userId): array;

    public function markNotificationRead(int $userId, int $notificationId): void;

    public function markAllNotificationsRead(int $userId): void;

    /** @return list<array<string, mixed>> */
    public function recentActivity(int $userId): array;

    /** @return list<array<string, mixed>> */
    public function history(int $userId): array;

    /** @return array<string, mixed>|null */
    public function receipt(int $userId, string $transactionCode): ?array;
}
