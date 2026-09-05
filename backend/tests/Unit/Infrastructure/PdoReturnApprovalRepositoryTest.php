<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Infrastructure\Persistence\AuditEventRepositoryInterface;
use App\Infrastructure\Persistence\PdoReturnApprovalRepository;
use PDO;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PdoReturnApprovalRepositoryTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE books (id INTEGER PRIMARY KEY, barcode VARCHAR(50), title VARCHAR(200), author VARCHAR(150), status VARCHAR(20), return_date DATE)');
        $this->pdo->exec('CREATE TABLE book_titles (id INTEGER PRIMARY KEY, title VARCHAR(200))');
        $this->pdo->exec('CREATE TABLE book_copies (id INTEGER PRIMARY KEY, title_id INTEGER, barcode VARCHAR(50), status VARCHAR(20), due_date DATE, return_date DATE, deleted_at DATETIME)');
        $this->pdo->exec('CREATE TABLE borrowing_transactions (id INTEGER PRIMARY KEY, transaction_code VARCHAR(40), user_id INTEGER, approval_status VARCHAR(20), borrow_date DATETIME, due_date DATE, return_date DATETIME, return_status VARCHAR(20), return_requested_at DATETIME, return_decided_at DATETIME, return_decided_by INTEGER, return_decision_note VARCHAR(500), status VARCHAR(20), fine_amount DECIMAL(8,2))');
        $this->pdo->exec('CREATE TABLE borrowing_items (id INTEGER PRIMARY KEY, transaction_id INTEGER, copy_id INTEGER, return_date DATETIME, return_status VARCHAR(20), return_requested_at DATETIME, return_decided_at DATETIME, return_decided_by INTEGER, return_decision_note VARCHAR(500), status VARCHAR(20), fine_amount DECIMAL(8,2))');
        $this->pdo->exec('CREATE TABLE borrowing (id INTEGER PRIMARY KEY, transaction_code VARCHAR(40), user_id INTEGER, book_id INTEGER, approval_status VARCHAR(20), borrow_date DATETIME, due_date DATE, return_date DATETIME, return_status VARCHAR(20), return_requested_at DATETIME, return_decided_at DATETIME, return_decided_by INTEGER, return_decision_note VARCHAR(500), status VARCHAR(20), fine_amount DECIMAL(8,2))');
        $this->pdo->exec('CREATE TABLE visitor_borrowing (id INTEGER PRIMARY KEY, visitor_id INTEGER, book_id INTEGER, due_date DATE, return_date DATE, request_status VARCHAR(40), return_verification_photo TEXT, return_requested_at DATETIME, return_decided_at DATETIME, return_decided_by INTEGER, return_decision_note VARCHAR(500))');
        $this->pdo->exec("INSERT INTO books (id, barcode, title, author, status) VALUES (1, 'BK-1', 'Clean Code', 'Robert Martin', 'Borrowed'), (2, 'BK-2', 'Algorithms', 'Cormen', 'Borrowed')");
        $this->pdo->exec("INSERT INTO book_titles (id, title) VALUES (1, 'Clean Code')");
        $this->pdo->exec("INSERT INTO book_copies (id, title_id, barcode, status, due_date) VALUES (1, 1, 'COPY-1', 'Borrowed', '2026-08-25')");
        $this->pdo->exec("INSERT INTO borrowing_transactions (id, transaction_code, user_id, approval_status, borrow_date, due_date, return_status, return_requested_at, status, fine_amount) VALUES (1, 'TX-1', 7, 'approved', '2026-08-20', '2026-08-25', 'none', '2026-08-28 09:00:00', 'Borrowed', 0)");
        $this->pdo->exec("INSERT INTO borrowing_items (id, transaction_id, copy_id, return_status, return_requested_at, status, fine_amount) VALUES (1, 1, 1, 'pending', '2026-08-28 09:00:00', 'Borrowed', 0)");
        $this->pdo->exec("INSERT INTO borrowing (id, transaction_code, user_id, book_id, approval_status, borrow_date, due_date, return_status, return_requested_at, status, fine_amount) VALUES (2, 'LEGACY-1', 8, 2, 'approved', '2026-08-20', '2026-08-28', 'pending', '2026-08-28 10:00:00', 'Borrowed', 0)");
        $this->pdo->exec("INSERT INTO visitor_borrowing (id, visitor_id, book_id, due_date, request_status, return_verification_photo, return_requested_at) VALUES (3, 9, 1, '2026-08-28', 'Return Verification Pending', 'photo-data', '2026-08-28 11:00:00')");
    }

    public function testPendingReturnsIncludeNormalizedLegacyAndGuestSources(): void
    {
        $rows = (new PdoReturnApprovalRepository($this->pdo))->pending();

        self::assertCount(3, $rows);
        self::assertSame(['borrower_item', 'legacy_borrowing', 'guest'], array_column($rows, 'type'));
        self::assertSame(['COPY-1', 'BK-2', 'BK-1'], array_column($rows, 'book_barcode'));
        self::assertSame('photo-data', $rows[2]['evidence_photo']);
    }

    public function testApprovingNormalizedItemMakesCopyAvailableAndStoresStaffDecision(): void
    {
        /** @var AuditEventRepositoryInterface&MockObject $audit */
        $audit = $this->createMock(AuditEventRepositoryInterface::class);
        $audit->expects(self::once())->method('record')->with(self::callback(
            static fn (AuditEvent $event): bool => $event->actorUserId === 19
                && $event->type === AuditEventType::RETURNED
                && $event->transactionId === 1
                && $event->borrowingItemId === 1,
        ));
        $repository = new PdoReturnApprovalRepository($this->pdo, $audit);

        self::assertTrue($repository->decide('borrower_item', 1, 'approve', 19, 10.0, ''));
        self::assertFalse($repository->decide('borrower_item', 1, 'approve', 19, 10.0, ''));

        $itemStatement = $this->pdo->query('SELECT return_date, return_status, return_decided_by, fine_amount, status FROM borrowing_items WHERE id = 1');
        $copyStatement = $this->pdo->query('SELECT status, return_date FROM book_copies WHERE id = 1');
        self::assertNotFalse($itemStatement);
        self::assertNotFalse($copyStatement);
        $item = $itemStatement->fetch(PDO::FETCH_ASSOC);
        $copy = $copyStatement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($item);
        self::assertIsArray($copy);
        self::assertNotNull($item['return_date']);
        self::assertSame('none', $item['return_status']);
        self::assertEquals(19, $item['return_decided_by']);
        self::assertSame('Returned', $item['status']);
        self::assertEquals(10.0, $item['fine_amount']);
        self::assertSame('Available', $copy['status']);
        self::assertNotNull($copy['return_date']);
        $transactionStatement = $this->pdo->query('SELECT status, return_decided_by FROM borrowing_transactions WHERE id = 1');
        self::assertNotFalse($transactionStatement);
        $transaction = $transactionStatement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($transaction);
        self::assertSame('Returned', $transaction['status']);
        self::assertEquals(19, $transaction['return_decided_by']);
    }

    public function testRejectingLegacyLoanKeepsItActiveAndStoresReason(): void
    {
        $repository = new PdoReturnApprovalRepository($this->pdo);

        self::assertTrue($repository->decide('legacy_borrowing', 2, 'reject', 19, 0.0, 'Book not received.'));

        $loanStatement = $this->pdo->query('SELECT return_date, return_status, return_decided_by, return_decision_note, status FROM borrowing WHERE id = 2');
        self::assertNotFalse($loanStatement);
        $loan = $loanStatement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($loan);
        self::assertNull($loan['return_date']);
        self::assertSame('rejected', $loan['return_status']);
        self::assertEquals(19, $loan['return_decided_by']);
        self::assertSame('Book not received.', $loan['return_decision_note']);
        self::assertSame('Borrowed', $loan['status']);
        $bookStatement = $this->pdo->query('SELECT status FROM books WHERE id = 2');
        self::assertNotFalse($bookStatement);
        self::assertSame('Borrowed', $bookStatement->fetchColumn());
    }

    public function testApprovingGuestReturnMakesBookAvailable(): void
    {
        $repository = new PdoReturnApprovalRepository($this->pdo);

        self::assertTrue($repository->decide('guest', 3, 'approve', 19, 0.0, 'Received at desk.'));

        $requestStatement = $this->pdo->query('SELECT return_date, request_status, return_decided_by, return_decision_note FROM visitor_borrowing WHERE id = 3');
        self::assertNotFalse($requestStatement);
        $request = $requestStatement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($request);
        self::assertNotNull($request['return_date']);
        self::assertSame('Returned', $request['request_status']);
        self::assertEquals(19, $request['return_decided_by']);
        self::assertSame('Received at desk.', $request['return_decision_note']);
        $bookStatement = $this->pdo->query('SELECT status FROM books WHERE id = 1');
        self::assertNotFalse($bookStatement);
        self::assertSame('Available', $bookStatement->fetchColumn());
    }
}
