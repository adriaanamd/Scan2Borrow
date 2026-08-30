<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Domain\Renewal\RenewalStatus;
use App\Infrastructure\Persistence\PdoRenewalRepository;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoRenewalRepositoryTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, firstname TEXT, lastname TEXT)');
        $this->pdo->exec('CREATE TABLE book_titles (id INTEGER PRIMARY KEY, title TEXT, author TEXT)');
        $this->pdo->exec('CREATE TABLE book_copies (id INTEGER PRIMARY KEY, title_id INTEGER, barcode TEXT)');
        $this->pdo->exec('CREATE TABLE borrowing_transactions (id INTEGER PRIMARY KEY, transaction_code TEXT, user_id INTEGER, due_date TEXT)');
        $this->pdo->exec('CREATE TABLE borrowing_items (id INTEGER PRIMARY KEY, transaction_id INTEGER, copy_id INTEGER, return_date TEXT, status TEXT)');
        $this->pdo->exec('CREATE TABLE renewal_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, loan_id INTEGER, user_id INTEGER, original_due_date TEXT, requested_due_date TEXT, status TEXT, reason TEXT, decision_note TEXT, requested_at TEXT, decided_at TEXT, approved_by INTEGER, created_at TEXT, updated_at TEXT)');
        $this->pdo->exec("INSERT INTO users VALUES (7, 'Ada', 'Lovelace'), (2, 'Librarian', 'One')");
        $this->pdo->exec("INSERT INTO book_titles VALUES (4, 'Clean Code', 'Robert C. Martin')");
        $this->pdo->exec("INSERT INTO book_copies VALUES (11, 4, 'COPY-11')");
        $this->pdo->exec("INSERT INTO borrowing_transactions VALUES (20, 'S2B-001', 7, '2026-08-30')");
        $this->pdo->exec("INSERT INTO borrowing_items VALUES (88, 20, 11, NULL, 'Borrowed')");
    }

    public function testCreateAndListForUserHydrateLoanContext(): void
    {
        $repository = new PdoRenewalRepository($this->pdo);
        $record = $repository->create(88, 7, new DateTimeImmutable('2026-08-30'), new DateTimeImmutable('2026-09-06'), 'Project deadline');

        self::assertSame('Clean Code', $record->title());
        self::assertCount(1, $repository->listForUser(7));
        self::assertSame(RenewalStatus::PENDING, $repository->listPending()[0]->status());
        self::assertTrue($repository->hasPendingForLoan(88, 7));
        self::assertFalse($repository->hasApprovedForLoan(88));
    }

    public function testApprovalUpdatesLoanDueDateAndAuditsLibrarian(): void
    {
        $repository = new PdoRenewalRepository($this->pdo);
        $record = $repository->create(88, 7, new DateTimeImmutable('2026-08-30'), new DateTimeImmutable('2026-09-06'), 'Project deadline');

        $approved = $repository->approve($record->id(), 2, 'Approved once.', new DateTimeImmutable('2026-08-30 12:00:00'));

        self::assertNotNull($approved);
        self::assertSame(RenewalStatus::APPROVED, $approved->status());
        self::assertSame('2026-09-06', $this->pdo->query('SELECT due_date FROM borrowing_transactions WHERE id = 20')->fetchColumn());
        self::assertTrue($repository->hasApprovedForLoan(88));
    }
}
