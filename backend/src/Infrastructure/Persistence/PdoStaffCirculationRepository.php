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
use PDOStatement;
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
        $borrower = $this->userByBarcode(trim($barcode));
        if ($borrower === null) {
            return null;
        }

        $borrowerId = $this->intValue($borrower['id'] ?? null);
        $normalizedLoans = $this->hasNormalizedSchema()
            ? $this->normalizedActiveLoans($borrowerId)
            : [];
        $legacyLoans = $this->hasTable('borrowing') && $this->hasTable('books')
            ? $this->legacyActiveLoans($borrowerId)
            : [];
        $activeLoans = $this->mergeLoanRows($normalizedLoans, $legacyLoans);
        $borrower['name'] = trim($this->string($borrower['firstname'] ?? null) . ' ' . $this->string($borrower['lastname'] ?? null));
        $borrower['active_loans'] = count($activeLoans);
        $borrower['borrow_limit_count'] = $this->activeLoanCount($borrowerId);

        return [
            'ok' => true,
            'borrower' => $borrower,
            'active_loans' => $activeLoans,
        ];
    }

    /** @return array<string, mixed> */
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
            $normalizedStorage = $this->storageForBorrowBarcodes($bookBarcodes);
            $this->assertBorrowerCanBorrow($borrowerId);
            $activeCount = $this->activeLoanCount($borrowerId);
            if ($activeCount + count($bookBarcodes) > $this->maxBooks) {
                throw new RuntimeException('This borrower has reached the ' . $this->maxBooks . '-book limit.');
            }

            $result = $normalizedStorage
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

    /**
     * @param list<int> $loanIds
     * @param list<string> $copyBarcodes
     * @return array<string, mixed>
     */
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
            $this->assertBorrowerExists($borrowerId);
            $normalizedRows = $this->hasNormalizedSchema()
                ? $this->selectedNormalizedLoans($borrowerId, $loanIds, $copyBarcodes)
                : [];
            $legacyRows = $this->hasTable('borrowing') && $this->hasTable('books')
                ? $this->selectedLegacyLoans($borrowerId, $loanIds, $copyBarcodes)
                : [];
            $rows = $this->selectReturnRows(
                array_map(static fn (array $row): array => $row + ['_storage' => 'normalized'], $normalizedRows),
                array_map(static fn (array $row): array => $row + ['_storage' => 'legacy'], $legacyRows),
                $loanIds,
                $copyBarcodes,
            );

            $totalFine = 0.0;
            $normalizedRows = [];
            foreach ($rows as &$row) {
                $fine = $this->fine($row['due_date'] ?? null, $now, $finePerDay);
                $totalFine += $fine;
                $row['processed_fine'] = $fine;
                $normalized = ($row['_storage'] ?? '') === 'normalized';
                if ($normalized) {
                    $this->returnNormalizedLoan($row, $staffId, $fine);
                    $normalizedRows[] = $row;
                } else {
                    $this->returnLegacyLoan($row, $staffId, $fine);
                }
            }

            if ($normalizedRows !== []) {
                $this->closeCompletedTransactions($normalizedRows, $staffId);
                foreach ($normalizedRows as $row) {
                    if ($this->availability === null) {
                        continue;
                    }
                    $this->availability->advance(
                        $this->intValue($row['title_id'] ?? null),
                        $this->intValue($row['copy_id'] ?? null),
                        $now,
                    );
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
        if (!$this->hasNormalizedSchema()) {
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
        $rows = $this->fetchRows($statement);

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
        $rows = $this->fetchRows($statement);

        return $rows;
    }

    /**
     * @param list<string> $bookBarcodes
     * @return array<string, mixed>
     */
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
            $copyId = $this->intValue($copy['id'] ?? null);
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

    /**
     * @param list<string> $bookBarcodes
     * @return array<string, mixed>
     */
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
                'book_id' => $this->intValue($copy['id'] ?? null),
                'processed_by' => $staffId,
                'due_date' => $dueDate->format('Y-m-d'),
            ]);
            $loanId = (int) $this->pdo->lastInsertId();
            $copyStatement->execute(['due_date' => $dueDate->format('Y-m-d'), 'id' => $this->intValue($copy['id'] ?? null)]);
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

    /**
     * @param list<string> $barcodes
     * @return list<array<string, mixed>>
     */
    private function normalizedCopiesByBarcodes(array $barcodes): array
    {
        $placeholders = $this->namedPlaceholders($barcodes, 'barcode');
        $statement = $this->pdo->prepare($this->forUpdate(
            'SELECT c.id, c.title_id, c.barcode, c.status, t.title, t.author
             FROM book_copies c JOIN book_titles t ON t.id = c.title_id
             WHERE c.barcode IN (' . $placeholders['sql'] . ') AND c.deleted_at IS NULL ORDER BY c.id'
        ));
        $statement->execute($placeholders['parameters']);
        return $this->fetchRows($statement);
    }

    /**
     * @param list<string> $barcodes
     * @return list<array<string, mixed>>
     */
    private function legacyCopiesByBarcodes(array $barcodes): array
    {
        $placeholders = $this->namedPlaceholders($barcodes, 'barcode');
        $statement = $this->pdo->prepare($this->forUpdate(
            'SELECT id, barcode, title, author, status FROM books WHERE barcode IN (' . $placeholders['sql'] . ') AND deleted_at IS NULL ORDER BY id'
        ));
        $statement->execute($placeholders['parameters']);
        return $this->fetchRows($statement);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $requested
     */
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

    /**
     * @param list<int> $loanIds
     * @param list<string> $copyBarcodes
     * @return list<array<string, mixed>>
     */
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
        if ($conditions === []) {
            return [];
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
        return $this->fetchRows($statement);
    }

    /**
     * @param list<int> $loanIds
     * @param list<string> $copyBarcodes
     * @return list<array<string, mixed>>
     */
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
        if ($conditions === []) {
            return [];
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
        return $this->fetchRows($statement);
    }

    /**
     * @param list<array<string, mixed>> $normalizedRows
     * @param list<array<string, mixed>> $legacyRows
     * @param list<int> $loanIds
     * @param list<string> $copyBarcodes
     * @return list<array<string, mixed>>
     */
    private function selectReturnRows(array $normalizedRows, array $legacyRows, array $loanIds, array $copyBarcodes): array
    {
        $candidates = array_merge($normalizedRows, $legacyRows);
        if ($candidates === []) {
            throw new InvalidArgumentException('The selected book does not belong to this borrower.');
        }

        if ($loanIds !== [] && $copyBarcodes !== []) {
            if (count($loanIds) !== count($copyBarcodes)) {
                throw new InvalidArgumentException('Each selected loan must match its returned copy barcode.');
            }
            $selected = [];
            $selectedKeys = [];
            foreach ($loanIds as $index => $loanId) {
                $barcode = $copyBarcodes[$index];
                $matches = $this->matchingReturnRows($candidates, $loanId, $barcode);
                if (count($matches) !== 1) {
                    throw new InvalidArgumentException('The selected loan and copy barcode do not match an active borrower loan.');
                }
                $key = $this->returnRowKey($matches[0]);
                if (isset($selectedKeys[$key])) {
                    throw new InvalidArgumentException('Please select each returned copy only once.');
                }
                $selectedKeys[$key] = true;
                $selected[] = $matches[0];
            }

            return $selected;
        }

        $selected = [];
        $selectedKeys = [];
        if ($loanIds !== []) {
            foreach ($loanIds as $loanId) {
                $matches = $this->matchingReturnRows($candidates, $loanId, null);
                if (count($matches) !== 1) {
                    throw new InvalidArgumentException('The selected loan is ambiguous or no longer active for this borrower.');
                }
                $key = $this->returnRowKey($matches[0]);
                if (isset($selectedKeys[$key])) {
                    throw new InvalidArgumentException('Please select each returned copy only once.');
                }
                $selectedKeys[$key] = true;
                $selected[] = $matches[0];
            }
        }
        foreach ($copyBarcodes as $barcode) {
            $matches = $this->matchingReturnRows($candidates, null, $barcode);
            if (count($matches) !== 1) {
                throw new InvalidArgumentException('The selected book is ambiguous or does not belong to this borrower.');
            }
            $key = $this->returnRowKey($matches[0]);
            if (isset($selectedKeys[$key])) {
                throw new InvalidArgumentException('Please select each returned copy only once.');
            }
            $selectedKeys[$key] = true;
            $selected[] = $matches[0];
        }

        return $selected;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function matchingReturnRows(array $rows, ?int $loanId, ?string $barcode): array
    {
        $matches = [];
        foreach ($rows as $row) {
            if ($loanId !== null && $this->intValue($row['id'] ?? null) !== $loanId) {
                continue;
            }
            if ($barcode !== null && $this->string($row['copy_barcode'] ?? null) !== $barcode) {
                continue;
            }
            $matches[] = $row;
        }

        return $matches;
    }

    /** @param array<string, mixed> $row */
    private function returnRowKey(array $row): string
    {
        return $this->string($row['_storage'] ?? '') . ':' . $this->intValue($row['id'] ?? null);
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
        $statement->execute(['fine' => $fine, 'staff_id' => $staffId, 'id' => $this->intValue($row['id'] ?? null)]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('The selected loan is no longer active.');
        }
        $this->pdo->prepare("UPDATE book_copies SET status = 'Available', due_date = NULL, return_date = CURRENT_DATE WHERE id = :id")
            ->execute(['id' => $this->intValue($row['copy_id'] ?? null)]);
        $this->recordReturnAudit($row, $staffId, $this->intValue($row['transaction_id'] ?? null), $this->intValue($row['id'] ?? null));
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
        $statement->execute(['fine' => $fine, 'staff_id' => $staffId, 'id' => $this->intValue($row['id'] ?? null)]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('The selected loan is no longer active.');
        }
        $this->pdo->prepare("UPDATE books SET status = 'Available', due_date = NULL, return_date = CURRENT_DATE WHERE id = :id")
            ->execute(['id' => $this->intValue($row['copy_id'] ?? null)]);
        $this->recordReturnAudit($row, $staffId, null, null, 'legacy_borrowing:' . $this->intValue($row['id'] ?? null) . ':direct-return');
    }

    /** @param list<array<string, mixed>> $rows */
    private function closeCompletedTransactions(array $rows, int $staffId): void
    {
        $transactionIds = [];
        foreach ($rows as $row) {
            $transactionId = $this->intValue($row['transaction_id'] ?? null);
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
            $this->intValue($row['id'] ?? null),
            $staffId,
            AuditEventType::LOANED,
            'Available',
            'Borrowed',
            null,
            $transactionId,
            $itemId,
            null,
            ['barcode' => $this->string($row['barcode'] ?? null), 'title' => $this->string($row['title'] ?? null), 'borrower_id' => $this->intValue($row['borrower_id'] ?? null)],
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
            $this->intValue($row['copy_id'] ?? null),
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

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function copyItem(array $row): array
    {
        return [
            'copy_id' => $this->intValue($row['id'] ?? null),
            'copy_barcode' => $this->string($row['barcode'] ?? null),
            'title' => $this->string($row['title'] ?? null),
            'author' => $this->string($row['author'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function returnItem(array $row): array
    {
        return [
            'loan_id' => $this->intValue($row['id'] ?? null),
            'copy_id' => $this->intValue($row['copy_id'] ?? null),
            'copy_barcode' => $this->string($row['copy_barcode'] ?? null),
            'title' => $this->string($row['title'] ?? null),
            'fine_amount' => $this->floatValue($row['processed_fine'] ?? null),
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

    private function assertBorrowerCanBorrow(int $borrowerId): void
    {
        $borrower = $this->userById($borrowerId, true);
        if ($borrower === null || !in_array(strtolower($this->string($borrower['role'] ?? null)), ['student', 'teacher'], true)) {
            throw new RuntimeException('Student account not found for that ID.');
        }
        if (strtolower($this->string($borrower['status'] ?? 'active')) !== 'active' || strtolower($this->string($borrower['borrowing_status'] ?? 'active')) !== 'active') {
            throw new RuntimeException('This account is not currently allowed to borrow books.');
        }
        if ($this->activeLoanCount($borrowerId) >= $this->maxBooks) {
            throw new RuntimeException('This borrower has reached the ' . $this->maxBooks . '-book limit.');
        }
    }

    private function assertBorrowerExists(int $borrowerId): void
    {
        if ($this->userById($borrowerId, true) === null) {
            throw new InvalidArgumentException('Student account was not found.');
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

    private function activeLoanCount(int $borrowerId): int
    {
        $normalizedRows = $this->hasNormalizedSchema()
            ? $this->normalizedBorrowLimitRows($borrowerId)
            : [];
        $legacyRows = $this->hasTable('borrowing') && $this->hasTable('books')
            ? $this->legacyBorrowLimitRows($borrowerId)
            : [];

        return count($this->mergeLoanRows($normalizedRows, $legacyRows));
    }

    /** @return list<array<string, mixed>> */
    private function normalizedBorrowLimitRows(int $borrowerId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT bi.id, bi.copy_id, c.barcode AS copy_barcode
             FROM borrowing_items bi JOIN borrowing_transactions bt ON bt.id = bi.transaction_id
             JOIN book_copies c ON c.id = bi.copy_id
             WHERE bt.user_id = :user_id AND bt.approval_status IN ('pending', 'approved')
               AND bi.return_date IS NULL AND bi.status IN ('Pending', 'Borrowed', 'Overdue')"
        );
        $statement->execute(['user_id' => $borrowerId]);

        return $this->fetchRows($statement);
    }

    /** @return list<array<string, mixed>> */
    private function legacyBorrowLimitRows(int $borrowerId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT b.id, b.book_id AS copy_id, old_book.barcode AS copy_barcode
             FROM borrowing b JOIN books old_book ON old_book.id = b.book_id
             WHERE b.user_id = :user_id AND b.approval_status IN ('pending', 'approved')
               AND b.return_date IS NULL AND b.status IN ('Pending', 'Borrowed', 'Overdue')"
        );
        $statement->execute(['user_id' => $borrowerId]);

        return $this->fetchRows($statement);
    }

    /**
     * @param list<array<string, mixed>> $preferred
     * @param list<array<string, mixed>> $fallback
     * @return list<array<string, mixed>>
     */
    private function mergeLoanRows(array $preferred, array $fallback): array
    {
        $merged = [];
        $seen = [];
        foreach (array_merge($preferred, $fallback) as $row) {
            $barcode = $this->string($row['copy_barcode'] ?? null);
            $key = $barcode !== ''
                ? 'barcode:' . $barcode
                : 'loan:' . $this->intValue($row['id'] ?? null) . ':' . count($merged);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $merged[] = $row;
        }

        return $merged;
    }

    /** @param list<string> $barcodes */
    private function assertUniqueBarcodes(array $barcodes): void
    {
        if (count(array_unique($barcodes)) !== count($barcodes)) {
            throw new RuntimeException('Please scan each book copy only once.');
        }
    }

    private function hasNormalizedSchema(): bool
    {
        return $this->hasTable('book_copies')
            && $this->hasTable('book_titles')
            && $this->hasTable('borrowing_transactions')
            && $this->hasTable('borrowing_items');
    }

    private function usesNormalizedInventory(): bool
    {
        return $this->hasNormalizedSchema() && $this->tableCount('book_copies') > 0;
    }

    /** @param list<string> $barcodes */
    private function storageForBorrowBarcodes(array $barcodes): bool
    {
        $normalizedRows = $this->hasNormalizedSchema()
            ? $this->normalizedCopiesByBarcodes($barcodes)
            : [];
        $legacyRows = $this->hasTable('books')
            ? $this->legacyCopiesByBarcodes($barcodes)
            : [];
        $requestedCount = count($barcodes);
        if (count($normalizedRows) === $requestedCount) {
            return true;
        }
        if (count($legacyRows) === $requestedCount) {
            return false;
        }
        if ($normalizedRows !== [] || $legacyRows !== []) {
            throw new RuntimeException('Scanned book copies must belong to the same circulation storage records.');
        }

        return $this->usesNormalizedInventory();
    }

    private function tableCount(string $table): int
    {
        $statement = $this->pdo->query('SELECT COUNT(*) FROM ' . $table);
        if ($statement === false) {
            return 0;
        }
        $value = $statement->fetchColumn();
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    /**
     * @param list<int|string> $values
     * @return array{sql: string, parameters: array<string, int|string>}
     */
    private function namedPlaceholders(array $values, string $prefix): array
    {
        $parameters = [];
        $names = [];
        foreach ($values as $index => $value) {
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
                $name = is_array($row) ? ($row['name'] ?? null) : null;
                if ($this->string($name) === $column) {
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

    /** @return list<array<string, mixed>> */
    private function fetchRows(PDOStatement|false $statement): array
    {
        if ($statement === false) {
            return [];
        }
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = [];
            foreach ($row as $key => $value) {
                if (is_string($key)) {
                    $normalized[$key] = $value;
                }
            }
            $result[] = $normalized;
        }

        return $result;
    }

    private function intValue(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    private function floatValue(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return 0.0;
    }
}
