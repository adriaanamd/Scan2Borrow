<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Infrastructure\Persistence\PdoStaffRepository;
use App\Infrastructure\Persistence\PdoBorrowerPortalRepository;
use App\Infrastructure\Persistence\PdoCirculationNotificationRepository;
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
        $this->pdo->exec('CREATE TABLE courses (id INTEGER PRIMARY KEY, name TEXT NOT NULL UNIQUE, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $this->pdo->exec('CREATE TABLE borrowing (id INTEGER PRIMARY KEY, transaction_code TEXT, user_id INTEGER, book_id INTEGER, approval_status TEXT, borrow_date TEXT, due_date TEXT, return_date TEXT, status TEXT, fine_amount NUMERIC, requested_at TEXT, approved_at TEXT, approved_by INTEGER)');
        $this->pdo->exec('CREATE TABLE visitor_borrowing (id INTEGER PRIMARY KEY, visitor_id INTEGER, book_id INTEGER, due_date TEXT, request_status TEXT, verification_photo TEXT, requested_at TEXT)');
        $this->pdo->exec('CREATE TABLE visitors (id INTEGER PRIMARY KEY, firstname TEXT, lastname TEXT, visitor_number TEXT, photo TEXT, id_barcode TEXT)');
        $this->pdo->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY, user_id INTEGER, type TEXT, title TEXT, message TEXT, related_id INTEGER, is_read INTEGER, created_at TEXT)');
        $this->pdo->exec('CREATE TABLE return_notifications (id INTEGER PRIMARY KEY, borrowing_id INTEGER, user_id INTEGER, book_id INTEGER, message TEXT, is_viewed INTEGER, viewed_at TEXT, created_at TEXT)');
        $this->pdo->exec("INSERT INTO books (id, barcode, title, author, category_name, status, floor_no, section_name, shelf_no, created_at) VALUES (1, 'BK-1', 'Clean Code', 'Martin', 'Computer Science', 'Available', '2', 'IT', 'A1', '2026-08-01')");
        $this->pdo->exec("INSERT INTO users VALUES (1, 'STAFF-1', 'Ada', 'Lovelace', 'Library', 'Librarian', NULL, NULL, 'ada@example.test', '09170000000', NULL, 'librarian', NULL, 'active')");
        $this->pdo->exec("INSERT INTO users VALUES (2, 'STU-1', 'Grace', 'Hopper', 'IT', NULL, 'CS', '4', 'grace@example.test', '09171111111', 'uploads/profile/grace.jpg', 'student', NULL, 'active')");
        $this->pdo->exec("INSERT INTO courses (id, name) VALUES (1, 'CS'), (2, 'BSA')");
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
        self::assertSame('uploads/profile/grace.jpg', $repository->pendingBorrowings()[0]['borrower_photo']);
    }

    public function testBorrowersAggregateActiveAndOverdueLoansPerUser(): void
    {
        $this->pdo->exec("INSERT INTO borrowing (id, transaction_code, user_id, book_id, approval_status, borrow_date, due_date, return_date, status, fine_amount) VALUES
            (2, 'TX-2', 2, 1, 'approved', '2026-08-01', '2026-08-08', NULL, 'Borrowed', 0),
            (3, 'TX-3', 2, 1, 'approved', '2026-07-01', '2026-07-08', NULL, 'Overdue', 10),
            (4, 'TX-4', 2, 1, 'approved', '2026-06-01', '2026-06-08', '2026-06-07', 'Returned', 0)");

        /** @var list<array{name: string, active_loans: int, overdue_loans: int}> $borrowers */
        $borrowers = (new PdoStaffRepository($this->pdo))->borrowers('');

        self::assertCount(1, $borrowers);
        self::assertSame('Grace Hopper', $borrowers[0]['name']);
        self::assertSame(3, $borrowers[0]['active_loans']);
        self::assertSame(1, $borrowers[0]['overdue_loans']);
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

    public function testLegacyDashboardRanksDistinctCourseBorrowersAndExcludesPendingRows(): void
    {
        $this->pdo->exec("INSERT INTO users VALUES (3, 'STU-2', 'Katherine', 'Johnson', 'Science', NULL, 'CS', '3', 'katherine@example.test', '09172222222', NULL, 'student', NULL, 'active')");
        $this->pdo->exec("INSERT INTO users VALUES (4, 'TEACH-1', 'Alan', 'Turing', 'Science', NULL, 'CS', '', 'alan@example.test', '09173333333', NULL, 'teacher', NULL, 'active')");
        $this->pdo->exec("INSERT INTO borrowing (id, transaction_code, user_id, book_id, approval_status, borrow_date, due_date, return_date, status, fine_amount) VALUES
            (2, 'TX-2', 2, 1, 'approved', '2026-08-01', '2026-08-08', '2026-08-08', 'Returned', 0),
            (3, 'TX-3', 2, 1, 'approved', '2026-08-02', '2026-08-09', NULL, 'Borrowed', 0),
            (4, 'TX-4', 3, 1, 'approved', '2026-08-03', '2026-08-10', NULL, 'Borrowed', 0),
            (5, 'TX-5', 3, 1, 'pending', '2026-08-04', '2026-08-11', NULL, 'Pending', 0),
            (6, 'TX-6', 4, 1, 'approved', '2026-08-05', '2026-08-12', NULL, 'Borrowed', 0)");

        $top = (new PdoStaffRepository($this->pdo))->dashboard()['overview']['top_course_borrowers'];

        self::assertSame(['rank' => 1, 'course' => 'CS', 'borrower_count' => 2], $top[0]);
        self::assertSame(['rank' => 2, 'course' => 'BSA', 'borrower_count' => 0], $top[1]);
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

    public function testLegacyTeacherApprovalCreatesTheSameNotificationType(): void
    {
        $this->pdo->exec("UPDATE users SET role = 'teacher' WHERE id = 2");
        $repository = new PdoStaffRepository($this->pdo, null, new PdoCirculationNotificationRepository($this->pdo));

        $repository->approveBorrowing(1, 1);

        self::assertSame('borrow_approved', $this->pdo->query('SELECT type FROM notifications WHERE user_id = 2')->fetchColumn());
    }

    private function normalizedApprovalFixture(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, barcode TEXT, firstname TEXT, middlename TEXT, lastname TEXT, department TEXT, position TEXT, course TEXT, year_level TEXT, photo TEXT, role TEXT)');
        $pdo->exec('CREATE TABLE book_titles (id INTEGER PRIMARY KEY, title TEXT, author TEXT, category_name TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE book_copies (id INTEGER PRIMARY KEY, title_id INTEGER, barcode TEXT, status TEXT, due_date TEXT, floor_no TEXT, deleted_at TEXT)');
        $pdo->exec('CREATE TABLE borrowing_transactions (id INTEGER PRIMARY KEY, transaction_code TEXT, user_id INTEGER, processed_by INTEGER, approval_status TEXT, borrow_date TEXT, due_date TEXT, return_date TEXT, status TEXT, fine_amount NUMERIC, requested_at TEXT, approved_at TEXT, approved_by INTEGER)');
        $pdo->exec('CREATE TABLE borrowing_items (id INTEGER PRIMARY KEY, transaction_id INTEGER, copy_id INTEGER, return_date TEXT, status TEXT, fine_amount NUMERIC)');
        $pdo->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY, user_id INTEGER, type TEXT, title TEXT, message TEXT, related_id INTEGER, is_read INTEGER, created_at TEXT)');
        $pdo->exec("INSERT INTO users VALUES (2, 'STU-1', 'Grace', NULL, 'Hopper', 'IT', NULL, 'CS', '4', 'uploads/profile/grace.jpg', 'student')");
        $pdo->exec("INSERT INTO book_titles VALUES (1, 'Clean Code', 'Martin', 'Computer Science', '2026-08-01')");
        $pdo->exec("INSERT INTO book_copies VALUES (1, 1, 'COPY-1', 'Reserved', '2026-09-10', '2', NULL)");
        $pdo->exec("INSERT INTO borrowing_transactions VALUES (1, 'TX-PENDING', 2, NULL, 'pending', '2026-09-03 10:00:00', '2026-09-10', NULL, 'Pending', 0, '2026-09-03 10:00:00', NULL, NULL)");
        $pdo->exec("INSERT INTO borrowing_items VALUES (1, 1, 1, NULL, 'Pending', 0)");

        return $pdo;
    }

    private function normalizedBulkApprovalFixture(): PDO
    {
        $pdo = $this->normalizedApprovalFixture();
        $pdo->exec("INSERT INTO book_titles VALUES (2, 'Refactoring', 'Martin Fowler', 'Computer Science', '2026-08-02')");
        $pdo->exec("INSERT INTO book_copies VALUES (2, 2, 'COPY-2', 'Reserved', '2026-09-10', '2', NULL)");
        $pdo->exec("INSERT INTO borrowing_items VALUES (2, 1, 2, NULL, 'Pending', 0)");

        return $pdo;
    }

    public function testNormalizedApprovalSynchronizesAllBorrowingStatusProjections(): void
    {
        $pdo = $this->normalizedApprovalFixture();
        $repository = new PdoStaffRepository($pdo);

        $repository->approveBorrowing(1, 99);

        $transaction = $pdo->query('SELECT approval_status, status FROM borrowing_transactions WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $itemStatus = $pdo->query('SELECT status, return_date FROM borrowing_items WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $copyStatus = $pdo->query('SELECT status FROM book_copies WHERE id = 1')->fetchColumn();
        self::assertSame(['approval_status' => 'approved', 'status' => 'Borrowed'], $transaction);
        self::assertSame('Borrowed', $itemStatus['status']);
        self::assertNull($itemStatus['return_date']);
        self::assertSame('Borrowed', $copyStatus);
        self::assertSame([], $repository->pendingBorrowings());

        $dashboard = (new PdoBorrowerPortalRepository($pdo))->dashboard(2);
        self::assertSame('Borrowed', $dashboard['current_loans'][0]['status']);
    }

    public function testNormalizedApprovalCreatesOneBorrowerNotification(): void
    {
        $pdo = $this->normalizedApprovalFixture();
        $repository = new PdoStaffRepository($pdo, null, new PdoCirculationNotificationRepository($pdo));

        $repository->approveBorrowing(1, 99);

        $row = $pdo->query('SELECT user_id, type, title, message, related_id, is_read FROM notifications WHERE user_id = 2')->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame(2, (int) $row['user_id']);
        self::assertSame('borrow_approved', $row['type']);
        self::assertSame('Borrowing request approved', $row['title']);
        self::assertStringContainsString('"Clean Code"', $row['message']);
        self::assertSame(1, (int) $row['related_id']);
        self::assertSame(0, (int) $row['is_read']);
    }

    public function testNormalizedBulkApprovalListsDistinctTitlesInOneNotification(): void
    {
        $pdo = $this->normalizedBulkApprovalFixture();
        $repository = new PdoStaffRepository($pdo, null, new PdoCirculationNotificationRepository($pdo));

        $repository->approveBorrowing(1, 99);

        $rows = $pdo->query("SELECT message FROM notifications WHERE user_id = 2 AND type = 'borrow_approved'")->fetchAll(PDO::FETCH_COLUMN);
        self::assertCount(1, $rows);
        self::assertStringContainsString('"Clean Code"', (string) $rows[0]);
        self::assertStringContainsString('"Refactoring"', (string) $rows[0]);
    }

    public function testRepeatedApprovalDoesNotDuplicateBorrowerNotification(): void
    {
        $pdo = $this->normalizedApprovalFixture();
        $repository = new PdoStaffRepository($pdo, null, new PdoCirculationNotificationRepository($pdo));

        $repository->approveBorrowing(1, 99);
        $repository->approveBorrowing(1, 99);

        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE type = 'borrow_approved'")->fetchColumn());
    }

    public function testNormalizedPendingBorrowingsIncludeBorrowerPhoto(): void
    {
        $repository = new PdoStaffRepository($this->normalizedApprovalFixture());

        $pending = $repository->pendingBorrowings();

        self::assertCount(1, $pending);
        self::assertSame('uploads/profile/grace.jpg', $pending[0]['borrower_photo']);
    }

    public function testNormalizedRejectionClosesItemsAndLeavesCopiesAvailable(): void
    {
        $pdo = $this->normalizedApprovalFixture();
        $repository = new PdoStaffRepository($pdo);

        $repository->rejectBorrowing(1, 99);

        $transaction = $pdo->query('SELECT approval_status, status FROM borrowing_transactions WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $item = $pdo->query('SELECT status, return_date FROM borrowing_items WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $copyStatus = $pdo->query('SELECT status FROM book_copies WHERE id = 1')->fetchColumn();
        self::assertSame(['approval_status' => 'rejected', 'status' => 'Returned'], $transaction);
        self::assertSame('Returned', $item['status']);
        self::assertNotNull($item['return_date']);
        self::assertSame('Available', $copyStatus);
        self::assertSame([], (new PdoBorrowerPortalRepository($pdo))->dashboard(2)['current_loans']);
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

    public function testNormalizedBorrowerHistoryExposesGroupedQuantities(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, barcode TEXT, firstname TEXT, lastname TEXT, department TEXT, position TEXT, course TEXT, year_level TEXT, email TEXT, contact_no TEXT, photo TEXT, role TEXT, status TEXT)');
        $pdo->exec('CREATE TABLE borrowing_transactions (id INTEGER PRIMARY KEY, transaction_code TEXT, user_id INTEGER, borrow_date TEXT, due_date TEXT, status TEXT)');
        $pdo->exec('CREATE TABLE borrowing_items (id INTEGER PRIMARY KEY, transaction_id INTEGER, copy_id INTEGER, return_date TEXT, status TEXT, fine_amount NUMERIC)');
        $pdo->exec('CREATE TABLE book_copies (id INTEGER PRIMARY KEY, title_id INTEGER, barcode TEXT)');
        $pdo->exec('CREATE TABLE book_titles (id INTEGER PRIMARY KEY, title TEXT, author TEXT)');
        $pdo->exec("INSERT INTO users VALUES (2, 'STU-1', 'Grace', 'Hopper', 'IT', NULL, 'CS', '4', 'grace@example.test', NULL, NULL, 'student', 'active')");
        $pdo->exec("INSERT INTO book_titles VALUES (1, 'Clean Code', 'Martin')");
        $pdo->exec("INSERT INTO book_copies VALUES (1, 1, 'COPY-1'), (2, 1, 'COPY-2')");
        $pdo->exec("INSERT INTO borrowing_transactions VALUES (1, 'TX-BULK', 2, '2026-08-20', '2026-08-27', 'Borrowed')");
        $pdo->exec("INSERT INTO borrowing_items VALUES (1, 1, 1, NULL, 'Borrowed', 0), (2, 1, 2, NULL, 'Borrowed', 0)");

        $details = (new PdoStaffRepository($pdo))->borrowerDetails(2);

        self::assertNotNull($details);
        self::assertSame(2, (int) $details['history'][0]['quantity']);
        self::assertSame(2, $details['summary']['active']);
    }

    public function testNormalizedDashboardUsesCopyQuantitiesAcrossStaffMetrics(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, barcode TEXT, firstname TEXT, lastname TEXT, role TEXT, department TEXT, position TEXT, course TEXT, year_level TEXT, photo TEXT, status TEXT)');
        $pdo->exec('CREATE TABLE book_titles (id INTEGER PRIMARY KEY, title TEXT, author TEXT, category_name TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE book_copies (id INTEGER PRIMARY KEY, title_id INTEGER, barcode TEXT, floor_no TEXT, section_name TEXT, shelf_no TEXT, status TEXT, deleted_at TEXT)');
        $pdo->exec('CREATE TABLE borrowing_transactions (id INTEGER PRIMARY KEY, transaction_code TEXT, user_id INTEGER, approval_status TEXT, borrow_date TEXT, due_date TEXT, status TEXT, requested_at TEXT)');
        $pdo->exec('CREATE TABLE borrowing_items (id INTEGER PRIMARY KEY, transaction_id INTEGER, copy_id INTEGER, return_date TEXT, status TEXT, fine_amount NUMERIC)');
        $pdo->exec("INSERT INTO users VALUES (1, 'STU-1', 'Grace', 'Hopper', 'student', 'IT', NULL, 'CS', '4', NULL, 'active')");
        $pdo->exec("INSERT INTO book_titles VALUES (1, 'Clean Code', 'Martin', 'Computer Science', '2026-08-01')");
        $pdo->exec("INSERT INTO book_copies VALUES (1, 1, 'COPY-1', '2', 'IT', 'A1', 'Borrowed', NULL), (2, 1, 'COPY-2', '2', 'IT', 'A1', 'Borrowed', NULL), (3, 1, 'COPY-3', '2', 'IT', 'A1', 'Available', NULL)");
        $pdo->exec("INSERT INTO borrowing_transactions VALUES (1, 'TX-BULK', 1, 'approved', '2026-08-20', '2026-08-27', 'Borrowed', '2026-08-20')");
        $pdo->exec("INSERT INTO borrowing_items VALUES (1, 1, 1, NULL, 'Borrowed', 0), (2, 1, 2, NULL, 'Borrowed', 0)");

        $dashboard = (new PdoStaffRepository($pdo))->dashboard();

        self::assertSame(3, $dashboard['stats']['total_books']);
        self::assertSame(2, $dashboard['stats']['borrowed_books']);
        self::assertSame(2, $dashboard['stats']['active_loans']);
        self::assertSame(2, $dashboard['overview']['loan_status']['borrowed']);
        self::assertSame(2, (int) $dashboard['recent'][0]['quantity']);
        self::assertSame(2, $dashboard['overview']['top_borrowers'][0]['borrowing_count']);
    }

    public function testNormalizedDashboardRanksDistinctStudentCourseBorrowers(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, barcode TEXT, firstname TEXT, lastname TEXT, role TEXT, department TEXT, position TEXT, course TEXT, year_level TEXT, photo TEXT, status TEXT)');
        $pdo->exec('CREATE TABLE courses (id INTEGER PRIMARY KEY, name TEXT NOT NULL UNIQUE, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $pdo->exec('CREATE TABLE book_titles (id INTEGER PRIMARY KEY, title TEXT, author TEXT, category_name TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE book_copies (id INTEGER PRIMARY KEY, title_id INTEGER, barcode TEXT, floor_no TEXT, section_name TEXT, shelf_no TEXT, status TEXT, deleted_at TEXT)');
        $pdo->exec('CREATE TABLE borrowing_transactions (id INTEGER PRIMARY KEY, transaction_code TEXT, user_id INTEGER, approval_status TEXT, borrow_date TEXT, due_date TEXT, status TEXT, requested_at TEXT)');
        $pdo->exec('CREATE TABLE borrowing_items (id INTEGER PRIMARY KEY, transaction_id INTEGER, copy_id INTEGER, return_date TEXT, status TEXT, fine_amount NUMERIC)');
        $pdo->exec("INSERT INTO courses (id, name) VALUES (1, 'BSIT'), (2, 'BSA'), (3, 'BEeD')");
        $pdo->exec("INSERT INTO users VALUES
            (1, 'STU-1', 'Grace', 'Hopper', 'student', 'IT', NULL, 'Bachelor of Science in Information Technology', '4', NULL, 'active'),
            (2, 'STU-2', 'Katherine', 'Johnson', 'student', 'IT', NULL, 'BSIT', '3', NULL, 'active'),
            (3, 'TEACH-1', 'Alan', 'Turing', 'teacher', 'IT', NULL, 'BSIT', '', NULL, 'active')");
        $pdo->exec("INSERT INTO book_titles VALUES (1, 'Clean Code', 'Martin', 'Computer Science', '2026-08-01')");
        $pdo->exec("INSERT INTO book_copies VALUES
            (1, 1, 'COPY-1', '2', 'IT', 'A1', 'Borrowed', NULL),
            (2, 1, 'COPY-2', '2', 'IT', 'A1', 'Borrowed', NULL),
            (3, 1, 'COPY-3', '2', 'IT', 'A1', 'Borrowed', NULL),
            (4, 1, 'COPY-4', '2', 'IT', 'A1', 'Reserved', NULL),
            (5, 1, 'COPY-5', '2', 'IT', 'A1', 'Borrowed', NULL)");
        $pdo->exec("INSERT INTO borrowing_transactions VALUES
            (1, 'TX-1', 1, 'approved', '2026-08-20', '2026-08-27', 'Borrowed', '2026-08-20'),
            (2, 'TX-2', 1, 'approved', '2026-08-21', '2026-08-28', 'Borrowed', '2026-08-21'),
            (3, 'TX-3', 2, 'approved', '2026-08-22', '2026-08-29', 'Borrowed', '2026-08-22'),
            (4, 'TX-4', 2, 'pending', '2026-08-23', '2026-08-30', 'Pending', '2026-08-23'),
            (5, 'TX-5', 3, 'approved', '2026-08-24', '2026-08-31', 'Borrowed', '2026-08-24')");
        $pdo->exec("INSERT INTO borrowing_items VALUES
            (1, 1, 1, NULL, 'Borrowed', 0),
            (2, 1, 2, NULL, 'Borrowed', 0),
            (3, 2, 3, NULL, 'Borrowed', 0),
            (4, 3, 4, NULL, 'Borrowed', 0),
            (5, 4, 5, NULL, 'Pending', 0)");

        $top = (new PdoStaffRepository($pdo))->dashboard()['overview']['top_course_borrowers'];

        self::assertSame(['rank' => 1, 'course' => 'BSIT', 'borrower_count' => 2], $top[0]);
        self::assertSame(2, $top[1]['rank']);
        self::assertSame('BEeD', $top[1]['course']);
        self::assertSame(0, $top[1]['borrower_count']);
    }

    public function testLegacyReportsExposeCategoriesAndComposeCategoryWithDateAndStatusFilters(): void
    {
        $this->pdo->exec("INSERT INTO books (id, barcode, title, author, category_name, status, deleted_at, floor_no, section_name, shelf_no, created_at) VALUES
            (2, 'BK-2', 'English Grammar', 'Author Two', 'English', 'Available', NULL, '1', 'ENG', 'A2', '2026-08-01'),
            (3, 'BK-3', 'Math Basics', 'Author Three', 'Mathematics', 'Available', NULL, '1', 'MATH', 'A3', '2026-08-01')");
        $this->pdo->exec("INSERT INTO borrowing (id, transaction_code, user_id, book_id, approval_status, borrow_date, due_date, return_date, status, fine_amount) VALUES
            (2, 'TX-2', 2, 2, 'approved', '2026-08-21', '2026-08-28', NULL, 'Borrowed', 0),
            (3, 'TX-3', 2, 3, 'approved', '2026-09-01', '2026-09-08', NULL, 'Borrowed', 0)");

        $repository = new PdoStaffRepository($this->pdo);

        self::assertSame(['Computer Science', 'English', 'Mathematics'], $repository->reportCategories());
        /** @var array{data: list<list<mixed>>} $filteredBorrowed */
        $filteredBorrowed = $repository->report('borrowed', '2026-08-01', '2026-08-31', 'English');
        self::assertSame(['TX-2'], array_column($filteredBorrowed['data'], 0));
        /** @var array{data: list<list<mixed>>} $invalidCategory */
        $invalidCategory = $repository->report('borrowed', '', '', 'Does Not Exist');
        self::assertSame([], $invalidCategory['data']);
        /** @var array{data: list<list<mixed>>} $allCategories */
        $allCategories = $repository->report('borrowed', '', '', '');
        self::assertCount(3, $allCategories['data']);

        $this->pdo->exec("UPDATE borrowing SET return_date = '2026-08-25', status = 'Returned' WHERE id = 2");
        /** @var array{data: list<list<mixed>>} $returned */
        $returned = $repository->report('returned', '', '', 'English');
        self::assertSame(['TX-2'], array_column($returned['data'], 0));

        $this->pdo->exec("UPDATE borrowing SET status = 'Overdue' WHERE id = 3");
        /** @var array{data: list<list<mixed>>} $overdue */
        $overdue = $repository->report('overdue', '', '', 'Mathematics');
        self::assertSame(['TX-3'], array_column($overdue['data'], 0));
    }

    public function testNormalizedReportsFilterInventoryByBookTitleCategory(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE book_titles (id INTEGER PRIMARY KEY, title TEXT, author TEXT, category_name TEXT, created_at TEXT)');
        $pdo->exec('CREATE TABLE book_copies (id INTEGER PRIMARY KEY, title_id INTEGER, barcode TEXT, status TEXT, deleted_at TEXT, floor_no TEXT, section_name TEXT, shelf_no TEXT)');
        $pdo->exec("INSERT INTO book_titles VALUES
            (1, 'Clean Code', 'Martin', 'Computer Science', '2026-08-01'),
            (2, 'English Grammar', 'Author Two', 'English', '2026-08-01'),
            (3, 'Deleted Title', 'Author Three', 'Mathematics', '2026-08-01')");
        $pdo->exec("INSERT INTO book_copies VALUES
            (1, 1, 'COPY-1', 'Available', NULL, '2', 'IT', 'A1'),
            (2, 1, 'COPY-2', 'Borrowed', NULL, '2', 'IT', 'A1'),
            (3, 2, 'COPY-3', 'Available', NULL, '1', 'ENG', 'A2'),
            (4, 3, 'COPY-4', 'Available', '2026-09-01', '1', 'MATH', 'A3')");

        $repository = new PdoStaffRepository($pdo);

        self::assertSame(['Computer Science', 'English'], $repository->reportCategories());
        /** @var array{data: list<list<mixed>>} $filtered */
        $filtered = $repository->report('inventory', '', '', 'Computer Science');
        self::assertCount(1, $filtered['data']);
        self::assertSame('Clean Code', $filtered['data'][0][1]);
        /** @var array{data: list<list<mixed>>} $allCategories */
        $allCategories = $repository->report('inventory', '', '', '');
        self::assertCount(2, $allCategories['data']);
        /** @var array{data: list<list<mixed>>} $invalidCategory */
        $invalidCategory = $repository->report('inventory', '', '', 'Mathematics');
        self::assertSame([], $invalidCategory['data']);
    }
}
