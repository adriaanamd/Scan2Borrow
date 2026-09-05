<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;

final readonly class PdoSearchHistoryRepository implements SearchHistoryRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function record(int $userId, string $query): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO search_history (user_id, search_query) VALUES (:user_id, :search_query)'
        );
        $statement->execute(['user_id' => $userId, 'search_query' => $query]);
    }

    /** @return list<string> */
    public function recentQueries(int $userId, int $limit): array
    {
        $boundedLimit = max(1, min(20, $limit));
        $statement = $this->pdo->prepare(
            'SELECT search_query FROM search_history WHERE user_id = :user_id ORDER BY created_at DESC, id DESC LIMIT :limit'
        );
        $statement->bindValue('user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue('limit', $boundedLimit, PDO::PARAM_INT);
        $statement->execute();
        /** @var list<array{search_query: string}> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): string => $row['search_query'], $rows);
    }
}
