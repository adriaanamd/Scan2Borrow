<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\DTO\BulkBorrowRequest;
use App\Application\DTO\BulkBorrowItem;
use App\Domain\Borrowing\LoanRecord;
use DateTimeImmutable;
use PDO;

final class PdoBorrowingRepository implements BorrowingRepositoryInterface, ReturnRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findBook(string $barcode): ?array
    {
        if ($this->hasTable('book_copies')) {
            $statement = $this->pdo->prepare(
                "SELECT c.id, c.id AS copy_id, c.title_id, c.barcode, t.title, t.author, c.status, c.due_date,
                        NULL AS return_date FROM book_copies c JOIN book_titles t ON t.id = c.title_id
                 WHERE c.barcode = :barcode AND c.deleted_at IS NULL LIMIT 1"
            );
            $statement->execute(['barcode' => trim($barcode)]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            return $row === false ? null : $row;
        }
        $statement = $this->pdo->prepare(
            'SELECT id, barcode, title, author, status, due_date, return_date FROM books WHERE barcode = :barcode AND deleted_at IS NULL LIMIT 1'
        );
        $statement->execute(['barcode' => trim($barcode)]);
        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @return array{transaction_code: string, copy_count: int, title_count: int} */
    public function createBulkTransaction(
        BulkBorrowRequest $request,
        DateTimeImmutable $dueDate,
        string $transactionCode,
        string $status,
        string $approvalStatus,
    ): array {
        $this->pdo->beginTransaction();
        try {
            /** @var list<array{id: int, title_id: int}> $copies */
            $copies = [];
            foreach ($request->items as $item) {
                $copies = array_merge($copies, $this->allocateCopies($item));
            }

            if (count($copies) !== array_sum(array_map(
                static fn (BulkBorrowItem $item): int => $item->quantity,
                $request->items,
            ))) {
                throw new \RuntimeException('Not enough available copies for this borrowing request.');
            }

            $statement = $this->pdo->prepare(
                'INSERT INTO borrowing_transactions (transaction_code, user_id, processed_by, approval_status, borrow_date, due_date, status, fine_amount, requested_at) '
                . 'VALUES (:transaction_code, :user_id, NULL, :approval_status, CURRENT_TIMESTAMP, :due_date, :status, 0, CURRENT_TIMESTAMP)'
            );
            $statement->execute([
                'transaction_code' => $transactionCode,
                'user_id' => $request->userId,
                'approval_status' => $approvalStatus,
                'due_date' => $dueDate->format('Y-m-d'),
                'status' => $status,
            ]);
            $transactionId = (int) $this->pdo->lastInsertId();

            $itemStatement = $this->pdo->prepare(
                'INSERT INTO borrowing_items (transaction_id, copy_id, status, fine_amount) VALUES (:transaction_id, :copy_id, :status, 0)'
            );
            $copyStatement = $this->pdo->prepare('UPDATE book_copies SET status = :status, due_date = :due_date WHERE id = :id');
            $copyStatus = $approvalStatus === 'pending' ? 'Reserved' : 'Borrowed';
            foreach ($copies as $copy) {
                $itemStatement->execute([
                    'transaction_id' => $transactionId,
                    'copy_id' => $copy['id'],
                    'status' => $status,
                ]);
                $copyStatement->execute([
                    'status' => $copyStatus,
                    'due_date' => $dueDate->format('Y-m-d'),
                    'id' => $copy['id'],
                ]);
            }

            $this->pdo->commit();

            return [
                'transaction_code' => $transactionCode,
                'copy_count' => count($copies),
                'title_count' => count($request->items),
            ];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return list<array{id: int, title_id: int}> */
    private function allocateCopies(BulkBorrowItem $item): array
    {
        $requestedBarcodes = array_values(array_unique(array_filter(
            $item->barcodes,
            static fn (mixed $barcode): bool => is_string($barcode) && trim($barcode) !== '',
        )));
        if (count($requestedBarcodes) > $item->quantity) {
            throw new \RuntimeException('The scanned copies do not match the requested quantity.');
        }

        $selected = [];
        if ($requestedBarcodes !== []) {
            $placeholders = implode(',', array_fill(0, count($requestedBarcodes), '?'));
            $query = 'SELECT id, title_id FROM book_copies WHERE title_id = ? AND barcode IN (' . $placeholders . ') AND status = \'Available\' AND deleted_at IS NULL ORDER BY id';
            $statement = $this->pdo->prepare($this->forUpdate($query));
            $statement->execute(array_merge([$item->titleId], $requestedBarcodes));
            /** @var list<array<string, mixed>> $rows */
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $selected[] = ['id' => (int) $row['id'], 'title_id' => (int) $row['title_id']];
            }
        }

        $remaining = $item->quantity - count($selected);
        if ($remaining > 0) {
            $selectedIds = array_column($selected, 'id');
            $excludedCopies = $selectedIds === []
                ? ''
                : ' AND id NOT IN (' . implode(',', array_fill(0, count($selectedIds), '?')) . ')';
            $statement = $this->pdo->prepare($this->forUpdate(
                'SELECT id, title_id FROM book_copies WHERE title_id = ? AND status = \'Available\' AND deleted_at IS NULL' . $excludedCopies . ' ORDER BY id'
            ));
            $statement->execute(array_merge([$item->titleId], $selectedIds));
            /** @var list<array<string, mixed>> $rows */
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach (array_slice($rows, 0, $remaining) as $row) {
                $selected[] = ['id' => (int) $row['id'], 'title_id' => (int) $row['title_id']];
            }
            if (count($selected) !== $item->quantity) {
                throw new \RuntimeException('Not enough available copies for this title.');
            }
        }

        return $selected;
    }

    private function forUpdate(string $query): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? $query . ' FOR UPDATE' : $query;
    }

    public function activeApprovedCount(int $userId): int
    {
        if ($this->hasTable('borrowing_items')) {
            $statement = $this->pdo->prepare(
                "SELECT COUNT(*) FROM borrowing_items bi JOIN borrowing_transactions bt ON bt.id = bi.transaction_id
                 WHERE bt.user_id = :user_id AND bi.return_date IS NULL
                   AND bt.approval_status IN ('pending', 'approved') AND bi.status IN ('Pending', 'Borrowed', 'Overdue')"
            );
            $statement->execute(['user_id' => $userId]);
            $normalizedCount = (int) $statement->fetchColumn();
            if ($normalizedCount > 0 || $this->tableCount('borrowing_transactions') > 0) return $normalizedCount;
        }
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM borrowing
             WHERE user_id = :user_id
               AND return_date IS NULL
               AND approval_status IN ('pending', 'approved')
               AND status IN ('Pending', 'Borrowed', 'Overdue')"
        );
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    public function createLoan(
        int $userId,
        int $bookId,
        string $transactionCode,
        DateTimeImmutable $dueDate,
        string $status,
        string $approvalStatus,
    ): int {
        $statement = $this->pdo->prepare(
            'INSERT INTO borrowing (transaction_code, user_id, book_id, processed_by, approval_status, borrow_date, due_date, status, fine_amount) '
            . 'VALUES (:transaction_code, :user_id, :book_id, NULL, :approval_status, CURRENT_TIMESTAMP, :due_date, :status, 0)'
        );
        $statement->execute([
            'transaction_code' => $transactionCode,
            'user_id' => $userId,
            'book_id' => $bookId,
            'approval_status' => $approvalStatus,
            'due_date' => $dueDate->format('Y-m-d'),
            'status' => $status,
        ]);

        if ($approvalStatus === 'approved') {
            $this->setBookStatus($bookId, 'Borrowed');
        }

        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<LoanRecord> */
    public function activeByTransaction(int $userId, string $transactionCode): array
    {
        if ($this->hasTable('borrowing_items')) {
            $statement = $this->pdo->prepare(
                "SELECT bi.id, bi.copy_id AS book_id, bt.transaction_code, bt.due_date
                 FROM borrowing_items bi JOIN borrowing_transactions bt ON bt.id = bi.transaction_id
                 WHERE bt.user_id = :user_id AND bt.transaction_code = :transaction_code
                   AND bi.return_date IS NULL AND bt.approval_status = 'approved' ORDER BY bi.id"
            );
            $statement->execute(['user_id' => $userId, 'transaction_code' => trim($transactionCode)]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            if ($rows !== [] || $this->tableCount('borrowing_transactions') > 0) return $this->loanRecords($rows);
        }
        $statement = $this->pdo->prepare(
            "SELECT id, book_id, transaction_code, due_date FROM borrowing WHERE user_id = :user_id AND transaction_code = :transaction_code AND return_date IS NULL AND approval_status = 'approved'"
        );
        $statement->execute(['user_id' => $userId, 'transaction_code' => trim($transactionCode)]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $this->loanRecords($rows);
    }

    public function findBookByBarcode(string $barcode): ?array
    {
        return $this->findBook($barcode);
    }

    public function activeByBook(int $userId, int $bookId): ?LoanRecord
    {
        if ($this->hasTable('borrowing_items')) {
            $statement = $this->pdo->prepare(
                "SELECT bi.id, bi.copy_id AS book_id, bt.transaction_code, bt.due_date
                 FROM borrowing_items bi JOIN borrowing_transactions bt ON bt.id = bi.transaction_id
                 WHERE bt.user_id = :user_id AND bi.copy_id = :book_id AND bi.return_date IS NULL
                   AND bt.approval_status = 'approved' LIMIT 1"
            );
            $statement->execute(['user_id' => $userId, 'book_id' => $bookId]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            if ($rows !== [] || $this->tableCount('borrowing_transactions') > 0) {
                $records = $this->loanRecords($rows);
                return $records[0] ?? null;
            }
        }
        $statement = $this->pdo->prepare(
            "SELECT id, book_id, transaction_code, due_date FROM borrowing WHERE user_id = :user_id AND book_id = :book_id AND return_date IS NULL AND approval_status = 'approved' LIMIT 1"
        );
        $statement->execute(['user_id' => $userId, 'book_id' => $bookId]);
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $records = $this->loanRecords($rows);

        return $records[0] ?? null;
    }

    public function completeReturn(int $loanId, int $bookId, float $fine): void
    {
        if ($this->hasTable('borrowing_items') && $this->itemExists($loanId)) {
            $this->pdo->prepare(
                "UPDATE borrowing_items SET return_date = CURRENT_TIMESTAMP, status = 'Returned', fine_amount = :fine WHERE id = :id"
            )->execute(['fine' => $fine, 'id' => $loanId]);
            $this->pdo->prepare("UPDATE book_copies SET status = 'Available', return_date = CURRENT_DATE WHERE id = :id")
                ->execute(['id' => $bookId]);
            return;
        }
        $statement = $this->pdo->prepare(
            'UPDATE borrowing SET return_date = CURRENT_TIMESTAMP, status = \'Returned\', fine_amount = :fine WHERE id = :id'
        );
        $statement->execute(['fine' => $fine, 'id' => $loanId]);
        $this->pdo->prepare(
            'UPDATE books SET status = \'Available\', return_date = CURRENT_DATE WHERE id = :id'
        )->execute(['id' => $bookId]);
    }

    private function setBookStatus(int $bookId, string $status): void
    {
        $this->pdo->prepare('UPDATE books SET status = :status WHERE id = :id')->execute([
            'status' => $status,
            'id' => $bookId,
        ]);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<LoanRecord>
     */
    private function loanRecords(array $rows): array
    {
        $records = [];
        foreach ($rows as $row) {
            $dueDateValue = $row['due_date'] ?? null;
            $dueDate = is_string($dueDateValue)
                ? DateTimeImmutable::createFromFormat('!Y-m-d', $dueDateValue)
                : false;
            if ($dueDate === false) {
                continue;
            }
            $records[] = new LoanRecord(
                $this->integerValue($row['id'] ?? null),
                $this->integerValue($row['book_id'] ?? null),
                $this->stringValue($row['transaction_code'] ?? null),
                $dueDate,
            );
        }

        return $records;
    }

    private function integerValue(mixed $value): int
    {
        return is_int($value) ? $value : (is_string($value) && is_numeric($value) ? (int) $value : 0);
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
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

    private function tableCount(string $table): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }

    private function itemExists(int $itemId): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM borrowing_items WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $itemId]);
        return $statement->fetchColumn() !== false;
    }
}
