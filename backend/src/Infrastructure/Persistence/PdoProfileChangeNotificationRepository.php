<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;

final class PdoProfileChangeNotificationRepository implements ProfileChangeNotificationInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function notifyAdministrators(int $requestId, string $message): void
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO notifications (user_id, type, title, message, related_id, is_read)
             SELECT id, 'profile_change_request', 'Profile change request', :message, :related_id, 0
             FROM users WHERE role = 'admin' AND status = 'active'",
        );
        $statement->execute(['message' => $message, 'related_id' => $requestId]);
    }

    public function notifyBorrower(int $userId, int $requestId, string $title, string $message): void
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO notifications (user_id, type, title, message, related_id, is_read)
             VALUES (:user_id, 'profile_change_decision', :title, :message, :related_id, 0)",
        );
        $statement->execute([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'related_id' => $requestId,
        ]);
    }
}
