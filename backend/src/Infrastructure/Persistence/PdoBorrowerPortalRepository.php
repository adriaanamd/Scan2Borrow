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
        $history = $this->history($userId);
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
                'on_time_rate' => $this->onTimeRate($history),
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

    /** @param list<array<string, mixed>> $history */
    private function onTimeRate(array $history): int
    {
        $returned = array_values(array_filter($history, static fn (array $row): bool => ($row['status'] ?? '') === 'Returned'));
        if ($returned === []) {
            return 100;
        }

        $onTime = 0;
        foreach ($returned as $row) {
            $returnDate = $this->stringValue($row['return_date'] ?? null);
            $dueDate = $this->stringValue($row['due_date'] ?? null);
            if ($returnDate <= $dueDate) {
                $onTime++;
            }
        }

        return (int) round($onTime / count($returned) * 100);
    }

    /** @return list<array<string, mixed>> */
    private function recommendations(): array
    {
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
}
