<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

interface SearchHistoryRepositoryInterface
{
    public function record(int $userId, string $query): void;

    /** @return list<string> */
    public function recentQueries(int $userId, int $limit): array;
}
