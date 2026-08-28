<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;
use RuntimeException;

final class PdoStaffRepository implements StaffRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function dashboard(): array
    {
        return [
            'stats' => [
                'total_books' => $this->count('SELECT COUNT(*) FROM books WHERE deleted_at IS NULL'),
                'available_books' => $this->count("SELECT COUNT(*) FROM books WHERE deleted_at IS NULL AND status = 'Available'"),
                'borrowed_books' => $this->count("SELECT COUNT(*) FROM books WHERE deleted_at IS NULL AND status = 'Borrowed'"),
                'borrowers' => $this->count("SELECT COUNT(*) FROM users WHERE role IN ('student','teacher')"),
                'active_loans' => $this->count('SELECT COUNT(*) FROM borrowing WHERE return_date IS NULL'),
                'overdue_loans' => $this->count("SELECT COUNT(*) FROM borrowing WHERE return_date IS NULL AND status = 'Overdue'"),
                'pending_approvals' => $this->count("SELECT COUNT(*) FROM borrowing WHERE approval_status = 'pending' AND return_date IS NULL"),
            ],
            'recent' => $this->recentTransactions(),
            'pending' => $this->pendingBorrowings(),
        ];
    }

    public function borrowers(string $search): array
    {
        $sql = "SELECT u.id, u.barcode, u.firstname, u.lastname, u.role, u.department, u.position, u.course, u.year_level, u.status,
                (SELECT COUNT(*) FROM borrowing br WHERE br.user_id = u.id AND br.return_date IS NULL) AS active_loans,
                (SELECT COUNT(*) FROM borrowing br WHERE br.user_id = u.id AND br.return_date IS NULL AND br.status = 'Overdue') AS overdue_loans
                FROM users u WHERE u.role IN ('student','teacher')";
        $parameters = [];
        if (trim($search) !== '') {
            $sql .= ' AND (u.barcode LIKE :search OR u.firstname LIKE :search OR u.lastname LIKE :search OR u.course LIKE :search)';
            $parameters['search'] = '%' . trim($search) . '%';
        }
        $sql .= ' ORDER BY u.lastname ASC, u.firstname ASC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        /** @var list<array<string, mixed>> $rows */
        /** @var list<array<string, mixed>> $rows */
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['name'] = trim($this->string($row['firstname'] ?? null) . ' ' . $this->string($row['lastname'] ?? null));
        }
        unset($row);

        return $rows;
    }

    public function overdue(): array
    {
        $statement = $this->pdo->query(
            "SELECT br.id, br.due_date, br.fine_amount, b.title, b.barcode AS book_barcode,
                    u.id AS user_id, u.barcode AS id_barcode, u.email,
                    u.firstname, u.lastname,
                    DATEDIFF(CURRENT_DATE, br.due_date) AS days_late
             FROM borrowing br
             JOIN books b ON b.id = br.book_id
             JOIN users u ON u.id = br.user_id
             WHERE br.return_date IS NULL AND br.status = 'Overdue'
             ORDER BY br.due_date ASC"
        );
        if ($statement === false) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        /** @var list<array<string, mixed>> $rows */
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['borrower'] = trim($this->string($row['firstname'] ?? null) . ' ' . $this->string($row['lastname'] ?? null));
        }
        unset($row);

        return $rows;
    }

    public function report(string $type, string $from, string $to): array
    {
        $labels = [
            'borrowed' => 'Borrowed Books',
            'returned' => 'Returned Books',
            'overdue' => 'Overdue Books',
            'inventory' => 'Inventory Status',
        ];
        $type = isset($labels[$type]) ? $type : 'borrowed';

        if ($type === 'inventory') {
            $statement = $this->pdo->query(
                "SELECT barcode, title, author, category_name, status,
                        CONCAT('Floor ', floor_no, ' / ', section_name, ' / Shelf ', shelf_no) AS location
                 FROM books WHERE deleted_at IS NULL ORDER BY title ASC"
            );
            $rows = $statement === false ? [] : $statement->fetchAll(PDO::FETCH_ASSOC);
            $data = [];
            foreach ($rows as $row) {
                $data[] = [
                    $this->string($row['barcode'] ?? null),
                    $this->string($row['title'] ?? null),
                    $this->string($row['author'] ?? null),
                    $this->string($row['category_name'] ?? null),
                    $this->string($row['status'] ?? null),
                    $this->string($row['location'] ?? null),
                ];
            }

            return ['label' => $labels[$type], 'headers' => ['Barcode', 'Title', 'Author', 'Category', 'Status', 'Location'], 'data' => $data];
        }

        $where = [];
        $parameters = [];
        if ($type === 'returned') {
            $where[] = 'br.return_date IS NOT NULL';
            $dateColumn = 'br.return_date';
        } elseif ($type === 'overdue') {
            $where[] = "br.return_date IS NULL AND br.status = 'Overdue'";
            $dateColumn = 'br.borrow_date';
        } else {
            $dateColumn = 'br.borrow_date';
        }
        if ($from !== '') {
            $where[] = 'DATE(' . $dateColumn . ') >= :from_date';
            $parameters['from_date'] = $from;
        }
        if ($to !== '') {
            $where[] = 'DATE(' . $dateColumn . ') <= :to_date';
            $parameters['to_date'] = $to;
        }

        $statement = $this->pdo->prepare(
            'SELECT br.transaction_code, CONCAT(u.firstname, \' \', u.lastname) AS borrower, u.barcode AS id_barcode,
                    b.title, br.borrow_date, br.due_date, br.return_date, br.status, br.fine_amount
             FROM borrowing br JOIN users u ON u.id = br.user_id JOIN books b ON b.id = br.book_id ' .
            ($where === [] ? '' : 'WHERE ' . implode(' AND ', $where)) . ' ORDER BY br.id DESC'
        );
        $statement->execute($parameters);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                $this->string($row['transaction_code'] ?? null),
                $this->string($row['borrower'] ?? null),
                $this->string($row['id_barcode'] ?? null),
                $this->string($row['title'] ?? null),
                $this->date($row['borrow_date'] ?? null),
                $this->date($row['due_date'] ?? null),
                $this->date($row['return_date'] ?? null),
                $this->string($row['status'] ?? null),
                number_format($this->number($row['fine_amount'] ?? null), 2),
            ];
        }

        return ['label' => $labels[$type], 'headers' => ['Code', 'Borrower', 'ID', 'Book', 'Borrowed', 'Due', 'Returned', 'Status', 'Fine'], 'data' => $data];
    }

    public function guestRequests(): array
    {
        $statement = $this->pdo->query(
            'SELECT vb.id, vb.visitor_id, vb.book_id, vb.due_date, vb.request_status, vb.verification_photo, vb.requested_at, vb.created_at,
                    v.firstname, v.lastname, v.visitor_number, v.photo AS visitor_photo, v.id_barcode,
                    b.title, b.author, b.accession_no, b.barcode
             FROM visitor_borrowing vb JOIN visitors v ON v.id = vb.visitor_id JOIN books b ON b.id = vb.book_id
             WHERE vb.request_status = \'Pending\' ORDER BY vb.requested_at ASC'
        );
        if ($statement === false) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['name'] = trim($this->string($row['firstname'] ?? null) . ' ' . $this->string($row['lastname'] ?? null));
            $accession = $this->string($row['accession_no'] ?? null);
            $row['accession'] = $accession !== '' ? $accession : $this->string($row['barcode'] ?? null);
        }
        unset($row);

        return $rows;
    }

    public function staffAccounts(): array
    {
        $statement = $this->pdo->query("SELECT id, barcode, firstname, lastname, role, email, status FROM users WHERE role IN ('admin','librarian') ORDER BY role ASC, lastname ASC");
        if ($statement === false) {
            return [];
        }
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['name'] = trim($this->string($row['firstname'] ?? null) . ' ' . $this->string($row['lastname'] ?? null));
        }
        unset($row);

        return $rows;
    }

    public function borrowerCandidates(string $search): array
    {
        $sql = "SELECT id, barcode, firstname, lastname, course FROM users WHERE role IN ('student','teacher')";
        $parameters = [];
        if (trim($search) !== '') {
            $sql .= ' AND (barcode LIKE :search OR firstname LIKE :search OR lastname LIKE :search)';
            $parameters['search'] = '%' . trim($search) . '%';
        }
        $sql .= ' ORDER BY lastname ASC LIMIT 25';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['name'] = trim($this->string($row['firstname'] ?? null) . ' ' . $this->string($row['lastname'] ?? null));
        }
        unset($row);

        return $rows;
    }

    public function pendingBorrowings(): array
    {
        $statement = $this->pdo->query(
            "SELECT br.id, br.transaction_code, br.borrow_date, br.due_date, br.status, br.user_id, br.book_id,
                    u.firstname, u.lastname, u.barcode AS id_barcode,
                    b.title, b.author, b.barcode AS book_barcode, b.cover_file
             FROM borrowing br JOIN users u ON u.id = br.user_id JOIN books b ON b.id = br.book_id
             WHERE br.approval_status = 'pending' AND br.return_date IS NULL ORDER BY br.requested_at ASC, br.id ASC"
        );
        if ($statement === false) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['borrower'] = trim($this->string($row['firstname'] ?? null) . ' ' . $this->string($row['lastname'] ?? null));
        }
        unset($row);

        return $rows;
    }

    public function notifications(int $staffId, string $type): array
    {
        if ($type === 'pending_approvals') {
            return $this->pendingBorrowings();
        }
        if ($type === 'return_notifications') {
            $statement = $this->pdo->prepare(
                'SELECT rn.*, u.firstname, u.lastname, b.title, b.barcode AS book_barcode FROM return_notifications rn
                 JOIN users u ON u.id = rn.user_id JOIN books b ON b.id = rn.book_id
                 WHERE rn.user_id = :staff_id AND rn.is_viewed = 0 ORDER BY rn.created_at DESC'
            );
        } else {
            $statement = $this->pdo->prepare(
                "SELECT n.*, u.firstname, u.lastname FROM notifications n JOIN users u ON u.id = n.user_id
                 WHERE n.user_id = :staff_id AND n.type = 'borrow_notification' AND n.is_read = 0 ORDER BY n.created_at DESC"
            );
        }
        $statement->execute(['staff_id' => $staffId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['borrower'] = trim($this->string($row['firstname'] ?? null) . ' ' . $this->string($row['lastname'] ?? null));
        }
        unset($row);

        return $rows;
    }

    public function markNotificationViewed(int $notificationId, int $staffId, string $type): void
    {
        if ($type === 'return') {
            $statement = $this->pdo->prepare('UPDATE return_notifications SET is_viewed = 1, viewed_at = CURRENT_TIMESTAMP WHERE id = :id AND user_id = :staff_id');
        } else {
            $statement = $this->pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :staff_id');
        }
        $statement->execute(['id' => $notificationId, 'staff_id' => $staffId]);
    }

    public function approveBorrowing(int $borrowingId, int $staffId): void
    {
        $this->changeBorrowing($borrowingId, $staffId, true);
    }

    public function rejectBorrowing(int $borrowingId, int $staffId): void
    {
        $this->changeBorrowing($borrowingId, $staffId, false);
    }

    public function promote(int $userId, string $role, string $password): void
    {
        $role = in_array($role, ['admin', 'librarian'], true) ? $role : 'librarian';
        $statement = $this->pdo->prepare("UPDATE users SET role = :role, password_hash = :password_hash, status = 'active' WHERE id = :id");
        $statement->execute(['role' => $role, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $userId]);
    }

    public function resetPassword(int $userId, string $password): void
    {
        $statement = $this->pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
        $statement->execute(['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $userId]);
    }

    public function demote(int $userId): void
    {
        $this->pdo->prepare("UPDATE users SET role = 'student', password_hash = NULL WHERE id = :id AND role IN ('admin','librarian')")->execute(['id' => $userId]);
    }

    public function toggleStatus(int $userId): void
    {
        $this->pdo->prepare("UPDATE users SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id = :id")->execute(['id' => $userId]);
    }

    /** @return list<array<string, mixed>> */
    private function recentTransactions(): array
    {
        $statement = $this->pdo->query(
            "SELECT br.transaction_code, br.borrow_date, br.due_date, br.status,
                    u.firstname, u.lastname, b.title
             FROM borrowing br JOIN users u ON u.id = br.user_id JOIN books b ON b.id = br.book_id
             ORDER BY br.id DESC LIMIT 8"
        );
        if ($statement === false) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['borrower'] = trim($this->string($row['firstname'] ?? null) . ' ' . $this->string($row['lastname'] ?? null));
        }
        unset($row);

        return $rows;
    }

    private function changeBorrowing(int $borrowingId, int $staffId, bool $approve): void
    {
        $this->pdo->beginTransaction();
        try {
            $status = $approve ? 'approved' : 'rejected';
            $statement = $this->pdo->prepare(
                'UPDATE borrowing SET approval_status = :approval_status, status = CASE WHEN :is_approved = 1 THEN \'Borrowed\' ELSE status END,
                 approved_at = CURRENT_TIMESTAMP, approved_by = :staff_id WHERE id = :id'
            );
            $statement->execute([
                'approval_status' => $status,
                'is_approved' => $approve ? 1 : 0,
                'staff_id' => $staffId,
                'id' => $borrowingId,
            ]);
            if ($approve) {
                $bookStatement = $this->pdo->prepare('SELECT book_id FROM borrowing WHERE id = :id LIMIT 1');
                $bookStatement->execute(['id' => $borrowingId]);
                $bookId = $bookStatement->fetchColumn();
                if (!is_numeric($bookId)) {
                    throw new RuntimeException('Borrowing record was not found.');
                }
                $this->pdo->prepare("UPDATE books SET status = 'Borrowed' WHERE id = :book_id")->execute(['book_id' => (int) $bookId]);
            }
            $this->pdo->prepare('UPDATE notifications SET is_read = 1 WHERE related_id = :id AND user_id = :staff_id')->execute(['id' => $borrowingId, 'staff_id' => $staffId]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException('Borrowing decision could not be saved.', 0, $exception);
        }
    }

    private function count(string $sql): int
    {
        $statement = $this->pdo->query($sql);
        if ($statement === false) {
            return 0;
        }

        return (int) $statement->fetchColumn();
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function date(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            return '';
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? '' : date('Y-m-d', $timestamp);
    }
}
