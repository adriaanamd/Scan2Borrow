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
            $quantity = is_numeric($loan['quantity'] ?? null) ? (int) $loan['quantity'] : 1;
            if (($loan['status'] ?? '') !== 'Returned') {
                $active += $quantity;
            }
            if (($loan['status'] ?? '') === 'Overdue') {
                $overdue += $quantity;
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
            'recent_activity' => $this->recentActivity($userId),
            'due_soon' => [],
            'recommended' => $this->recommendations(),
            'favorite_category' => '',
            'achievements' => [],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function activity(int $userId): array
    {
        $rows = $this->activityRows($userId);
        usort($rows, static function (array $left, array $right): int {
            $dateComparison = strcmp(self::comparableValue($right['occurred_at'] ?? null), self::comparableValue($left['occurred_at'] ?? null));
            if ($dateComparison !== 0) {
                return $dateComparison;
            }

            $typeComparison = strcmp(self::comparableValue($right['type'] ?? null), self::comparableValue($left['type'] ?? null));
            if ($typeComparison !== 0) {
                return $typeComparison;
            }

            return strcmp(self::comparableValue($right['id'] ?? null), self::comparableValue($left['id'] ?? null));
        });

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public function recentActivity(int $userId): array
    {
        return array_slice($this->activity($userId), 0, 5);
    }

    public function history(int $userId): array
    {
        return $this->loans($userId, true);
    }

    public function receipt(int $userId, string $transactionCode): ?array
    {
        if ($this->hasTable('borrowing_items')) {
            $statement = $this->pdo->prepare(
                "SELECT bt.transaction_code, bt.borrow_date, bt.due_date, MAX(bi.return_date) AS return_date,
                        CASE WHEN SUM(CASE WHEN bi.return_date IS NULL THEN 1 ELSE 0 END) > 0 THEN bt.status ELSE 'Returned' END AS status,
                        SUM(bi.fine_amount) AS fine_amount, t.title, t.author, COUNT(bi.id) AS quantity,
                        GROUP_CONCAT(c.barcode) AS barcode
                 FROM borrowing_transactions bt JOIN borrowing_items bi ON bi.transaction_id = bt.id
                 JOIN book_copies c ON c.id = bi.copy_id JOIN book_titles t ON t.id = c.title_id
                 WHERE bt.user_id = :user_id AND bt.transaction_code = :code
                 GROUP BY bt.id, bt.transaction_code, bt.borrow_date, bt.due_date, bt.status, t.id, t.title, t.author
                 ORDER BY t.title"
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
    private function activityRows(int $userId): array
    {
        $rows = $this->hasTable('borrowing_items') && $this->hasTable('borrowing_transactions')
            ? $this->normalizedBorrowingActivity($userId)
            : $this->legacyBorrowingActivity($userId);

        if ($this->hasTable('reservations') && $this->hasTable('book_titles')) {
            $rows = array_merge($rows, $this->reservationActivity($userId));
        }
        if ($this->hasTable('renewal_requests') && $this->hasTable('borrowing_items')) {
            $rows = array_merge($rows, $this->renewalActivity($userId));
        }
        if ($this->hasTable('profile_change_requests')) {
            $rows = array_merge($rows, $this->profileChangeActivity($userId));
        }
        if ($this->hasColumn('users', 'last_login')) {
            $rows = array_merge($rows, $this->loginActivity($userId));
        }
        if ($this->hasTable('audit_log')) {
            $rows = array_merge($rows, $this->auditLogActivity($userId));
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function normalizedBorrowingActivity(int $userId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT bi.id AS item_id, bt.id AS transaction_id, bt.transaction_code, bt.borrow_date,
                    bt.approval_status, bt.status AS transaction_status, bi.status AS item_status,
                    bi.return_date, t.title
             FROM borrowing_items bi
             JOIN borrowing_transactions bt ON bt.id = bi.transaction_id
             JOIN book_copies c ON c.id = bi.copy_id
             JOIN book_titles t ON t.id = c.title_id
             WHERE bt.user_id = :user_id
             ORDER BY bt.borrow_date DESC, bi.id DESC"
        );
        $statement->execute(['user_id' => $userId]);
        $rows = [];
        /** @var list<array<string, mixed>> $fetchedRows */
        $fetchedRows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($fetchedRows as $row) {
            $status = $this->stringValue($row['item_status'] ?? null);
            if ($status === '') {
                $status = $this->stringValue($row['transaction_status'] ?? null);
            }
            $title = $this->stringValue($row['title'] ?? null);
            $transactionCode = $this->stringValue($row['transaction_code'] ?? null);
            $isPending = $this->stringValue($row['approval_status'] ?? null) === 'pending' || $status === 'Pending';
            $rows[] = $this->activityEntry(
                'borrowing:' . $this->stringValue($row['item_id'] ?? null) . ':borrow',
                $isPending ? 'borrow_requested' : 'borrowed',
                $isPending ? 'Borrow request submitted' : 'Borrowed book',
                $isPending ? 'Borrow request for ' . $title : 'Borrowed ' . $title,
                $title,
                $transactionCode,
                $status,
                $this->stringValue($row['borrow_date'] ?? null),
            );
            $returnDate = $this->stringValue($row['return_date'] ?? null);
            if ($returnDate !== '') {
                $rows[] = $this->activityEntry(
                    'borrowing:' . $this->stringValue($row['item_id'] ?? null) . ':return',
                    'returned',
                    'Returned book',
                    'Returned ' . $title,
                    $title,
                    $transactionCode,
                    'Returned',
                    $returnDate,
                );
            }
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function legacyBorrowingActivity(int $userId): array
    {
        if (!$this->hasTable('borrowing')) {
            return [];
        }

        $statement = $this->pdo->prepare(
            'SELECT br.id, br.transaction_code, br.borrow_date, br.return_date, br.status, b.title '
            . 'FROM borrowing br JOIN books b ON b.id = br.book_id '
            . 'WHERE br.user_id = :user_id ORDER BY br.borrow_date DESC, br.id DESC'
        );
        $statement->execute(['user_id' => $userId]);
        $rows = [];
        /** @var list<array<string, mixed>> $fetchedRows */
        $fetchedRows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($fetchedRows as $row) {
            $title = $this->stringValue($row['title'] ?? null);
            $transactionCode = $this->stringValue($row['transaction_code'] ?? null);
            $status = $this->stringValue($row['status'] ?? null);
            $id = $this->stringValue($row['id'] ?? null);
            $rows[] = $this->activityEntry(
                'borrowing:' . $id . ':borrow',
                'borrowed',
                'Borrowed book',
                'Borrowed ' . $title,
                $title,
                $transactionCode,
                $status,
                $this->stringValue($row['borrow_date'] ?? null),
            );
            $returnDate = $this->stringValue($row['return_date'] ?? null);
            if ($returnDate !== '') {
                $rows[] = $this->activityEntry(
                    'borrowing:' . $id . ':return',
                    'returned',
                    'Returned book',
                    'Returned ' . $title,
                    $title,
                    $transactionCode,
                    'Returned',
                    $returnDate,
                );
            }
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function reservationActivity(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT r.id, r.status, r.created_at, r.updated_at, t.title '
            . 'FROM reservations r JOIN book_titles t ON t.id = r.title_id '
            . 'WHERE r.user_id = :user_id ORDER BY r.created_at DESC, r.id DESC'
        );
        $statement->execute(['user_id' => $userId]);
        $rows = [];
        /** @var list<array<string, mixed>> $fetchedRows */
        $fetchedRows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($fetchedRows as $row) {
            $id = $this->stringValue($row['id'] ?? null);
            $title = $this->stringValue($row['title'] ?? null);
            $status = $this->stringValue($row['status'] ?? null);
            $createdAt = $this->stringValue($row['created_at'] ?? null);
            $updatedAt = $this->stringValue($row['updated_at'] ?? null);
            $rows[] = $this->activityEntry(
                'reservation:' . $id . ':created',
                'reservation',
                'Reservation created',
                'Reserved ' . $title,
                $title,
                '',
                $status,
                $createdAt,
            );
            if ($updatedAt !== '' && $updatedAt !== $createdAt) {
                $rows[] = $this->activityEntry(
                    'reservation:' . $id . ':updated',
                    'reservation_updated',
                    'Reservation updated',
                    'Reservation for ' . $title . ' is ' . $status,
                    $title,
                    '',
                    $status,
                    $updatedAt,
                );
            }
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function renewalActivity(int $userId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT rr.id, rr.status, rr.requested_at, rr.decided_at, bt.transaction_code, t.title
             FROM renewal_requests rr
             JOIN borrowing_items bi ON bi.id = rr.loan_id
             JOIN borrowing_transactions bt ON bt.id = bi.transaction_id
             JOIN book_copies c ON c.id = bi.copy_id
             JOIN book_titles t ON t.id = c.title_id
             WHERE rr.user_id = :user_id ORDER BY rr.requested_at DESC, rr.id DESC"
        );
        $statement->execute(['user_id' => $userId]);
        $rows = [];
        /** @var list<array<string, mixed>> $fetchedRows */
        $fetchedRows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($fetchedRows as $row) {
            $id = $this->stringValue($row['id'] ?? null);
            $status = $this->stringValue($row['status'] ?? null);
            $title = $this->stringValue($row['title'] ?? null);
            $transactionCode = $this->stringValue($row['transaction_code'] ?? null);
            $rows[] = $this->activityEntry(
                'renewal:' . $id . ':requested',
                'renewal_requested',
                'Renewal requested',
                'Renewal requested for ' . $title,
                $title,
                $transactionCode,
                $status,
                $this->stringValue($row['requested_at'] ?? null),
            );
            $decidedAt = $this->stringValue($row['decided_at'] ?? null);
            if ($decidedAt !== '') {
                $rows[] = $this->activityEntry(
                    'renewal:' . $id . ':decided',
                    'renewal_' . $status,
                    'Renewal ' . ucfirst($status),
                    'Renewal for ' . $title . ' was ' . $status,
                    $title,
                    $transactionCode,
                    $status,
                    $decidedAt,
                );
            }
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function profileChangeActivity(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, status, requested_at, reviewed_at FROM profile_change_requests '
            . 'WHERE user_id = :user_id ORDER BY requested_at DESC, id DESC'
        );
        $statement->execute(['user_id' => $userId]);
        $rows = [];
        /** @var list<array<string, mixed>> $fetchedRows */
        $fetchedRows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($fetchedRows as $row) {
            $id = $this->stringValue($row['id'] ?? null);
            $status = $this->stringValue($row['status'] ?? null);
            $rows[] = $this->activityEntry(
                'profile:' . $id . ':requested',
                'profile_change_requested',
                'Profile change requested',
                'Profile change request submitted',
                '',
                '',
                $status,
                $this->stringValue($row['requested_at'] ?? null),
            );
            $reviewedAt = $this->stringValue($row['reviewed_at'] ?? null);
            if ($reviewedAt !== '') {
                $rows[] = $this->activityEntry(
                    'profile:' . $id . ':reviewed',
                    'profile_change_' . $status,
                    'Profile change ' . ucfirst($status),
                    'Profile change request was ' . $status,
                    '',
                    '',
                    $status,
                    $reviewedAt,
                );
            }
        }

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private function loginActivity(int $userId): array
    {
        $statement = $this->pdo->prepare('SELECT last_login FROM users WHERE id = :user_id LIMIT 1');
        $statement->execute(['user_id' => $userId]);
        $lastLogin = $this->stringValue($statement->fetchColumn());
        if ($lastLogin === '') {
            return [];
        }

        return [$this->activityEntry('login:' . $userId, 'login', 'Signed in', 'Signed in to Scan2Borrow', '', '', '', $lastLogin)];
    }

    /** @return list<array<string, mixed>> */
    private function auditLogActivity(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, action, details, created_at FROM audit_log WHERE user_id = :user_id ORDER BY created_at DESC, id DESC'
        );
        $statement->execute(['user_id' => $userId]);
        $rows = [];
        /** @var list<array<string, mixed>> $fetchedRows */
        $fetchedRows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($fetchedRows as $row) {
            $action = $this->stringValue($row['action'] ?? null);
            $rows[] = $this->activityEntry(
                'audit:' . $this->stringValue($row['id'] ?? null),
                $action,
                $action === '' ? 'Account activity' : ucfirst(str_replace('_', ' ', $action)),
                $this->stringValue($row['details'] ?? null),
                '',
                '',
                '',
                $this->stringValue($row['created_at'] ?? null),
            );
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function activityEntry(
        mixed $id,
        string $type,
        string $label,
        string $details,
        string $title,
        string $transactionCode,
        string $status,
        string $occurredAt,
    ): array {
        return [
            'id' => is_int($id) || is_string($id) ? $id : '',
            'type' => $type,
            'label' => $label,
            'details' => $details,
            'title' => $title,
            'transaction_code' => $transactionCode,
            'status' => $status,
            'occurred_at' => $occurredAt,
        ];
    }

    private static function comparableValue(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? (string) $value : '';
    }

    /** @return list<array<string, mixed>> */
    private function loans(int $userId, bool $includeReturned): array
    {
        if ($this->hasTable('borrowing_items')) {
            $statusClause = $includeReturned ? '' : ' AND bi.return_date IS NULL';
            $statement = $this->pdo->prepare(
                "SELECT MIN(bi.id) AS id, bt.transaction_code, bt.borrow_date, bt.due_date, MAX(bi.return_date) AS return_date,
                        CASE
                            WHEN SUM(CASE WHEN bi.return_date IS NULL
                                           AND (bi.status = 'Pending' OR bt.approval_status = 'pending')
                                          THEN 1 ELSE 0 END) > 0 THEN 'Pending'
                            WHEN SUM(CASE WHEN bi.return_date IS NULL THEN 1 ELSE 0 END) > 0 THEN bt.status
                            ELSE 'Returned'
                        END AS status,
                        SUM(bi.fine_amount) AS fine_amount, t.title, t.author, COUNT(bi.id) AS quantity,
                        GROUP_CONCAT(c.barcode) AS barcode
                 FROM borrowing_items bi JOIN borrowing_transactions bt ON bt.id = bi.transaction_id
                 JOIN book_copies c ON c.id = bi.copy_id JOIN book_titles t ON t.id = c.title_id
                 WHERE bt.user_id = :user_id" . $statusClause . "
                 GROUP BY bt.id, bt.transaction_code, bt.borrow_date, bt.due_date, bt.status, t.id, t.title, t.author
                 ORDER BY bt.borrow_date DESC, MIN(bi.id) DESC"
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

    private function hasColumn(string $table, string $column): bool
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $this->pdo->query('PRAGMA table_info(' . $table . ')');
            if ($statement === false) {
                return false;
            }
            while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                if (($row['name'] ?? null) === $column) {
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
}
