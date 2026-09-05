<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Infrastructure\Persistence\PdoSearchHistoryRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoSearchHistoryRepositoryTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE search_history (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, search_query VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)'
        );
    }

    public function testRecordsAndReadsOnlyTheBorrowersNewestQueriesWithinTheLimit(): void
    {
        $this->pdo->exec("INSERT INTO search_history (user_id, search_query, created_at) VALUES
            (7, 'old', '2026-09-01 09:00:00'),
            (7, 'new', '2026-09-02 09:00:00'),
            (8, 'other', '2026-09-03 09:00:00')");
        $repository = new PdoSearchHistoryRepository($this->pdo);

        $repository->record(7, 'latest');
        $queries = $repository->recentQueries(7, 20);

        self::assertSame(['latest', 'new', 'old'], $queries);
    }
}
