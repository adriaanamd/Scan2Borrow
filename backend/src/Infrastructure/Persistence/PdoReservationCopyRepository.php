<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;

final readonly class PdoReservationCopyRepository implements ReservationCopyRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function availableCopyForTitle(int $titleId): ?int
    {
        $statement = $this->pdo->prepare(
            "SELECT id FROM book_copies WHERE title_id = :title_id AND status = 'Available' AND deleted_at IS NULL ORDER BY id LIMIT 1",
        );
        $statement->execute(['title_id' => $titleId]);
        $copyId = $statement->fetchColumn();

        return $copyId === false ? null : (int) $copyId;
    }
}
