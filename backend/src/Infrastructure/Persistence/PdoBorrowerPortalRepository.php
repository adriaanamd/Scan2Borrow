<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;

final class PdoBorrowerPortalRepository implements BorrowerPortalRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function dashboard(int $userId): array
    {
        $userStatement = $this->pdo->prepare(
            'SELECT id, barcode, firstname, middlename, lastname, department, position, course, year_level, photo, role FROM users WHERE id = :id LIMIT 1'
        );
        $userStatement->execute(['id' => $userId]);
        /** @var array<string, mixed>|false $user */
        $user = $userStatement->fetch(PDO::FETCH_ASSOC);
        if ($user === false) {
            return [];
        }

        $loanRows = $this->loans($userId, false);
        $historySummary = $this->historySummary($userId);
        $active = 0;
        $overdue = 0;
        $fines = 0.0;
        foreach ($loanRows as $loan) {
            if (($loan['status'] ?? '') !== 'Returned') {
                $active++;
            }
            if (($loan['status'] ?? '') === 'Overdue') {
                $overdue++;
            }
            $fineValue = $loan['fine_amount'] ?? 0;
            $fines += is_numeric($fineValue) ? (float) $fineValue : 0.0;
        }

        $name = trim($this->stringValue($user['firstname'] ?? null) . ' ' . $this->stringValue($user['lastname'] ?? null));

        return [
            'user' => [
                'name' => $name,
                'barcode' => $this->stringValue($user['barcode'] ?? null),
                'role' => ucfirst($this->stringValue($user['role'] ?? 'student')),
                'course' => $this->stringValue($user['course'] ?? null),
                'year_level' => $this->stringValue($user['year_level'] ?? null),
                'department' => $this->stringValue($user['department'] ?? null),
                'position' => $this->stringValue($user['position'] ?? null),
                'photo' => $this->stringValue($user['photo'] ?? null),
            ],
            'stats' => [
                'active' => $active,
                'overdue' => $overdue,
                'fines' => $fines,
                'on_time_rate' => $this->onTimeRate(
                    $historySummary['returned_count'],
                    $historySummary['on_time_count'],
                ),
            ],
            'max_books' => 3,
            'current_loans' => $loanRows,
            'due_soon' => [],
            'recommended' => $this->recommendations(),
            'favorite_category' => '',
            'achievements' => [],
        ];
    }

    public function history(int $userId): array
    {
        return $this->loans($userId, true);
    }

    public function receipt(int $userId, string $transactionCode): ?array
    {
        if ($this->hasTable('borrowing_items')) {
            $statement = $this->pdo->prepare(
                'SELECT bt.transaction_code, bt.borrow_date, bt.due_date, bi.return_date, bi.status, bi.fine_amount, t.title, t.author, c.barcode
                 FROM borrowing_transactions bt JOIN borrowing_items bi ON bi.transaction_id = bt.id
                 JOIN book_copies c ON c.id = bi.copy_id JOIN book_titles t ON t.id = c.title_id
                 WHERE bt.user_id = :user_id AND bt.transaction_code = :code ORDER BY bi.id'
            );
            $statement->execute(['user_id' => $userId, 'code' => trim($transactionCode)]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            if ($rows === []) return null;
            return ['transaction_code' => (string) $rows[0]['transaction_code'], 'books' => $rows];
        }
        $statement = $this->pdo->prepare(
            'SELECT br.transaction_code, br.borrow_date, br.due_date, br.return_date, br.status, br.fine_amount, b.title, b.author, b.barcode '
            . 'FROM borrowing br JOIN books b ON b.id = br.book_id WHERE br.user_id = :user_id AND br.transaction_code = :code ORDER BY br.id'
        );
        $statement->execute(['user_id' => $userId, 'code' => trim($transactionCode)]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            return null;
        }

        return ['transaction_code' => (string) $rows[0]['transaction_code'], 'books' => $rows];
    }

    /** @return list<array<string, mixed>> */
    private function loans(int $userId, bool $includeReturned): array
    {
        if ($this->hasTable('borrowing_items')) {
            $statusClause = $includeReturned ? '' : ' AND bi.return_date IS NULL';
            $statement = $this->pdo->prepare(
                'SELECT bi.id, bt.transaction_code, bt.borrow_date, bt.due_date, bi.return_date, bi.status, bi.fine_amount, t.title, t.author, c.barcode
                 FROM borrowing_items bi JOIN borrowing_transactions bt ON bt.id = bi.transaction_id
                 JOIN book_copies c ON c.id = bi.copy_id JOIN book_titles t ON t.id = c.title_id
                 WHERE bt.user_id = :user_id' . $statusClause . ' ORDER BY bt.borrow_date DESC, bi.id DESC'
            );
            $statement->execute(['user_id' => $userId]);
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        }
        $statusClause = $includeReturned ? '' : " AND br.return_date IS NULL";
        $statement = $this->pdo->prepare(
            'SELECT br.id, br.transaction_code, br.borrow_date, br.due_date, br.return_date, br.status, br.fine_amount, b.title, b.author, b.barcode '
            . 'FROM borrowing br JOIN books b ON b.id = br.book_id WHERE br.user_id = :user_id' . $statusClause . ' ORDER BY br.borrow_date DESC, br.id DESC'
        );
        $statement->execute(['user_id' => $userId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    /** @return array{returned_count: int, on_time_count: int} */
    private function historySummary(int $userId): array
    {
        if ($this->hasTable('borrowing_items')) {
            $statement = $this->pdo->prepare(
                "SELECT COUNT(*) AS returned_count,
                        COALESCE(SUM(CASE WHEN COALESCE(bi.return_date, '') <= COALESCE(bt.due_date, '') THEN 1 ELSE 0 END), 0) AS on_time_count
                 FROM borrowing_items bi JOIN borrowing_transactions bt ON bt.id = bi.transaction_id
                 WHERE bt.user_id = :user_id AND bi.status = 'Returned'"
            );
            $statement->execute(['user_id' => $userId]);
            $summary = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
            return [
                'returned_count' => is_numeric($summary['returned_count'] ?? null) ? (int) $summary['returned_count'] : 0,
                'on_time_count' => is_numeric($summary['on_time_count'] ?? null) ? (int) $summary['on_time_count'] : 0,
            ];
        }
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) AS returned_count,
                    COALESCE(SUM(CASE WHEN COALESCE(return_date, '') <= COALESCE(due_date, '') THEN 1 ELSE 0 END), 0) AS on_time_count
             FROM borrowing
             WHERE user_id = :user_id AND status = 'Returned'"
        );
        $statement->execute(['user_id' => $userId]);
        /** @var array<string, mixed>|false $summary */
        $summary = $statement->fetch(PDO::FETCH_ASSOC);

        return [
            'returned_count' => is_numeric($summary['returned_count'] ?? null) ? (int) $summary['returned_count'] : 0,
            'on_time_count' => is_numeric($summary['on_time_count'] ?? null) ? (int) $summary['on_time_count'] : 0,
        ];
    }

    private function onTimeRate(int $returnedCount, int $onTimeCount): int
    {
        if ($returnedCount === 0) {
            return 100;
        }

        return (int) round($onTimeCount / $returnedCount * 100);
    }

    /** @return list<array<string, mixed>> */
    private function recommendations(): array
    {
        if ($this->hasTable('book_titles')) {
            $statement = $this->pdo->query(
                "SELECT t.title, t.author, t.category_name AS category, MIN(c.floor_no) AS floor_no
                 FROM book_titles t JOIN book_copies c ON c.title_id = t.id
                 WHERE c.status = 'Available' AND c.deleted_at IS NULL GROUP BY t.id ORDER BY t.created_at DESC LIMIT 6"
            );
            return $statement === false ? [] : $statement->fetchAll(PDO::FETCH_ASSOC);
        }
        $statement = $this->pdo->query(
            "SELECT title, author, category_name AS category, floor_no FROM books WHERE status = 'Available' AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 6"
        );
        if ($statement === false) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
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
}
