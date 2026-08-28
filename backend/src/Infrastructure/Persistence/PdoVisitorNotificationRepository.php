<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;

final class PdoVisitorNotificationRepository implements VisitorNotificationRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function notifyVisitor(int $visitorId, string $title, string $message): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO visitor_notifications (visitor_id, title, message) VALUES (:visitor_id, :title, :message)'
        );
        $statement->execute(['visitor_id' => $visitorId, 'title' => $title, 'message' => $message]);
    }

    public function notifyStaff(int $borrowingId, string $message): void
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO notifications (user_id, type, title, message, related_id) SELECT id, 'borrow_request', 'New Guest Borrow Request', :message, :related_id FROM users WHERE role IN ('admin', 'librarian') AND status = 'active'"
        );
        $statement->execute(['message' => $message, 'related_id' => $borrowingId]);
    }
}
