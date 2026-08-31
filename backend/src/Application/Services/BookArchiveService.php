<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Infrastructure\Persistence\BookAdministrationRepositoryInterface;

final class BookArchiveService
{
    public function __construct(private readonly BookAdministrationRepositoryInterface $books)
    {
    }

    /** @param list<int> $ids */
    public function archive(array $ids, int $actorId = 0): int
    {
        return $this->books->archive($ids, $actorId);
    }

    /** @param list<int> $ids */
    public function restore(array $ids, int $actorId = 0): int
    {
        return $this->books->restore($ids, $actorId);
    }

    /** @param list<int> $ids */
    public function delete(array $ids, int $actorId = 0): int
    {
        return $this->books->delete($ids, $actorId);
    }
}
