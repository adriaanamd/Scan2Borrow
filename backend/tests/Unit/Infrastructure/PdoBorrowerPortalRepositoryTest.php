<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Infrastructure\Persistence\PdoBorrowerPortalRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoBorrowerPortalRepositoryTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, barcode TEXT, firstname TEXT, middlename TEXT, lastname TEXT, department TEXT, position TEXT, course TEXT, year_level TEXT, photo TEXT, role TEXT)');
        $this->pdo->exec('CREATE TABLE books (id INTEGER PRIMARY KEY, barcode TEXT, title TEXT, author TEXT, category_name TEXT, status TEXT, deleted_at TEXT, floor_no TEXT, created_at TEXT)');
        $this->pdo->exec('CREATE TABLE borrowing (id INTEGER PRIMARY KEY, transaction_code TEXT, user_id INTEGER, book_id INTEGER, borrow_date TEXT, due_date TEXT, return_date TEXT, status TEXT, fine_amount NUMERIC)');
        $this->pdo->exec("INSERT INTO users VALUES (1, 'STU-1', 'Grace', 'B.', 'Hopper', 'IT', NULL, 'CS', '4', NULL, 'student')");
        $this->pdo->exec("INSERT INTO books VALUES
            (1, 'BK-1', 'Clean Code', 'Martin', 'Computer Science', 'Borrowed', NULL, '2', '2026-08-01'),
            (2, 'BK-2', 'Refactoring', 'Fowler', 'Computer Science', 'Available', NULL, '2', '2026-08-02'),
            (3, 'BK-3', 'Domain-Driven Design', 'Evans', 'Software', 'Available', NULL, '2', '2026-08-03')");
        $this->pdo->exec("INSERT INTO borrowing VALUES
            (1, 'TX-1', 1, 1, '2026-08-10', '2026-08-17', NULL, 'Overdue', 25),
            (2, 'TX-2', 1, 2, '2026-07-01', '2026-07-08', '2026-07-07', 'Returned', 0),
            (3, 'TX-3', 1, 3, '2026-06-01', '2026-06-08', '2026-06-10', 'Returned', 10)");
    }

    public function testDashboardReturnsTheSameLoanStatsAndOnTimeRate(): void
    {
        /** @var array{
         *     user: array{name: string},
         *     stats: array{active: int, overdue: int, fines: float, on_time_rate: int},
         *     current_loans: list<array{title: string}>
         * } $dashboard */
        $dashboard = (new PdoBorrowerPortalRepository($this->pdo))->dashboard(1);

        self::assertSame('Grace Hopper', $dashboard['user']['name']);
        self::assertSame(1, $dashboard['stats']['active']);
        self::assertSame(1, $dashboard['stats']['overdue']);
        self::assertSame(25.0, $dashboard['stats']['fines']);
        self::assertSame(50, $dashboard['stats']['on_time_rate']);
        self::assertCount(1, $dashboard['current_loans']);
        self::assertSame('Clean Code', $dashboard['current_loans'][0]['title']);
    }
}
