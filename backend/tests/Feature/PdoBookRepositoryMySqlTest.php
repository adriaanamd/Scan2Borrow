<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Book\BookSearchCriteria;
use App\Infrastructure\Database\DatabaseConfig;
use App\Infrastructure\Database\PdoConnectionFactory;
use App\Infrastructure\Persistence\PdoBookRepository;
use PDOException;
use PHPUnit\Framework\TestCase;

final class PdoBookRepositoryMySqlTest extends TestCase
{
    public function testSearchSupportsOneTermAcrossAllBookColumnsWithNativeMySqlPrepares(): void
    {
        try {
            $pdo = (new PdoConnectionFactory())->create(DatabaseConfig::fromEnvironment());
        } catch (PDOException $exception) {
            self::markTestSkipped('MySQL is not available: ' . $exception->getMessage());
        }

        $statement = $pdo->query('SELECT title FROM books WHERE deleted_at IS NULL ORDER BY id LIMIT 1');
        $title = $statement === false ? false : $statement->fetchColumn();
        if (!is_string($title) || $title === '') {
            self::markTestSkipped('The MySQL database has no active books to search.');
        }

        $result = (new PdoBookRepository($pdo))->search(BookSearchCriteria::fromArray(['search' => $title]));

        self::assertGreaterThanOrEqual(1, $result->total());
    }
}
