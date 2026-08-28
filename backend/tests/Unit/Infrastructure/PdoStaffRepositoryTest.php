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
