<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use DateTimeImmutable;
use PDO;

final class PdoContactNotificationDeliveryRepository implements ContactNotificationDeliveryRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findRecipient(string $recipientType, int $recipientId): ?array
    {
        if ($recipientId < 1) {
            return null;
        }

        $table = $recipientType === 'visitor' ? 'visitors' : 'users';
        $statement = $this->pdo->prepare(
            "SELECT firstname, lastname, email, contact_no FROM {$table} WHERE id = :id LIMIT 1",
        );
        $statement->execute(['id' => $recipientId]);
        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return [
            'name' => trim($this->stringValue($row['firstname'] ?? null) . ' ' . $this->stringValue($row['lastname'] ?? null)),
            'email' => $this->nullableString($row['email'] ?? null),
            'phone' => $this->nullableString($row['contact_no'] ?? null),
        ];
    }

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
    ): ?int {
        $sql = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'INSERT OR IGNORE INTO contact_notification_deliveries '
                . '(recipient_type, recipient_id, event_type, borrowing_reference, channel, destination, subject, message, status, last_error, created_at, updated_at) '
                . 'VALUES (:recipient_type, :recipient_id, :event_type, :borrowing_reference, :channel, :destination, :subject, :message, :status, :last_error, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
            : 'INSERT IGNORE INTO contact_notification_deliveries '
                . '(recipient_type, recipient_id, event_type, borrowing_reference, channel, destination, subject, message, status, last_error) '
                . 'VALUES (:recipient_type, :recipient_id, :event_type, :borrowing_reference, :channel, :destination, :subject, :message, :status, :last_error)';
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'recipient_type' => $recipientType,
            'recipient_id' => $recipientId,
            'event_type' => $eventType,
            'borrowing_reference' => $borrowingReference,
            'channel' => $channel,
            'destination' => $destination,
            'subject' => $subject,
            'message' => $message,
            'status' => $status,
            'last_error' => $lastError,
        ]);

        return $statement->rowCount() === 1 ? (int) $this->pdo->lastInsertId() : null;
    }

    public function markSent(int $deliveryId, DateTimeImmutable $sentAt): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE contact_notification_deliveries
             SET status = 'sent', attempts = attempts + 1, last_error = NULL, sent_at = :sent_at, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id",
        );
        $statement->execute(['id' => $deliveryId, 'sent_at' => $sentAt->format('Y-m-d H:i:s')]);
    }

    public function markFailed(int $deliveryId, string $error): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE contact_notification_deliveries
             SET status = 'failed', attempts = attempts + 1, last_error = :last_error, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id",
        );
        $statement->execute(['id' => $deliveryId, 'last_error' => mb_substr(trim($error), 0, 500)]);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = $this->stringValue($value);

        return $value === '' ? null : $value;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
