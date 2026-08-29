<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Application\DTO\BulkBorrowItem;
use App\Application\DTO\BulkBorrowRequest;
use App\Domain\Auth\Role;
use App\Infrastructure\Persistence\PdoBorrowingRepository;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoBorrowingRepositoryTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE books (' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT, barcode VARCHAR(50) NOT NULL, title VARCHAR(200) NOT NULL, ' .
            'author VARCHAR(150), status VARCHAR(20) NOT NULL, due_date DATE, return_date DATE, deleted_at DATETIME)'
        );
        $this->pdo->exec(
            'CREATE TABLE borrowing (' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT, transaction_code VARCHAR(40) NOT NULL, user_id INTEGER NOT NULL, ' .
            'book_id INTEGER NOT NULL, processed_by INTEGER, approval_status VARCHAR(20) NOT NULL, borrow_date DATETIME, ' .
            'due_date DATE NOT NULL, return_date DATETIME, status VARCHAR(20) NOT NULL, fine_amount DECIMAL(8,2) NOT NULL DEFAULT 0)'
        );
        $this->pdo->exec(
            'CREATE TABLE book_titles (' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT, title VARCHAR(200) NOT NULL, quantity INTEGER NOT NULL DEFAULT 0)'
        );
        $this->pdo->exec(
            'CREATE TABLE book_copies (' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT, title_id INTEGER NOT NULL, barcode VARCHAR(50) NOT NULL UNIQUE, ' .
            'status VARCHAR(20) NOT NULL, deleted_at DATETIME, due_date DATE, return_date DATE)'
        );
        $this->pdo->exec(
            'CREATE TABLE borrowing_transactions (' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT, transaction_code VARCHAR(40) NOT NULL UNIQUE, user_id INTEGER NOT NULL, ' .
            'processed_by INTEGER, approval_status VARCHAR(20) NOT NULL, borrow_date DATETIME NOT NULL, due_date DATE NOT NULL, ' .
            'return_date DATETIME, status VARCHAR(20) NOT NULL, fine_amount DECIMAL(8,2) NOT NULL DEFAULT 0, requested_at DATETIME, ' .
            'approved_at DATETIME, approved_by INTEGER)'
        );
        $this->pdo->exec(
            'CREATE TABLE borrowing_items (' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT, transaction_id INTEGER NOT NULL, copy_id INTEGER NOT NULL, ' .
            'return_date DATETIME, status VARCHAR(20) NOT NULL, fine_amount DECIMAL(8,2) NOT NULL DEFAULT 0)'
        );
        $this->pdo->exec(
            "INSERT INTO books (barcode, title, author, status) VALUES ('BK-1', 'Clean Code', 'Robert Martin', 'Available')"
        );
        $this->pdo->exec("INSERT INTO book_titles (title, quantity) VALUES ('Clean Code', 3), ('Algorithms', 1)");
        $this->pdo->exec("INSERT INTO book_copies (title_id, barcode, status) VALUES (1, 'COPY-1', 'Available'), (1, 'COPY-2', 'Available'), (1, 'COPY-3', 'Available'), (2, 'COPY-4', 'Available')");
    }

    public function testCreateLoanPreservesBorrowingColumnsAndMarksBookBorrowed(): void
    {
        $repository = new PdoBorrowingRepository($this->pdo);

        $id = $repository->createLoan(
            7,
            1,
            'S2B-20260828-ABC123',
            new DateTimeImmutable('2026-09-04'),
            'Borrowed',
            'approved',
        );

        $statement = $this->pdo->prepare('SELECT * FROM borrowing WHERE id = :id');
        $statement->execute(['id' => $id]);
        $loan = $statement->fetch(PDO::FETCH_ASSOC);
        $bookStatement = $this->pdo->query('SELECT status FROM books WHERE id = 1');
        self::assertNotFalse($bookStatement);
        $book = $bookStatement->fetchColumn();

        self::assertIsArray($loan);
        self::assertSame('S2B-20260828-ABC123', $loan['transaction_code']);
        self::assertSame('approved', $loan['approval_status']);
        self::assertSame('Borrowed', $loan['status']);
        self::assertSame('2026-09-04', $loan['due_date']);
        self::assertSame('Borrowed', $book);
    }

    public function testCompleteReturnPreservesFineAndMakesBookAvailable(): void
    {
        $repository = new PdoBorrowingRepository($this->pdo);
        $id = $repository->createLoan(
            7,
            1,
            'S2B-20260828-RETURN',
            new DateTimeImmutable('2026-09-04'),
            'Borrowed',
            'approved',
        );

        $repository->completeReturn($id, 1, 40.0);

        $statement = $this->pdo->prepare('SELECT * FROM borrowing WHERE id = :id');
        $statement->execute(['id' => $id]);
        $loan = $statement->fetch(PDO::FETCH_ASSOC);
        $bookStatement = $this->pdo->query('SELECT status, return_date FROM books WHERE id = 1');
        self::assertNotFalse($bookStatement);
        $book = $bookStatement->fetch(PDO::FETCH_ASSOC);

        self::assertIsArray($loan);
        self::assertNotNull($loan['return_date']);
        self::assertSame('Returned', $loan['status']);
        self::assertEquals(40.0, $loan['fine_amount']);
        self::assertIsArray($book);
        self::assertSame('Available', $book['status']);
        self::assertNotNull($book['return_date']);
    }

    public function testActiveBorrowingCountIncludesPendingRequestsThatConsumeCapacity(): void
    {
        $this->pdo->exec(
            "INSERT INTO borrowing (transaction_code, user_id, book_id, approval_status, borrow_date, due_date, return_date, status, fine_amount) VALUES
            ('PENDING-1', 7, 1, 'pending', CURRENT_TIMESTAMP, '2026-09-04', NULL, 'Pending', 0),
            ('PENDING-2', 7, 1, 'pending', CURRENT_TIMESTAMP, '2026-09-04', NULL, 'Pending', 0),
            ('PENDING-3', 7, 1, 'pending', CURRENT_TIMESTAMP, '2026-09-04', NULL, 'Pending', 0),
            ('REJECTED-1', 7, 1, 'rejected', CURRENT_TIMESTAMP, '2026-09-04', NULL, 'Pending', 0),
            ('RETURNED-1', 7, 1, 'approved', CURRENT_TIMESTAMP, '2026-09-04', CURRENT_TIMESTAMP, 'Returned', 0)"
        );

        $repository = new PdoBorrowingRepository($this->pdo);

        self::assertSame(3, $repository->activeApprovedCount(7));
    }

    public function testCreateBulkTransactionReservesEverySelectedCopyUnderOneCode(): void
    {
        $repository = new PdoBorrowingRepository($this->pdo);

        $result = $repository->createBulkTransaction(
            new BulkBorrowRequest(7, Role::STUDENT, [
                new BulkBorrowItem(1, 2, ['COPY-1', 'COPY-2']),
                new BulkBorrowItem(2, 1),
            ]),
            new DateTimeImmutable('2026-09-04'),
            'S2B-20260828-BULK01',
            'Pending',
            'pending',
        );

        self::assertSame('S2B-20260828-BULK01', $result['transaction_code']);
        self::assertSame(3, $result['copy_count']);
        self::assertSame(2, $result['title_count']);
        self::assertSame(3, (int) $this->pdo->query("SELECT COUNT(*) FROM borrowing_items WHERE transaction_id = 1")->fetchColumn());
        self::assertSame(3, (int) $this->pdo->query("SELECT COUNT(*) FROM book_copies WHERE status = 'Reserved'")->fetchColumn());
    }

    public function testBulkTransactionRollsBackWhenOneTitleCannotSupplyItsQuantity(): void
    {
        $repository = new PdoBorrowingRepository($this->pdo);

        $this->expectException(\RuntimeException::class);
        try {
            $repository->createBulkTransaction(
                new BulkBorrowRequest(7, Role::STUDENT, [
                    new BulkBorrowItem(1, 2),
                    new BulkBorrowItem(2, 2),
                ]),
                new DateTimeImmutable('2026-09-04'),
                'S2B-20260828-ROLLBK',
                'Pending',
                'pending',
            );
        } finally {
            self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM borrowing_transactions')->fetchColumn());
            self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM borrowing_items')->fetchColumn());
            self::assertSame(4, (int) $this->pdo->query("SELECT COUNT(*) FROM book_copies WHERE status = 'Available'")->fetchColumn());
        }
    }

    public function testReservedCopiesCannotBeAllocatedAgain(): void
    {
        $repository = new PdoBorrowingRepository($this->pdo);
        $repository->createBulkTransaction(
            new BulkBorrowRequest(7, Role::STUDENT, [new BulkBorrowItem(1, 3)]),
            new DateTimeImmutable('2026-09-04'),
            'S2B-20260828-FIRST',
            'Pending',
            'pending',
        );

        $this->expectException(\RuntimeException::class);
        $repository->createBulkTransaction(
            new BulkBorrowRequest(8, Role::STUDENT, [new BulkBorrowItem(1, 1)]),
            new DateTimeImmutable('2026-09-04'),
            'S2B-20260828-SECOND',
            'Pending',
            'pending',
        );
    }

    public function testBorrowingAUsedCopyBarcodeFallsBackToAnotherAvailableCopyOfTheTitle(): void
    {
        $this->pdo->exec("UPDATE book_copies SET status = 'Borrowed' WHERE barcode = 'COPY-1'");
        $repository = new PdoBorrowingRepository($this->pdo);

        $result = $repository->createBulkTransaction(
            new BulkBorrowRequest(8, Role::STUDENT, [new BulkBorrowItem(1, 1, ['COPY-1'])]),
            new DateTimeImmutable('2026-09-04'),
            'S2B-20260828-FALLBK',
            'Pending',
            'pending',
        );

        self::assertSame(1, $result['copy_count']);
        self::assertSame('Reserved', $this->pdo->query("SELECT status FROM book_copies WHERE barcode = 'COPY-2'")->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM borrowing_items WHERE transaction_id = 1 AND copy_id = 2')->fetchColumn());
    }

    public function testMixedPinnedAndFallbackCopiesAreAllocatedWithoutDuplicatingThePinnedCopy(): void
    {
        $repository = new PdoBorrowingRepository($this->pdo);

        $result = $repository->createBulkTransaction(
            new BulkBorrowRequest(8, Role::TEACHER, [new BulkBorrowItem(1, 2, ['COPY-1'])]),
            new DateTimeImmutable('2026-09-04'),
            'S2B-20260828-MIXED',
            'Pending',
            'pending',
        );

        self::assertSame(2, $result['copy_count']);
        self::assertSame(
            [1, 2],
            $this->pdo->query('SELECT copy_id FROM borrowing_items WHERE transaction_id = 1 ORDER BY copy_id')->fetchAll(PDO::FETCH_COLUMN),
        );
    }

    public function testBulkTransactionSupportsTransactionAndCopyReturns(): void
    {
        $repository = new PdoBorrowingRepository($this->pdo);
        $repository->createBulkTransaction(
            new BulkBorrowRequest(7, Role::STUDENT, [new BulkBorrowItem(1, 2)]),
            new DateTimeImmutable('2026-09-04'),
            'S2B-20260828-RETURNB',
            'Borrowed',
            'approved',
        );

        $loans = $repository->activeByTransaction(7, 'S2B-20260828-RETURNB');
        self::assertCount(2, $loans);
        $repository->completeReturn($loans[0]->id(), $loans[0]->bookId(), 0.0);
        self::assertSame('Available', $this->pdo->query("SELECT status FROM book_copies WHERE id = {$loans[0]->bookId()}")->fetchColumn());
        self::assertCount(1, $repository->activeByTransaction(7, 'S2B-20260828-RETURNB'));
    }
}
