<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;

final class PdoGuestApprovalRepository implements GuestApprovalRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findPending(int $requestId): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT vb.id, vb.visitor_id, b.title, vb.due_date FROM visitor_borrowing vb JOIN books b ON b.id = vb.book_id WHERE vb.id = :id AND vb.request_status = 'Pending' LIMIT 1"
        );
        $statement->execute(['id' => $requestId]);
        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return [
            'id' => $this->intValue($row['id'] ?? null),
            'visitor_id' => $this->intValue($row['visitor_id'] ?? null),
            'title' => $this->stringValue($row['title'] ?? null),
            'due_date' => $this->stringValue($row['due_date'] ?? null),
        ];
    }

    public function approve(int $requestId, string $notes): void
    {
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                "UPDATE visitor_borrowing SET request_status = 'Released', released_at = CURRENT_TIMESTAMP, review_notes = :notes WHERE id = :id"
            );
            $statement->execute(['notes' => $this->nullable($notes), 'id' => $requestId]);
            $this->pdo->prepare(
                "UPDATE books b JOIN visitor_borrowing vb ON vb.book_id = b.id SET b.status = 'Borrowed' WHERE vb.id = :id"
            )->execute(['id' => $requestId]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function reject(int $requestId, string $reason): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE visitor_borrowing SET request_status = 'Rejected', review_notes = :reason WHERE id = :id"
        );
        $statement->execute(['reason' => $reason, 'id' => $requestId]);
    }

    private function intValue(mixed $value): int
    {
        return is_int($value) ? $value : (is_string($value) && is_numeric($value) ? (int) $value : 0);
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function nullable(string $value): ?string
    {
        return trim($value) === '' ? null : $value;
    }
}
