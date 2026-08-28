<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

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
            "INSERT INTO books (barcode, title, author, status) VALUES ('BK-1', 'Clean Code', 'Robert Martin', 'Available')"
        );
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
}
