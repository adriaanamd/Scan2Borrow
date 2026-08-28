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
            'CREATE TABLE visitors (id INTEGER PRIMARY KEY AUTOINCREMENT, visitor_number TEXT, firstname TEXT, lastname TEXT, account_status TEXT, registration_expires_at TEXT, id_barcode TEXT)'
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
}
