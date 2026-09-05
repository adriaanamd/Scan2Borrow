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

    public function testActivityIsUserScopedNewestFirstAndRecentIsLimitedToFive(): void
    {
        $this->pdo->exec('CREATE TABLE book_titles (id INTEGER PRIMARY KEY, title TEXT, author TEXT, category_name TEXT, created_at TEXT)');
        $this->pdo->exec('CREATE TABLE book_copies (id INTEGER PRIMARY KEY, title_id INTEGER, barcode TEXT, floor_no TEXT, status TEXT, deleted_at TEXT)');
        $this->pdo->exec('CREATE TABLE borrowing_transactions (id INTEGER PRIMARY KEY, transaction_code TEXT, user_id INTEGER, borrow_date TEXT, due_date TEXT, return_date TEXT, status TEXT, approval_status TEXT, fine_amount NUMERIC)');
        $this->pdo->exec('CREATE TABLE borrowing_items (id INTEGER PRIMARY KEY, transaction_id INTEGER, copy_id INTEGER, return_date TEXT, status TEXT, fine_amount NUMERIC)');
        $this->pdo->exec('CREATE TABLE reservations (id INTEGER PRIMARY KEY, user_id INTEGER, title_id INTEGER, status TEXT, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec('CREATE TABLE renewal_requests (id INTEGER PRIMARY KEY, loan_id INTEGER, user_id INTEGER, status TEXT, requested_at TEXT, decided_at TEXT)');
        $this->pdo->exec('CREATE TABLE profile_change_requests (id INTEGER PRIMARY KEY, user_id INTEGER, status TEXT, requested_at TEXT, reviewed_at TEXT)');
        $this->pdo->exec('CREATE TABLE audit_log (id INTEGER PRIMARY KEY, user_id INTEGER, action TEXT, details TEXT, created_at TEXT)');
        $this->pdo->exec("INSERT INTO book_titles VALUES (10, 'Activity Book', 'Author', 'Computer Science', '2026-08-01')");
        $this->pdo->exec("INSERT INTO book_copies VALUES (10, 10, 'COPY-10', '2', 'Borrowed', NULL)");
        $this->pdo->exec("INSERT INTO borrowing_transactions VALUES (10, 'TX-A', 1, '2026-08-01 09:00:00', '2026-08-10', NULL, 'Borrowed', 'approved', 0)");
        $this->pdo->exec("INSERT INTO borrowing_items VALUES (10, 10, 10, NULL, 'Borrowed', 0)");
        $this->pdo->exec("INSERT INTO reservations VALUES (4, 1, 10, 'queued', '2026-08-02 10:00:00', '2026-08-02 10:00:00')");
        $this->pdo->exec("INSERT INTO renewal_requests VALUES (5, 10, 1, 'approved', '2026-08-03 11:00:00', '2026-08-04 12:00:00')");
        $this->pdo->exec("INSERT INTO profile_change_requests VALUES (6, 1, 'pending', '2026-08-05 13:00:00', NULL)");
        $this->pdo->exec("INSERT INTO audit_log VALUES (7, 1, 'login', 'Signed in', '2026-08-06 14:00:00'), (8, 2, 'login', 'Other account', '2026-09-01 14:00:00')");

        $repository = new PdoBorrowerPortalRepository($this->pdo);
        $activity = $repository->activity(1);

        self::assertSame('2026-08-06 14:00:00', $activity[0]['occurred_at']);
        self::assertSame('login', $activity[0]['type']);
        self::assertNotContains('Other account', array_column($activity, 'details'));
        self::assertCount(5, $repository->recentActivity(1));
    }

    public function testActivityFallsBackToLegacyBorrowingWithoutOptionalTables(): void
    {
        $this->pdo->exec("INSERT INTO borrowing VALUES
            (4, 'TX-4', 1, 1, '2026-08-11 09:00:00', '2026-08-18', NULL, 'Borrowed', 0),
            (5, 'TX-5', 1, 2, '2026-08-01 09:00:00', '2026-08-08', '2026-08-07 16:00:00', 'Returned', 0)");

        $activity = (new PdoBorrowerPortalRepository($this->pdo))->activity(1);

        self::assertSame('2026-08-11 09:00:00', $activity[0]['occurred_at']);
        $returned = array_values(array_filter($activity, static fn (array $row): bool => $row['occurred_at'] === '2026-08-07 16:00:00'));
        self::assertCount(1, $returned);
        self::assertSame('Returned', $returned[0]['status']);
    }

    public function testNormalizedPortalRowsExposeGroupedQuantities(): void
    {
        $this->pdo->exec('CREATE TABLE book_titles (id INTEGER PRIMARY KEY, title TEXT, author TEXT, category_name TEXT, created_at TEXT)');
        $this->pdo->exec('CREATE TABLE book_copies (id INTEGER PRIMARY KEY, title_id INTEGER, barcode TEXT, floor_no TEXT, status TEXT, deleted_at TEXT)');
        $this->pdo->exec('CREATE TABLE borrowing_transactions (id INTEGER PRIMARY KEY, transaction_code TEXT, user_id INTEGER, borrow_date TEXT, due_date TEXT, return_date TEXT, status TEXT, approval_status TEXT, fine_amount NUMERIC)');
        $this->pdo->exec('CREATE TABLE borrowing_items (id INTEGER PRIMARY KEY, transaction_id INTEGER, copy_id INTEGER, return_date TEXT, status TEXT, fine_amount NUMERIC)');
        $this->pdo->exec("INSERT INTO book_titles VALUES (1, 'Clean Code', 'Martin', 'Computer Science', '2026-08-01')");
        $this->pdo->exec("INSERT INTO book_copies VALUES (1, 1, 'COPY-1', '2', 'Borrowed', NULL), (2, 1, 'COPY-2', '2', 'Borrowed', NULL)");
        $this->pdo->exec("INSERT INTO borrowing_transactions VALUES (1, 'TX-BULK', 1, '2026-08-10', '2026-09-10', NULL, 'Borrowed', 'approved', 0)");
        $this->pdo->exec("INSERT INTO borrowing_items VALUES (1, 1, 1, NULL, 'Borrowed', 0), (2, 1, 2, NULL, 'Borrowed', 0)");

        $repository = new PdoBorrowerPortalRepository($this->pdo);
        $dashboard = $repository->dashboard(1);
        $receipt = $repository->receipt(1, 'TX-BULK');

        self::assertSame(2, (int) $dashboard['current_loans'][0]['quantity']);
        self::assertSame(2, $dashboard['stats']['active']);
        self::assertSame(2, (int) $receipt['books'][0]['quantity']);
    }

    public function testDashboardMarksPendingApprovalLoansAsPending(): void
    {
        $this->pdo->exec('CREATE TABLE book_titles (id INTEGER PRIMARY KEY, title TEXT, author TEXT, category_name TEXT, created_at TEXT)');
        $this->pdo->exec('CREATE TABLE book_copies (id INTEGER PRIMARY KEY, title_id INTEGER, barcode TEXT, floor_no TEXT, status TEXT, deleted_at TEXT)');
        $this->pdo->exec('CREATE TABLE borrowing_transactions (id INTEGER PRIMARY KEY, transaction_code TEXT, user_id INTEGER, borrow_date TEXT, due_date TEXT, return_date TEXT, status TEXT, approval_status TEXT, fine_amount NUMERIC)');
        $this->pdo->exec('CREATE TABLE borrowing_items (id INTEGER PRIMARY KEY, transaction_id INTEGER, copy_id INTEGER, return_date TEXT, status TEXT, fine_amount NUMERIC)');
        $this->pdo->exec("INSERT INTO book_titles VALUES (1, 'Awaiting Book', 'Author', 'Computer Science', '2026-08-01')");
        $this->pdo->exec("INSERT INTO book_copies VALUES (1, 1, 'COPY-1', '2', 'Borrowed', NULL)");
        $this->pdo->exec("INSERT INTO borrowing_transactions VALUES (1, 'TX-PENDING', 1, '2026-08-10', '2026-09-10', NULL, 'Borrowed', 'approved', 0)");
        $this->pdo->exec("INSERT INTO borrowing_items VALUES (1, 1, 1, NULL, 'Pending', 0)");

        $dashboard = (new PdoBorrowerPortalRepository($this->pdo))->dashboard(1);

        self::assertSame('Pending', $dashboard['current_loans'][0]['status']);
    }
}
