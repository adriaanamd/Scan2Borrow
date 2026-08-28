<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

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
        $statement = $this->pdo->prepare(
            'SELECT id, barcode, title, author, status, due_date, return_date FROM books WHERE barcode = :barcode AND deleted_at IS NULL LIMIT 1'
        );
        $statement->execute(['barcode' => trim($barcode)]);
        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function activeApprovedCount(int $userId): int
    {
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
}
