<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use DateTimeImmutable;

interface ContactNotificationDeliveryRepositoryInterface
{
    /** @return array{name: string, email: ?string, phone: ?string}|null */
    public function findRecipient(string $recipientType, int $recipientId): ?array;

    public function createDelivery(
        string $recipientType,
        int $recipientId,
        string $eventType,
        string $borrowingReference,
        string $channel,
        ?string $destination,
        ?string $subject,
        string $message,
        string $status,
        ?string $lastError = null,
    ): ?int;

    public function markSent(int $deliveryId, DateTimeImmutable $sentAt): void;

    public function markFailed(int $deliveryId, string $error): void;
}
