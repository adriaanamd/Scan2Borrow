<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Application\DTO\GuestBorrowRequest;
use App\Infrastructure\Persistence\PdoGuestBorrowingRepository;
use App\Infrastructure\Persistence\PdoGuestPortalRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoGuestRepositoriesTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE books (id INTEGER PRIMARY KEY AUTOINCREMENT, barcode TEXT, accession_no TEXT, isbn TEXT, title TEXT, author TEXT, publisher TEXT, category_name TEXT, cover_file TEXT, floor_no TEXT, section_name TEXT, shelf_no TEXT, row_no TEXT, status TEXT, deleted_at TEXT)'
        );
        $this->pdo->exec(
            'CREATE TABLE visitor_borrowing (id INTEGER PRIMARY KEY AUTOINCREMENT, visitor_id INTEGER, book_id INTEGER, borrow_date TEXT, due_date TEXT, return_date TEXT, request_status TEXT, verification_photo TEXT, return_verification_photo TEXT, requested_at TEXT, released_at TEXT, return_requested_at TEXT, review_notes TEXT)'
        );
        $this->pdo->exec(
            'CREATE TABLE visitors (id INTEGER PRIMARY KEY AUTOINCREMENT, visitor_number TEXT, firstname TEXT, middlename TEXT, lastname TEXT, account_status TEXT, registration_expires_at TEXT, id_barcode TEXT)'
        );
        $this->pdo->exec(
            'CREATE TABLE visitor_visit_history (id INTEGER PRIMARY KEY AUTOINCREMENT, visitor_id INTEGER, time_in TEXT, time_out TEXT)'
        );
        $this->pdo->exec(
            'CREATE TABLE visitor_security_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, visitor_id INTEGER, activity TEXT, details TEXT, created_at TEXT)'
        );
        $this->pdo->exec("INSERT INTO visitors (id, visitor_number, firstname, lastname, account_status) VALUES (7, 'VIS-2026-000007', 'Lia', 'Santos', 'Active')");
        $this->pdo->exec("INSERT INTO books (id, barcode, title, author, category_name, status) VALUES (12, 'BK-12', 'Clean Code', 'Robert Martin', 'Computer Science', 'Available')");
    }

    public function testGuestBorrowingRepositoryPreservesVisitorBorrowingWorkflow(): void
    {
        $repository = new PdoGuestBorrowingRepository($this->pdo);

        self::assertTrue($repository->isBookAvailable(12));
        $id = $repository->createPending(new GuestBorrowRequest(12, 'GOV-7', 'photo-data'), 7);
        self::assertSame(1, $repository->activeCount(7));

        $repository->markReturnVerification($id, 'return-photo');
        $statement = $this->pdo->query('SELECT request_status, return_verification_photo FROM visitor_borrowing WHERE id = ' . $id);
        self::assertNotFalse($statement);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('Return Verification Pending', $row['request_status']);
        self::assertSame('return-photo', $row['return_verification_photo']);
    }

    public function testGuestPortalBrowseUsesExistingBookFieldsAndAvailability(): void
    {
        /** @var array{books: list<array<string, mixed>>, total: int} $result */
        $result = (new PdoGuestPortalRepository($this->pdo))->browse(['q' => 'clean']);

        self::assertSame(1, $result['total']);
        self::assertSame('Clean Code', $result['books'][0]['title']);
        self::assertSame('Computer Science', $result['books'][0]['category_name']);
    }

    public function testGuestDashboardIncludesVisitAndSecurityHistory(): void
    {
        $this->pdo->exec("INSERT INTO visitor_visit_history (visitor_id, time_in, time_out) VALUES (7, '2026-08-01 09:00:00', NULL)");
        $this->pdo->exec("INSERT INTO visitor_security_logs (visitor_id, activity, details, created_at) VALUES (7, 'login', 'Guest signed in.', '2026-08-01 09:00:00')");

        /** @var array{visit_history: list<array<string, mixed>>, security_log: list<array<string, mixed>>} $summary */
        $summary = (new PdoGuestPortalRepository($this->pdo))->dashboardSummary(7);

        self::assertCount(1, $summary['visit_history']);
        self::assertSame('login', $summary['security_log'][0]['activity']);
    }

    public function testGuestHistoryExposesReturnDecisionMetadataWhenPresent(): void
    {
        $this->pdo->exec('ALTER TABLE visitor_borrowing ADD COLUMN return_decided_at TEXT');
        $this->pdo->exec('ALTER TABLE visitor_borrowing ADD COLUMN return_decided_by INTEGER');
        $this->pdo->exec('ALTER TABLE visitor_borrowing ADD COLUMN return_decision_note TEXT');
        $this->pdo->exec("INSERT INTO visitor_borrowing (visitor_id, book_id, borrow_date, due_date, request_status, return_decided_at, return_decided_by, return_decision_note) VALUES (7, 12, '2026-08-20', '2026-08-27', 'Released', '2026-08-28 10:00:00', 19, 'Book was not received.')");

        $rows = (new PdoGuestPortalRepository($this->pdo))->history(7, 'all', '', '');

        self::assertSame('2026-08-28 10:00:00', $rows[0]['return_decided_at']);
        self::assertEquals(19, $rows[0]['return_decided_by']);
        self::assertSame('Book was not received.', $rows[0]['return_decision_note']);
    }
}
