<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;

final class PdoCirculationNotificationRepository implements CirculationNotificationRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function notifyBorrower(int $userId, string $type, string $title, string $message, int $relatedId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO notifications (user_id, type, title, message, related_id, is_read) '
            . 'VALUES (:user_id, :type, :title, :message, :related_id, 0)',
        );
        $statement->execute([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'related_id' => $relatedId,
        ]);
    }

    public function notifyStaff(string $type, string $title, string $message, int $relatedId): void
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO notifications (user_id, type, title, message, related_id, is_read)
             SELECT id, :type, :title, :message, :related_id, 0
             FROM users WHERE role IN ('admin', 'librarian') AND status = 'active'",
        );
        $statement->execute([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'related_id' => $relatedId,
        ]);
    }
}
