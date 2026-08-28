<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Infrastructure\Persistence\PdoStaffRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoStaffRepositoryTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE books (id INTEGER PRIMARY KEY, barcode TEXT, title TEXT, author TEXT, category_name TEXT, status TEXT, deleted_at TEXT, floor_no TEXT, section_name TEXT, shelf_no TEXT, created_at TEXT, cover_file TEXT)');
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, barcode TEXT, firstname TEXT, lastname TEXT, department TEXT, position TEXT, course TEXT, year_level TEXT, email TEXT, contact_no TEXT, photo TEXT, role TEXT, password_hash TEXT, status TEXT)');
        $this->pdo->exec('CREATE TABLE borrowing (id INTEGER PRIMARY KEY, transaction_code TEXT, user_id INTEGER, book_id INTEGER, approval_status TEXT, borrow_date TEXT, due_date TEXT, return_date TEXT, status TEXT, fine_amount NUMERIC, requested_at TEXT, approved_at TEXT, approved_by INTEGER)');
        $this->pdo->exec('CREATE TABLE visitor_borrowing (id INTEGER PRIMARY KEY, visitor_id INTEGER, book_id INTEGER, due_date TEXT, request_status TEXT, verification_photo TEXT, requested_at TEXT)');
        $this->pdo->exec('CREATE TABLE visitors (id INTEGER PRIMARY KEY, firstname TEXT, lastname TEXT, visitor_number TEXT, photo TEXT, id_barcode TEXT)');
        $this->pdo->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY, user_id INTEGER, type TEXT, title TEXT, message TEXT, related_id INTEGER, is_read INTEGER, created_at TEXT)');
        $this->pdo->exec('CREATE TABLE return_notifications (id INTEGER PRIMARY KEY, borrowing_id INTEGER, user_id INTEGER, book_id INTEGER, message TEXT, is_viewed INTEGER, viewed_at TEXT, created_at TEXT)');
        $this->pdo->exec("INSERT INTO books (id, barcode, title, author, category_name, status, floor_no, section_name, shelf_no, created_at) VALUES (1, 'BK-1', 'Clean Code', 'Martin', 'Computer Science', 'Available', '2', 'IT', 'A1', '2026-08-01')");
        $this->pdo->exec("INSERT INTO users VALUES (1, 'STAFF-1', 'Ada', 'Lovelace', 'Library', 'Librarian', NULL, NULL, 'ada@example.test', '09170000000', NULL, 'librarian', NULL, 'active')");
        $this->pdo->exec("INSERT INTO users VALUES (2, 'STU-1', 'Grace', 'Hopper', 'IT', NULL, 'CS', '4', 'grace@example.test', '09171111111', NULL, 'student', NULL, 'active')");
        $this->pdo->exec("INSERT INTO borrowing (id, transaction_code, user_id, book_id, approval_status, borrow_date, due_date, return_date, status, fine_amount) VALUES (1, 'TX-1', 2, 1, 'pending', '2026-08-20', '2026-08-27', NULL, 'Pending', 0)");
    }

    public function testDashboardAndBorrowerQueriesPreserveStaffDataShape(): void
    {
        $repository = new PdoStaffRepository($this->pdo);

        $dashboard = $repository->dashboard();
        self::assertIsArray($dashboard['stats'] ?? null);
        self::assertSame(1, $dashboard['stats']['total_books']);
        self::assertSame('Grace Hopper', $repository->borrowers('Grace')[0]['name']);
        self::assertSame('TX-1', $repository->pendingBorrowings()[0]['transaction_code']);
    }

    public function testDashboardIncludesOverviewAggregatesAndTopBorrowers(): void
    {
        $this->pdo->exec("INSERT INTO users VALUES (3, 'STU-2', 'Katherine', 'Johnson', 'Science', NULL, 'Math', '3', 'katherine@example.test', '09172222222', NULL, 'student', NULL, 'active')");
        $this->pdo->exec("INSERT INTO books (id, barcode, title, author, category_name, status, floor_no, section_name, shelf_no, created_at) VALUES (2, 'BK-2', 'Mathematics', 'Euler', 'Mathematics', 'Borrowed', '2', 'Math', 'B1', '2026-08-01')");
        $this->pdo->exec("INSERT INTO borrowing (id, transaction_code, user_id, book_id, approval_status, borrow_date, due_date, return_date, status, fine_amount) VALUES
            (2, 'TX-2', 2, 1, 'approved', '2026-07-10', '2026-07-17', '2026-07-18', 'Returned', 0),
            (3, 'TX-3', 2, 1, 'approved', '2026-08-02', '2026-08-09', NULL, 'Borrowed', 0),
            (4, 'TX-4', 3, 1, 'approved', '2026-08-03', '2026-08-10', NULL, 'Overdue', 10),
            (5, 'TX-5', 2, 2, 'approved', '2026-06-15', '2026-06-22', '2026-06-23', 'Returned', 0)");

        /** @var array{overview: array{borrowing_activity: list<array{month: string, label: string, count: int}>, category_borrowing_activity: array{months: list<array{month: string, label: string}>, series: list<array{name: string, counts: list<int>}>}, loan_status: array{available: int, borrowed: int, overdue: int, pending: int}, category_breakdown: list<array{name: string, count: int}>, top_genres: list<array{name: string, count: int}>, top_borrowers: list<array{id: int, name: string, barcode: string, borrowing_count: int}>, recent_activity: list<array<string, mixed>>}} $dashboard */
        $dashboard = (new PdoStaffRepository($this->pdo))->dashboard();
        $overview = $dashboard['overview'];

        self::assertCount(12, $overview['borrowing_activity']);
        self::assertSame(['month', 'label', 'count'], array_keys($overview['borrowing_activity'][0]));
        self::assertSame(['available', 'borrowed', 'overdue', 'pending'], array_keys($overview['loan_status']));
        self::assertSame(['name', 'count'], array_keys($overview['category_breakdown'][0]));
        self::assertSame('Computer Science', $overview['category_breakdown'][0]['name']);
        self::assertCount(12, $overview['category_borrowing_activity']['months']);
        self::assertSame(['month', 'label'], array_keys($overview['category_borrowing_activity']['months'][0]));
        self::assertSame('Computer Science', $overview['category_borrowing_activity']['series'][0]['name']);
        self::assertSame(4, array_sum($overview['category_borrowing_activity']['series'][0]['counts']));
        self::assertSame('Computer Science', $overview['top_genres'][0]['name']);
        self::assertSame(4, $overview['top_genres'][0]['count']);
        self::assertSame('TX-5', $overview['recent_activity'][0]['transaction_code']);
        self::assertCount(5, $overview['recent_activity']);
        self::assertSame('Grace Hopper', $overview['top_borrowers'][0]['name']);
        self::assertSame(4, $overview['top_borrowers'][0]['borrowing_count']);
        self::assertSame('STU-1', $overview['top_borrowers'][0]['barcode']);
        self::assertLessThanOrEqual(10, count($overview['top_borrowers']));
    }

    public function testDashboardOverviewHandlesEmptyBorrowingData(): void
    {
        $this->pdo->exec('DELETE FROM borrowing');

        /** @var array{overview: array{borrowing_activity: list<array{month: string, label: string, count: int}>, category_borrowing_activity: array{months: list<array{month: string, label: string}>, series: list<array{name: string, counts: list<int>}>}, loan_status: array{available: int, borrowed: int, overdue: int, pending: int}, category_breakdown: list<array{name: string, count: int}>, top_genres: list<array{name: string, count: int}>, top_borrowers: list<array{id: int, name: string, barcode: string, borrowing_count: int}>, recent_activity: list<array<string, mixed>>}} $dashboard */
        $dashboard = (new PdoStaffRepository($this->pdo))->dashboard();
        $overview = $dashboard['overview'];

        self::assertCount(12, $overview['borrowing_activity']);
        self::assertSame(0, array_sum(array_column($overview['borrowing_activity'], 'count')));
        self::assertSame([], $overview['top_borrowers']);
        self::assertSame([], $overview['top_genres']);
        self::assertSame([], $overview['recent_activity']);
        self::assertSame(1, $overview['loan_status']['available']);
        self::assertSame(0, $overview['loan_status']['borrowed']);
        self::assertSame(0, $overview['loan_status']['overdue']);
        self::assertSame(0, $overview['loan_status']['pending']);
    }

    public function testApprovalUpdatesBorrowingAndBookTogether(): void
    {
        $repository = new PdoStaffRepository($this->pdo);

        $repository->approveBorrowing(1, 1);

        $approval = $this->pdo->query('SELECT approval_status FROM borrowing WHERE id = 1');
        $book = $this->pdo->query('SELECT status FROM books WHERE id = 1');
        self::assertNotFalse($approval);
        self::assertNotFalse($book);
        self::assertSame('approved', $approval->fetchColumn());
        self::assertSame('Borrowed', $book->fetchColumn());
    }

    public function testBorrowerDetailsPreserveProfileHistoryAndSummary(): void
    {
        $details = (new PdoStaffRepository($this->pdo))->borrowerDetails(2);

        self::assertNotNull($details);
        self::assertSame('Grace Hopper', $details['borrower']['name']);
        self::assertSame('grace@example.test', $details['borrower']['email']);
        self::assertSame(1, $details['summary']['active']);
        self::assertSame('Clean Code', $details['history'][0]['title']);
    }

    public function testUnknownBorrowerReturnsNoDetails(): void
    {
        self::assertNull((new PdoStaffRepository($this->pdo))->borrowerDetails(999));
    }
}
