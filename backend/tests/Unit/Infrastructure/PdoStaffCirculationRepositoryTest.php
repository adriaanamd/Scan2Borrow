<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Application\DTO\CopyHistoryResult;
use App\Infrastructure\Persistence\AuditEventRepositoryInterface;
use App\Infrastructure\Persistence\PdoStaffCirculationRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PdoStaffCirculationRepositoryTest extends TestCase
{
    public function testNormalizedLookupReturnsBorrowerDetailsAndActiveCopies(): void
    {
        $pdo = $this->normalizedPdo();
        $pdo->exec("INSERT INTO borrowing_transactions (id, transaction_code, user_id, processed_by, approval_status, borrow_date, due_date, status, fine_amount) VALUES (31, 'S2B-ACTIVE', 7, 19, 'approved', '2026-08-20 10:00:00', '2026-08-28', 'Borrowed', 0)");
        $pdo->exec("INSERT INTO borrowing_items (id, transaction_id, copy_id, status, fine_amount) VALUES (55, 31, 11, 'Borrowed', 0)");

        $result = (new PdoStaffCirculationRepository($pdo))->findBorrowerByBarcode('STU-001');

        self::assertIsArray($result);
        self::assertSame('Ada Lovelace', $result['borrower']['name']);
        self::assertSame(1, $result['borrower']['active_loans']);
        self::assertSame('COPY-001', $result['active_loans'][0]['copy_barcode']);
        self::assertSame(55, $result['active_loans'][0]['id']);
    }

    public function testLegacyBorrowingIsUsedUntilNormalizedTransactionsExist(): void
    {
        $pdo = $this->normalizedPdo();
        $pdo->exec('CREATE TABLE books (id INTEGER PRIMARY KEY, barcode TEXT UNIQUE, title TEXT, author TEXT, status TEXT, due_date TEXT, return_date TEXT, deleted_at TEXT)');
        $pdo->exec('CREATE TABLE borrowing (id INTEGER PRIMARY KEY AUTOINCREMENT, transaction_code TEXT UNIQUE, user_id INTEGER, book_id INTEGER, processed_by INTEGER, approval_status TEXT, borrow_date TEXT, due_date TEXT, return_date TEXT, return_status TEXT DEFAULT \'none\', return_decided_at TEXT, return_decided_by INTEGER, return_decision_note TEXT, status TEXT, fine_amount NUMERIC)');
        $pdo->exec("INSERT INTO books (id, barcode, title, author, status, due_date, deleted_at) VALUES (21, 'LEGACY-001', 'Legacy Copy', 'Ada Author', 'Borrowed', '2026-09-04', NULL)");
        $pdo->exec("INSERT INTO borrowing (transaction_code, user_id, book_id, approval_status, borrow_date, due_date, status, fine_amount) VALUES ('LEGACY-TX', 7, 21, 'approved', '2026-08-20 10:00:00', '2026-09-04', 'Borrowed', 0)");

        $result = (new PdoStaffCirculationRepository($pdo))->findBorrowerByBarcode('STU-001');

        self::assertIsArray($result);
        self::assertSame('LEGACY-001', $result['active_loans'][0]['copy_barcode']);
        self::assertSame(1, $result['borrower']['borrow_limit_count']);
    }

    public function testLegacyBookBarcodeCanStillBeBorrowedWhenNormalizedInventoryExists(): void
    {
        $pdo = $this->normalizedPdo();
        $pdo->exec('CREATE TABLE books (id INTEGER PRIMARY KEY, barcode TEXT UNIQUE, title TEXT, author TEXT, status TEXT, due_date TEXT, return_date TEXT, deleted_at TEXT)');
        $pdo->exec('CREATE TABLE borrowing (id INTEGER PRIMARY KEY AUTOINCREMENT, transaction_code TEXT UNIQUE, user_id INTEGER, book_id INTEGER, processed_by INTEGER, approval_status TEXT, borrow_date TEXT, due_date TEXT, return_date TEXT, return_status TEXT DEFAULT \'none\', return_decided_at TEXT, return_decided_by INTEGER, return_decision_note TEXT, status TEXT, fine_amount NUMERIC)');
        $pdo->exec("INSERT INTO books (id, barcode, title, author, status, due_date, deleted_at) VALUES (21, 'LEGACY-001', 'Legacy Copy', 'Ada Author', 'Available', NULL, NULL)");

        $repository = new PdoStaffCirculationRepository($pdo);
        $result = $repository->createDirectBorrow(
            7,
            19,
            ['LEGACY-001'],
            new DateTimeImmutable('2026-09-04'),
            'S2B-20260828-LEGACY-MIXED',
        );

        self::assertSame(1, $result['borrowed']);
        self::assertSame('Borrowed', $this->scalar($pdo, "SELECT status FROM books WHERE barcode = 'LEGACY-001'"));
        self::assertSame(0, $this->integerScalar($pdo, 'SELECT COUNT(*) FROM borrowing_transactions'));
        self::assertSame(1, $this->integerScalar($pdo, 'SELECT COUNT(*) FROM borrowing'));

        $loanId = $this->integerScalar($pdo, "SELECT id FROM borrowing WHERE transaction_code = 'S2B-20260828-LEGACY-MIXED'");
        $returned = $repository->processDirectReturns(
            7,
            19,
            [$loanId],
            ['LEGACY-001'],
            new DateTimeImmutable('2026-09-04 10:00:00'),
            20.0,
        );

        self::assertSame(1, $returned['returned']);
        self::assertSame('Available', $this->scalar($pdo, "SELECT status FROM books WHERE barcode = 'LEGACY-001'"));
    }

    public function testLookupMergesActiveLoansAcrossBothStoresWithoutDuplicatingCopies(): void
    {
        $pdo = $this->normalizedPdo();
        $pdo->exec("INSERT INTO borrowing_transactions (id, transaction_code, user_id, processed_by, approval_status, borrow_date, due_date, status, fine_amount) VALUES (31, 'S2B-NORMALIZED', 7, 19, 'approved', '2026-08-20 10:00:00', '2026-08-28', 'Borrowed', 0)");
        $pdo->exec("INSERT INTO borrowing_items (id, transaction_id, copy_id, status, fine_amount) VALUES (55, 31, 11, 'Borrowed', 0)");
        $pdo->exec('CREATE TABLE books (id INTEGER PRIMARY KEY, barcode TEXT UNIQUE, title TEXT, author TEXT, status TEXT, due_date TEXT, return_date TEXT, deleted_at TEXT)');
        $pdo->exec('CREATE TABLE borrowing (id INTEGER PRIMARY KEY AUTOINCREMENT, transaction_code TEXT UNIQUE, user_id INTEGER, book_id INTEGER, processed_by INTEGER, approval_status TEXT, borrow_date TEXT, due_date TEXT, return_date TEXT, return_status TEXT DEFAULT \'none\', return_decided_at TEXT, return_decided_by INTEGER, return_decision_note TEXT, status TEXT, fine_amount NUMERIC)');
        $pdo->exec("INSERT INTO books (id, barcode, title, author, status, due_date, deleted_at) VALUES (21, 'LEGACY-001', 'Legacy Copy', 'Ada Author', 'Borrowed', '2026-09-04', NULL)");
        $pdo->exec("INSERT INTO borrowing (transaction_code, user_id, book_id, approval_status, borrow_date, due_date, status, fine_amount) VALUES ('LEGACY-TX', 7, 21, 'approved', '2026-08-20 10:00:00', '2026-09-04', 'Borrowed', 0)");

        $result = (new PdoStaffCirculationRepository($pdo))->findBorrowerByBarcode('STU-001');

        self::assertIsArray($result);
        self::assertCount(2, $result['active_loans']);
        self::assertSame(2, $result['borrower']['borrow_limit_count']);
        self::assertSame(['COPY-001', 'LEGACY-001'], array_column($result['active_loans'], 'copy_barcode'));
    }

    public function testNormalizedDirectBorrowApprovesCopiesAndRecordsAudit(): void
    {
        $pdo = $this->normalizedPdo();
        $audit = new StaffCirculationAuditRecorder();
        $repository = new PdoStaffCirculationRepository($pdo, $audit);

        $result = $repository->createDirectBorrow(
            7,
            19,
            ['COPY-001', 'COPY-002'],
            new DateTimeImmutable('2026-09-04'),
            'S2B-20260828-DIRECT',
        );

        self::assertSame('S2B-20260828-DIRECT', $result['transaction_code']);
        self::assertSame(2, $result['borrowed']);
        self::assertSame(1, $this->integerScalar($pdo, 'SELECT COUNT(*) FROM borrowing_transactions WHERE approval_status = \'approved\''));
        self::assertSame(2, $this->integerScalar($pdo, "SELECT COUNT(*) FROM borrowing_items WHERE status = 'Borrowed'"));
        self::assertSame(2, $this->integerScalar($pdo, "SELECT COUNT(*) FROM book_copies WHERE status = 'Borrowed' AND due_date = '2026-09-04'"));
        self::assertSame(19, $this->scalar($pdo, "SELECT processed_by FROM borrowing_transactions WHERE transaction_code = 'S2B-20260828-DIRECT'"));
        self::assertSame(19, $this->scalar($pdo, "SELECT approved_by FROM borrowing_transactions WHERE transaction_code = 'S2B-20260828-DIRECT'"));
        self::assertCount(2, $audit->events);
        self::assertSame(AuditEventType::LOANED, $audit->events[0]->type);
    }

    public function testNormalizedBorrowRollsBackWhenAnyScannedCopyIsUnavailable(): void
    {
        $pdo = $this->normalizedPdo();
        $pdo->exec("UPDATE book_copies SET status = 'Borrowed' WHERE barcode = 'COPY-002'");
        $repository = new PdoStaffCirculationRepository($pdo);

        $this->expectException(RuntimeException::class);
        try {
            $repository->createDirectBorrow(
                7,
                19,
                ['COPY-001', 'COPY-002'],
                new DateTimeImmutable('2026-09-04'),
                'S2B-20260828-ROLLBK',
            );
        } finally {
            self::assertSame(0, $this->integerScalar($pdo, 'SELECT COUNT(*) FROM borrowing_transactions'));
            self::assertSame('Available', $this->scalar($pdo, "SELECT status FROM book_copies WHERE barcode = 'COPY-001'"));
        }
    }

    public function testNormalizedDirectReturnIsBorrowerScopedAndCalculatesFine(): void
    {
        $pdo = $this->normalizedPdo();
        $audit = new StaffCirculationAuditRecorder();
        $repository = new PdoStaffCirculationRepository($pdo, $audit);
        $repository->createDirectBorrow(7, 19, ['COPY-001'], new DateTimeImmutable('2026-08-28'), 'S2B-20260820-RETURN');
        $loanId = $this->integerScalar($pdo, 'SELECT id FROM borrowing_items LIMIT 1');
        self::assertSame('2026-08-28', $this->scalar($pdo, 'SELECT due_date FROM borrowing_transactions LIMIT 1'));

        $this->expectException(InvalidArgumentException::class);
        try {
            $repository->processDirectReturns(8, 19, [$loanId], ['COPY-001'], new DateTimeImmutable('2026-09-01 10:00:00'), 20.0);
        } finally {
            self::assertNull($this->scalar($pdo, 'SELECT return_date FROM borrowing_items WHERE id = ' . $loanId));
        }
    }

    public function testNormalizedDirectReturnReleasesCopyAndClosesTransaction(): void
    {
        $pdo = $this->normalizedPdo();
        $audit = new StaffCirculationAuditRecorder();
        $repository = new PdoStaffCirculationRepository($pdo, $audit);
        $repository->createDirectBorrow(7, 19, ['COPY-001'], new DateTimeImmutable('2026-08-28'), 'S2B-20260820-RETURN');
        $loanId = $this->integerScalar($pdo, 'SELECT id FROM borrowing_items LIMIT 1');

        $result = $repository->processDirectReturns(
            7,
            19,
            [$loanId],
            ['COPY-001'],
            new DateTimeImmutable('2026-09-01 10:00:00'),
            20.0,
        );

        self::assertSame(1, $result['returned']);
        self::assertSame(60.0, $result['total_fine']);
        self::assertNotNull($this->scalar($pdo, 'SELECT return_date FROM borrowing_items WHERE id = ' . $loanId));
        self::assertSame('Returned', $this->scalar($pdo, 'SELECT status FROM borrowing_items WHERE id = ' . $loanId));
        self::assertSame('Available', $this->scalar($pdo, "SELECT status FROM book_copies WHERE barcode = 'COPY-001'"));
        self::assertSame('Returned', $this->scalar($pdo, "SELECT status FROM borrowing_transactions WHERE transaction_code = 'S2B-20260820-RETURN'"));
        self::assertSame(AuditEventType::RETURNED, $audit->events[1]->type);
    }

    public function testLegacyLookupBorrowAndReturnRemainSupported(): void
    {
        $pdo = $this->legacyPdo();
        $repository = new PdoStaffCirculationRepository($pdo);

        $lookup = $repository->findBorrowerByBarcode('STU-001');
        self::assertIsArray($lookup);
        self::assertSame([], $lookup['active_loans']);

        $borrowed = $repository->createDirectBorrow(7, 19, ['BOOK-001'], new DateTimeImmutable('2026-09-04'), 'S2B-20260828-LEGACY');
        self::assertSame(1, $borrowed['borrowed']);
        self::assertSame('Borrowed', $this->scalar($pdo, "SELECT status FROM books WHERE barcode = 'BOOK-001'"));
        self::assertSame(19, $this->scalar($pdo, "SELECT processed_by FROM borrowing WHERE transaction_code = 'S2B-20260828-LEGACY'"));

        $loanId = $this->integerScalar($pdo, "SELECT id FROM borrowing WHERE transaction_code = 'S2B-20260828-LEGACY'");
        $returned = $repository->processDirectReturns(7, 19, [$loanId], ['BOOK-001'], new DateTimeImmutable('2026-09-04 10:00:00'), 20.0);
        self::assertSame(1, $returned['returned']);
        self::assertSame('Available', $this->scalar($pdo, "SELECT status FROM books WHERE barcode = 'BOOK-001'"));
    }

    private function scalar(PDO $pdo, string $query): mixed
    {
        $statement = $pdo->query($query);
        if ($statement === false) {
            throw new RuntimeException('Test query failed.');
        }

        return $statement->fetchColumn();
    }

    private function integerScalar(PDO $pdo, string $query): int
    {
        $value = $this->scalar($pdo, $query);
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        throw new RuntimeException('Expected an integer test result.');
    }

    private function normalizedPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, barcode TEXT, firstname TEXT, middlename TEXT, lastname TEXT, email TEXT, contact_no TEXT, role TEXT, status TEXT, borrowing_status TEXT, department TEXT, position TEXT, course TEXT, year_level TEXT, photo TEXT)');
        $pdo->exec('CREATE TABLE book_titles (id INTEGER PRIMARY KEY, title TEXT, author TEXT)');
        $pdo->exec('CREATE TABLE book_copies (id INTEGER PRIMARY KEY, title_id INTEGER, barcode TEXT UNIQUE, status TEXT, deleted_at TEXT, due_date TEXT, return_date TEXT)');
        $pdo->exec('CREATE TABLE borrowing_transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, transaction_code TEXT UNIQUE, user_id INTEGER, processed_by INTEGER, approval_status TEXT, borrow_date TEXT, due_date TEXT, return_date TEXT, return_status TEXT DEFAULT \'none\', return_decided_at TEXT, return_decided_by INTEGER, return_decision_note TEXT, status TEXT, fine_amount NUMERIC, requested_at TEXT, approved_at TEXT, approved_by INTEGER)');
        $pdo->exec('CREATE TABLE borrowing_items (id INTEGER PRIMARY KEY AUTOINCREMENT, transaction_id INTEGER, copy_id INTEGER, return_date TEXT, return_status TEXT DEFAULT \'none\', return_decided_at TEXT, return_decided_by INTEGER, return_decision_note TEXT, status TEXT, fine_amount NUMERIC)');
        $pdo->exec("INSERT INTO users (id, barcode, firstname, lastname, role, status, borrowing_status, course, year_level) VALUES (7, 'STU-001', 'Ada', 'Lovelace', 'student', 'active', 'active', 'Computer Science', '4'), (19, 'LIB-019', 'Librarian', 'One', 'librarian', 'active', 'active', '', '')");
        $pdo->exec("INSERT INTO book_titles (id, title, author) VALUES (4, 'Clean Code', 'Robert C. Martin'), (5, 'Refactoring', 'Martin Fowler')");
        $pdo->exec("INSERT INTO book_copies (id, title_id, barcode, status, deleted_at) VALUES (11, 4, 'COPY-001', 'Available', NULL), (12, 5, 'COPY-002', 'Available', NULL)");

        return $pdo;
    }

    private function legacyPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, barcode TEXT, firstname TEXT, middlename TEXT, lastname TEXT, email TEXT, contact_no TEXT, role TEXT, status TEXT, borrowing_status TEXT, department TEXT, position TEXT, course TEXT, year_level TEXT, photo TEXT)');
        $pdo->exec('CREATE TABLE books (id INTEGER PRIMARY KEY, barcode TEXT UNIQUE, title TEXT, author TEXT, status TEXT, due_date TEXT, return_date TEXT, deleted_at TEXT)');
        $pdo->exec('CREATE TABLE borrowing (id INTEGER PRIMARY KEY AUTOINCREMENT, transaction_code TEXT UNIQUE, user_id INTEGER, book_id INTEGER, processed_by INTEGER, approval_status TEXT, borrow_date TEXT, due_date TEXT, return_date TEXT, return_status TEXT DEFAULT \'none\', return_decided_at TEXT, return_decided_by INTEGER, return_decision_note TEXT, status TEXT, fine_amount NUMERIC)');
        $pdo->exec("INSERT INTO users (id, barcode, firstname, lastname, role, status, borrowing_status) VALUES (7, 'STU-001', 'Ada', 'Lovelace', 'student', 'active', 'active'), (19, 'LIB-019', 'Librarian', 'One', 'librarian', 'active', 'active')");
        $pdo->exec("INSERT INTO books (id, barcode, title, author, status, deleted_at) VALUES (21, 'BOOK-001', 'Clean Code', 'Robert C. Martin', 'Available', NULL)");

        return $pdo;
    }
}

final class StaffCirculationAuditRecorder implements AuditEventRepositoryInterface
{
    /** @var list<AuditEvent> */
    public array $events = [];

    public function record(AuditEvent $event): void
    {
        $this->events[] = $event;
    }

    public function findCopyHistory(string $barcode): ?CopyHistoryResult
    {
        return null;
    }
}
