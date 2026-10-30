<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Services\ReservationAvailabilityInterface;
use App\Application\StaffCirculation\StaffCirculationRepositoryInterface;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class PdoStaffCirculationRepository implements StaffCirculationRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?AuditEventRepositoryInterface $audit = null,
        private readonly ?ReservationAvailabilityInterface $availability = null,
        private readonly int $maxBooks = 3,
    ) {
    }

    public function findBorrowerByBarcode(string $barcode): ?array
    {
        $normalized = $this->usesNormalizedSchema();
        $borrower = $this->userByBarcode(trim($barcode));
        if ($borrower === null) {
            return null;
        }

        $activeLoans = $normalized
            ? $this->normalizedActiveLoans((int) $borrower['id'])
            : $this->legacyActiveLoans((int) $borrower['id']);
        $borrower['name'] = trim($this->string($borrower['firstname'] ?? null) . ' ' . $this->string($borrower['lastname'] ?? null));
        $borrower['active_loans'] = count($activeLoans);

        return [
            'ok' => true,
            'borrower' => $borrower,
            'active_loans' => $activeLoans,
        ];
    }

    public function createDirectBorrow(
        int $borrowerId,
        int $staffId,
        array $bookBarcodes,
        DateTimeImmutable $dueDate,
        string $transactionCode,
    ): array {
        $this->assertUniqueBarcodes($bookBarcodes);
        if ($bookBarcodes === []) {
            throw new RuntimeException('At least one book copy is required.');
        }

        $this->pdo->beginTransaction();
        try {
            $normalized = $this->usesNormalizedSchema();
            $this->assertBorrowerCanBorrow($borrowerId, $normalized);
            $activeCount = $this->activeLoanCount($borrowerId, $normalized);
            if ($activeCount + count($bookBarcodes) > $this->maxBooks) {
                throw new RuntimeException('This borrower has reached the ' . $this->maxBooks . '-book limit.');
            }

            $result = $normalized
                ? $this->createNormalizedBorrow($borrowerId, $staffId, $bookBarcodes, $dueDate, $transactionCode)
                : $this->createLegacyBorrow($borrowerId, $staffId, $bookBarcodes, $dueDate, $transactionCode);
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function processDirectReturns(
        int $borrowerId,
        int $staffId,
        array $loanIds,
        array $copyBarcodes,
        DateTimeImmutable $now,
        float $finePerDay,
    ): array {
        $this->pdo->beginTransaction();
        try {
            $normalized = $this->usesNormalizedSchema();
            $this->assertBorrowerExists($borrowerId, $normalized);
            $rows = $normalized
                ? $this->selectedNormalizedLoans($borrowerId, $loanIds, $copyBarcodes)
                : $this->selectedLegacyLoans($borrowerId, $loanIds, $copyBarcodes);
            $this->assertSelectedRows($rows, $loanIds, $copyBarcodes);

            $totalFine = 0.0;
            foreach ($rows as &$row) {
                $fine = $this->fine($row['due_date'] ?? null, $now, $finePerDay);
                $totalFine += $fine;
                $row['processed_fine'] = $fine;
                if ($normalized) {
                    $this->returnNormalizedLoan($row, $staffId, $fine);
                } else {
                    $this->returnLegacyLoan($row, $staffId, $fine);
                }
            }

            if ($normalized) {
                $this->closeCompletedTransactions($rows, $staffId);
                foreach ($rows as $row) {
                    if ($this->availability !== null) {
                        $this->availability->advance(
                            (int) ($row['title_id'] ?? 0),
                            (int) ($row['copy_id'] ?? 0),
                            $now,
                        );
                    }
                }
            }
            unset($row);
            $this->pdo->commit();

            return [
                'returned' => count($rows),
                'total_fine' => round($totalFine, 2),
                'items' => array_map(fn (array $row): array => $this->returnItem($row), $rows),
            ];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<string, mixed>|null */
    private function userByBarcode(string $barcode): ?array
    {
        if ($barcode === '' || !$this->hasTable('users')) {
            return null;
        }
        $status = $this->hasColumn('users', 'status') ? 'u.status' : "'active' AS status";
        $borrowingStatus = $this->hasColumn('users', 'borrowing_status')
            ? 'u.borrowing_status'
            : "'active' AS borrowing_status";
        $statement = $this->pdo->prepare(
            'SELECT u.id, u.barcode, u.firstname, u.middlename, u.lastname, u.email, u.contact_no,
                    u.role, ' . $status . ', ' . $borrowingStatus . ', u.department, u.position,
                    u.course, u.year_level, u.photo
             FROM users u WHERE u.barcode = :barcode AND u.role IN (\'student\', \'teacher\') LIMIT 1'
        );
        $statement->execute(['barcode' => $barcode]);
        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    private function normalizedActiveLoans(int $borrowerId): array
    {
        if (!$this->usesNormalizedSchema()) {
            return [];
        }
        $statement = $this->pdo->prepare(
            "SELECT bi.id, bi.copy_id, c.barcode AS copy_barcode, c.title_id, t.title, t.author,
                    bt.transaction_code, bt.borrow_date, bt.due_date, bt.status
             FROM borrowing_items bi
             JOIN borrowing_transactions bt ON bt.id = bi.transaction_id
             JOIN book_copies c ON c.id = bi.copy_id
             JOIN book_titles t ON t.id = c.title_id
             WHERE bt.user_id = :user_id AND bt.approval_status = 'approved'
               AND bi.return_date IS NULL AND bi.status IN ('Borrowed', 'Overdue')
             ORDER BY bt.due_date ASC, bi.id ASC"
        );
        $statement->execute(['user_id' => $borrowerId]);
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function legacyActiveLoans(int $borrowerId): array
    {
        if (!$this->hasTable('borrowing') || !$this->hasTable('books')) {
            return [];
        }
        $statement = $this->pdo->prepare(
            "SELECT b.id, b.book_id AS copy_id, old_book.barcode AS copy_barcode, old_book.title,
                    old_book.author, b.transaction_code, b.borrow_date, b.due_date, b.status
             FROM borrowing b JOIN books old_book ON old_book.id = b.book_id
             WHERE b.user_id = :user_id AND b.approval_status = 'approved'
               AND b.return_date IS NULL AND b.status IN ('Borrowed', 'Overdue')
             ORDER BY b.due_date ASC, b.id ASC"
        );
        $statement->execute(['user_id' => $borrowerId]);
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    /** @param list<string> $bookBarcodes @return array<string, mixed> */
    private function createNormalizedBorrow(int $borrowerId, int $staffId, array $bookBarcodes, DateTimeImmutable $dueDate, string $transactionCode): array
    {
        $copies = $this->normalizedCopiesByBarcodes($bookBarcodes);
        $this->assertAvailableCopies($copies, $bookBarcodes);
        $statement = $this->pdo->prepare(
            'INSERT INTO borrowing_transactions
                (transaction_code, user_id, processed_by, approval_status, borrow_date, due_date, status, fine_amount, requested_at, approved_at, approved_by)
             VALUES (:transaction_code, :user_id, :processed_by, \'approved\', CURRENT_TIMESTAMP, :due_date, \'Borrowed\', 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, :approved_by)'
        );
        $statement->execute([
            'transaction_code' => $transactionCode,
            'user_id' => $borrowerId,
            'processed_by' => $staffId,
            'due_date' => $dueDate->format('Y-m-d'),
            'approved_by' => $staffId,
        ]);
        $transactionId = (int) $this->pdo->lastInsertId();
        $itemStatement = $this->pdo->prepare(
            "INSERT INTO borrowing_items (transaction_id, copy_id, status, fine_amount) VALUES (:transaction_id, :copy_id, 'Borrowed', 0)"
        );
        $copyStatement = $this->pdo->prepare('UPDATE book_copies SET status = \'Borrowed\', due_date = :due_date WHERE id = :id');
        foreach ($copies as $copy) {
            $copyId = (int) $copy['id'];
            $itemStatement->execute(['transaction_id' => $transactionId, 'copy_id' => $copyId]);
            $itemId = (int) $this->pdo->lastInsertId();
            $copyStatement->execute(['due_date' => $dueDate->format('Y-m-d'), 'id' => $copyId]);
            $this->recordLoanAudit($copy, $staffId, $transactionId, $itemId);
        }

        return [
            'transaction_code' => $transactionCode,
            'transaction_id' => $transactionId,
            'borrowed' => count($copies),
            'items' => array_map(fn (array $copy): array => $this->copyItem($copy), $copies),
            'due_date' => $dueDate->format('Y-m-d'),
        ];
    }

    /** @param list<string> $bookBarcodes @return array<string, mixed> */
    private function createLegacyBorrow(int $borrowerId, int $staffId, array $bookBarcodes, DateTimeImmutable $dueDate, string $transactionCode): array
    {
        $copies = $this->legacyCopiesByBarcodes($bookBarcodes);
        $this->assertAvailableCopies($copies, $bookBarcodes);
        $statement = $this->pdo->prepare(
            "INSERT INTO borrowing (transaction_code, user_id, book_id, processed_by, approval_status, borrow_date, due_date, status, fine_amount)
             VALUES (:transaction_code, :user_id, :book_id, :processed_by, 'approved', CURRENT_TIMESTAMP, :due_date, 'Borrowed', 0)"
        );
        $copyStatement = $this->pdo->prepare('UPDATE books SET status = \'Borrowed\', due_date = :due_date WHERE id = :id');
        $codes = [];
        foreach ($copies as $index => $copy) {
            $copyCode = count($copies) === 1 ? $transactionCode : $transactionCode . '-' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $statement->execute([
                'transaction_code' => $copyCode,
                'user_id' => $borrowerId,
                'book_id' => (int) $copy['id'],
                'processed_by' => $staffId,
                'due_date' => $dueDate->format('Y-m-d'),
            ]);
            $loanId = (int) $this->pdo->lastInsertId();
            $copyStatement->execute(['due_date' => $dueDate->format('Y-m-d'), 'id' => (int) $copy['id']]);
            $this->recordLoanAudit($copy, $staffId, null, null, 'legacy_borrowing:' . $loanId . ':direct-borrow');
            $codes[] = $copyCode;
        }

        return [
            'transaction_code' => $transactionCode,
            'transaction_codes' => $codes,
            'borrowed' => count($copies),
            'items' => array_map(fn (array $copy): array => $this->copyItem($copy), $copies),
            'due_date' => $dueDate->format('Y-m-d'),
        ];
    }

    /** @param list<string> $barcodes @return list<array<string, mixed>> */
    private function normalizedCopiesByBarcodes(array $barcodes): array
    {
        $placeholders = $this->namedPlaceholders($barcodes, 'barcode');
        $statement = $this->pdo->prepare($this->forUpdate(
            'SELECT c.id, c.title_id, c.barcode, c.status, t.title, t.author
             FROM book_copies c JOIN book_titles t ON t.id = c.title_id
             WHERE c.barcode IN (' . $placeholders['sql'] . ') AND c.deleted_at IS NULL ORDER BY c.id'
        ));
        $statement->execute($placeholders['parameters']);
        /** @var list<array<string, mixed>> $rows */
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param list<string> $barcodes @return list<array<string, mixed>> */
    private function legacyCopiesByBarcodes(array $barcodes): array
    {
        $placeholders = $this->namedPlaceholders($barcodes, 'barcode');
        $statement = $this->pdo->prepare($this->forUpdate(
            'SELECT id, barcode, title, author, status FROM books WHERE barcode IN (' . $placeholders['sql'] . ') AND deleted_at IS NULL ORDER BY id'
        ));
        $statement->execute($placeholders['parameters']);
        /** @var list<array<string, mixed>> $rows */
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param list<array<string, mixed>> $rows @param list<string> $requested */
    private function assertAvailableCopies(array $rows, array $requested): void
    {
        $found = [];
        foreach ($rows as $row) {
            $barcode = $this->string($row['barcode'] ?? null);
            $found[$barcode] = true;
            if ($this->string($row['status'] ?? null) !== 'Available') {
                throw new RuntimeException('Book copy ' . $barcode . ' is not available.');
            }
        }
        foreach ($requested as $barcode) {
            if (!isset($found[$barcode])) {
                throw new RuntimeException('Book copy ' . $barcode . ' was not found.');
            }
        }
    }

    /** @param list<int> $loanIds @param list<string> $copyBarcodes @return list<array<string, mixed>> */
    private function selectedNormalizedLoans(int $borrowerId, array $loanIds, array $copyBarcodes): array
    {
        $conditions = [];
        $parameters = ['user_id' => $borrowerId];
        if ($loanIds !== []) {
            $ids = $this->namedPlaceholders($loanIds, 'loan');
            $conditions[] = 'bi.id IN (' . $ids['sql'] . ')';
            $parameters += $ids['parameters'];
        }
        if ($copyBarcodes !== []) {
            $barcodes = $this->namedPlaceholders($copyBarcodes, 'copy');
            $conditions[] = 'c.barcode IN (' . $barcodes['sql'] . ')';
            $parameters += $barcodes['parameters'];
        }
        $statement = $this->pdo->prepare($this->forUpdate(
            "SELECT bi.id, bi.copy_id, c.barcode AS copy_barcode, c.title_id, c.status AS copy_status,
                    t.title, t.author, bt.id AS transaction_id, bt.transaction_code, bt.due_date
             FROM borrowing_items bi
             JOIN borrowing_transactions bt ON bt.id = bi.transaction_id
             JOIN book_copies c ON c.id = bi.copy_id
             JOIN book_titles t ON t.id = c.title_id
             WHERE bt.user_id = :user_id AND bt.approval_status = 'approved'
               AND bi.return_date IS NULL AND bi.status IN ('Borrowed', 'Overdue')
               AND (" . implode(' OR ', $conditions) . ') ORDER BY bi.id'
        ));
        $statement->execute($parameters);
        /** @var list<array<string, mixed>> $rows */
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param list<int> $loanIds @param list<string> $copyBarcodes @return list<array<string, mixed>> */
    private function selectedLegacyLoans(int $borrowerId, array $loanIds, array $copyBarcodes): array
    {
        $conditions = [];
        $parameters = ['user_id' => $borrowerId];
        if ($loanIds !== []) {
            $ids = $this->namedPlaceholders($loanIds, 'loan');
            $conditions[] = 'b.id IN (' . $ids['sql'] . ')';
            $parameters += $ids['parameters'];
        }
        if ($copyBarcodes !== []) {
            $barcodes = $this->namedPlaceholders($copyBarcodes, 'copy');
            $conditions[] = 'old_book.barcode IN (' . $barcodes['sql'] . ')';
            $parameters += $barcodes['parameters'];
        }
        $statement = $this->pdo->prepare($this->forUpdate(
            "SELECT b.id, b.book_id AS copy_id, old_book.barcode AS copy_barcode, old_book.title,
                    old_book.author, old_book.status AS copy_status, b.transaction_code, b.due_date
             FROM borrowing b JOIN books old_book ON old_book.id = b.book_id
             WHERE b.user_id = :user_id AND b.approval_status = 'approved'
               AND b.return_date IS NULL AND b.status IN ('Borrowed', 'Overdue')
               AND (" . implode(' OR ', $conditions) . ') ORDER BY b.id'
        ));
        $statement->execute($parameters);
        /** @var list<array<string, mixed>> $rows */
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param list<array<string, mixed>> $rows @param list<int> $loanIds @param list<string> $copyBarcodes */
    private function assertSelectedRows(array $rows, array $loanIds, array $copyBarcodes): void
    {
        $foundIds = [];
        $foundBarcodes = [];
        foreach ($rows as $row) {
            $foundIds[(int) ($row['id'] ?? 0)] = true;
            $foundBarcodes[$this->string($row['copy_barcode'] ?? null)] = true;
        }
        foreach ($loanIds as $loanId) {
            if (!isset($foundIds[$loanId])) {
                throw new InvalidArgumentException('The selected loan is no longer active for this borrower.');
            }
        }
        foreach ($copyBarcodes as $barcode) {
            if (!isset($foundBarcodes[$barcode])) {
                throw new InvalidArgumentException('The selected book does not belong to this borrower.');
            }
        }
        if ($rows === []) {
            throw new InvalidArgumentException('The selected book does not belong to this borrower.');
        }
    }

    /** @param array<string, mixed> $row */
    private function returnNormalizedLoan(array $row, int $staffId, float $fine): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE borrowing_items SET return_date = CURRENT_TIMESTAMP, status = 'Returned', fine_amount = :fine,
                return_status = 'none', return_decided_at = CURRENT_TIMESTAMP, return_decided_by = :staff_id,
                return_decision_note = 'Processed at circulation desk.'
             WHERE id = :id AND return_date IS NULL"
        );
        $statement->execute(['fine' => $fine, 'staff_id' => $staffId, 'id' => (int) $row['id']]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('The selected loan is no longer active.');
        }
        $this->pdo->prepare("UPDATE book_copies SET status = 'Available', due_date = NULL, return_date = CURRENT_DATE WHERE id = :id")
            ->execute(['id' => (int) $row['copy_id']]);
        $this->recordReturnAudit($row, $staffId, (int) $row['transaction_id'], (int) $row['id']);
    }

    /** @param array<string, mixed> $row */
    private function returnLegacyLoan(array $row, int $staffId, float $fine): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE borrowing SET return_date = CURRENT_TIMESTAMP, status = 'Returned', fine_amount = :fine,
                return_status = 'none', return_decided_at = CURRENT_TIMESTAMP, return_decided_by = :staff_id,
                return_decision_note = 'Processed at circulation desk.'
             WHERE id = :id AND return_date IS NULL"
        );
        $statement->execute(['fine' => $fine, 'staff_id' => $staffId, 'id' => (int) $row['id']]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('The selected loan is no longer active.');
        }
        $this->pdo->prepare("UPDATE books SET status = 'Available', due_date = NULL, return_date = CURRENT_DATE WHERE id = :id")
            ->execute(['id' => (int) $row['copy_id']]);
        $this->recordReturnAudit($row, $staffId, null, null, 'legacy_borrowing:' . (int) $row['id'] . ':direct-return');
    }

    /** @param list<array<string, mixed>> $rows */
    private function closeCompletedTransactions(array $rows, int $staffId): void
    {
        $transactionIds = [];
        foreach ($rows as $row) {
            $transactionId = (int) ($row['transaction_id'] ?? 0);
            if ($transactionId > 0) {
                $transactionIds[$transactionId] = true;
            }
        }
        $statement = $this->pdo->prepare(
            "UPDATE borrowing_transactions SET return_date = CURRENT_TIMESTAMP, status = 'Returned', return_status = 'none',
                return_decided_at = CURRENT_TIMESTAMP, return_decided_by = :staff_id, return_decision_note = 'Processed at circulation desk.'
             WHERE id = :id AND NOT EXISTS (
                SELECT 1 FROM borrowing_items WHERE transaction_id = :transaction_id AND return_date IS NULL
             )"
        );
        foreach (array_keys($transactionIds) as $transactionId) {
            $statement->execute(['staff_id' => $staffId, 'id' => $transactionId, 'transaction_id' => $transactionId]);
        }
    }

    /** @param array<string, mixed> $row */
    private function recordLoanAudit(array $row, int $staffId, ?int $transactionId, ?int $itemId, ?string $legacySource = null): void
    {
        if ($this->audit === null) {
            return;
        }
        $this->audit->record(new AuditEvent(
            (int) ($row['id'] ?? 0),
            $staffId,
            AuditEventType::LOANED,
            'Available',
            'Borrowed',
            null,
            $transactionId,
            $itemId,
            null,
            ['barcode' => $this->string($row['barcode'] ?? null), 'title' => $this->string($row['title'] ?? null), 'borrower_id' => (int) ($row['borrower_id'] ?? 0)],
            new DateTimeImmutable(),
            $legacySource,
        ));
    }

    /** @param array<string, mixed> $row */
    private function recordReturnAudit(array $row, int $staffId, ?int $transactionId, ?int $itemId, ?string $legacySource = null): void
    {
        if ($this->audit === null) {
            return;
        }
        $this->audit->record(new AuditEvent(
            (int) ($row['copy_id'] ?? 0),
            $staffId,
            AuditEventType::RETURNED,
            'Borrowed',
            'Available',
            'Return processed at circulation desk.',
            $transactionId,
            $itemId,
            null,
            ['barcode' => $this->string($row['copy_barcode'] ?? null), 'title' => $this->string($row['title'] ?? null)],
            new DateTimeImmutable(),
            $legacySource,
        ));
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function copyItem(array $row): array
    {
        return [
            'copy_id' => (int) ($row['id'] ?? 0),
            'copy_barcode' => $this->string($row['barcode'] ?? null),
            'title' => $this->string($row['title'] ?? null),
            'author' => $this->string($row['author'] ?? null),
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function returnItem(array $row): array
    {
        return [
            'loan_id' => (int) ($row['id'] ?? 0),
            'copy_id' => (int) ($row['copy_id'] ?? 0),
            'copy_barcode' => $this->string($row['copy_barcode'] ?? null),
            'title' => $this->string($row['title'] ?? null),
            'fine_amount' => (float) ($row['processed_fine'] ?? 0.0),
        ];
    }

    private function fine(mixed $dueDateValue, DateTimeImmutable $now, float $finePerDay): float
    {
        if (!is_string($dueDateValue)) {
            return 0.0;
        }
        $dueDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dueDateValue);
        if ($dueDate === false) {
            return 0.0;
        }
        $delta = $now->getTimestamp() - $dueDate->setTime(23, 59, 59)->getTimestamp();
        $days = $delta > 0 ? (int) floor($delta / 86400) : 0;

        return round($days * $finePerDay, 2);
    }

    private function assertBorrowerCanBorrow(int $borrowerId, bool $normalized): void
    {
        $borrower = $this->userById($borrowerId, true);
        if ($borrower === null || !in_array(strtolower($this->string($borrower['role'] ?? null)), ['student', 'teacher'], true)) {
            throw new RuntimeException('Student account not found for that ID.');
        }
        if (strtolower($this->string($borrower['status'] ?? 'active')) !== 'active' || strtolower($this->string($borrower['borrowing_status'] ?? 'active')) !== 'active') {
            throw new RuntimeException('This account is not currently allowed to borrow books.');
        }
        if ($this->activeLoanCount($borrowerId, $normalized) >= $this->maxBooks) {
            throw new RuntimeException('This borrower has reached the ' . $this->maxBooks . '-book limit.');
        }
    }

    private function assertBorrowerExists(int $borrowerId, bool $normalized): void
    {
        if ($this->userById($borrowerId, true) === null) {
            throw new InvalidArgumentException('Student account was not found.');
        }
        if ($normalized && !$this->usesNormalizedSchema()) {
            throw new RuntimeException('Circulation schema is unavailable.');
        }
    }

    /** @return array<string, mixed>|null */
    private function userById(int $userId, bool $forUpdate): ?array
    {
        $status = $this->hasColumn('users', 'status') ? 'u.status' : "'active' AS status";
        $borrowingStatus = $this->hasColumn('users', 'borrowing_status')
            ? 'u.borrowing_status'
            : "'active' AS borrowing_status";
        $statement = $this->pdo->prepare($this->forUpdate(
            'SELECT u.id, u.role, ' . $status . ', ' . $borrowingStatus . ' FROM users u WHERE u.id = :id LIMIT 1',
            $forUpdate,
        ));
        $statement->execute(['id' => $userId]);
        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private function activeLoanCount(int $borrowerId, bool $normalized): int
    {
        $statement = $this->pdo->prepare($normalized
            ? "SELECT COUNT(*) FROM borrowing_items bi JOIN borrowing_transactions bt ON bt.id = bi.transaction_id
               WHERE bt.user_id = :user_id AND bt.approval_status = 'approved' AND bi.return_date IS NULL
                 AND bi.status IN ('Borrowed', 'Overdue')"
            : "SELECT COUNT(*) FROM borrowing WHERE user_id = :user_id AND approval_status = 'approved'
               AND return_date IS NULL AND status IN ('Borrowed', 'Overdue')"
        );
        $statement->execute(['user_id' => $borrowerId]);

        return (int) $statement->fetchColumn();
    }

    /** @param list<string> $barcodes */
    private function assertUniqueBarcodes(array $barcodes): void
    {
        if (count(array_unique($barcodes)) !== count($barcodes)) {
            throw new RuntimeException('Please scan each book copy only once.');
        }
    }

    private function usesNormalizedSchema(): bool
    {
        return $this->hasTable('book_copies')
            && $this->hasTable('book_titles')
            && $this->hasTable('borrowing_transactions')
            && $this->hasTable('borrowing_items');
    }

    /** @param list<int|string> $values @return array{sql: string, parameters: array<string, int|string>} */
    private function namedPlaceholders(array $values, string $prefix): array
    {
        $parameters = [];
        $names = [];
        foreach (array_values($values) as $index => $value) {
            $name = $prefix . '_' . $index;
            $names[] = ':' . $name;
            $parameters[$name] = $value;
        }

        return ['sql' => implode(', ', $names), 'parameters' => $parameters];
    }

    private function forUpdate(string $query, bool $enabled = true): string
    {
        return $enabled && $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? $query . ' FOR UPDATE' : $query;
    }

    private function hasTable(string $table): bool
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :table LIMIT 1");
        } else {
            $statement = $this->pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1');
        }
        $statement->execute(['table' => $table]);

        return $statement->fetchColumn() !== false;
    }

    private function hasColumn(string $table, string $column): bool
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $this->pdo->query('PRAGMA table_info(' . $table . ')');
            if ($statement === false) {
                return false;
            }
            while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                if ($this->string($row['name'] ?? null) === $column) {
                    return true;
                }
            }

            return false;
        }
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column LIMIT 1'
        );
        $statement->execute(['table' => $table, 'column' => $column]);

        return $statement->fetchColumn() !== false;
    }

    private function string(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? trim((string) $value) : '';
    }
}
