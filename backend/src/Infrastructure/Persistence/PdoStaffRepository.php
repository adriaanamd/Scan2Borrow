<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class PdoStaffRepository implements StaffRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?AuditEventRepositoryInterface $audit = null,
    )
    {
    }

    public function dashboard(): array
    {
        return [
            'stats' => $this->dashboardStats(),
            'recent' => $this->recentTransactions(),
            'pending' => $this->pendingBorrowings(),
            'overview' => $this->dashboardOverview(),
        ];
    }

    public function borrowers(string $search): array
    {
        if ($this->hasTable('borrowing_items')) {
            $sql = "SELECT u.id, u.barcode, u.firstname, u.lastname, u.role, u.department, u.position, u.course, u.year_level, u.status,
                    COALESCE(SUM(CASE WHEN bi.return_date IS NULL THEN 1 ELSE 0 END), 0) AS active_loans,
                    COALESCE(SUM(CASE WHEN bi.return_date IS NULL AND bt.status = 'Overdue' THEN 1 ELSE 0 END), 0) AS overdue_loans
                    FROM users u
                    LEFT JOIN borrowing_transactions bt ON bt.user_id = u.id
                    LEFT JOIN borrowing_items bi ON bi.transaction_id = bt.id
                    WHERE u.role IN ('student','teacher')";
            $parameters = [];
            if (trim($search) !== '') {
                $sql .= ' AND (u.barcode LIKE :search OR u.firstname LIKE :search OR u.lastname LIKE :search OR u.course LIKE :search)';
                $parameters['search'] = '%' . trim($search) . '%';
            }
            $sql .= ' GROUP BY u.id, u.barcode, u.firstname, u.lastname, u.role, u.department, u.position, u.course, u.year_level, u.status ORDER BY u.lastname ASC, u.firstname ASC';
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

        $sql = "SELECT u.id, u.barcode, u.firstname, u.lastname, u.role, u.department, u.position, u.course, u.year_level, u.status,
                COALESCE(loan_stats.active_loans, 0) AS active_loans,
                COALESCE(loan_stats.overdue_loans, 0) AS overdue_loans
                FROM users u
                LEFT JOIN (
                    SELECT user_id,
                           SUM(CASE WHEN return_date IS NULL THEN 1 ELSE 0 END) AS active_loans,
                           SUM(CASE WHEN return_date IS NULL AND status = 'Overdue' THEN 1 ELSE 0 END) AS overdue_loans
                    FROM borrowing
                    GROUP BY user_id
                ) AS loan_stats ON loan_stats.user_id = u.id
                WHERE u.role IN ('student','teacher')";
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

    public function borrowerDetails(int $userId): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, barcode, firstname, lastname, role, department, position, course, year_level,
                    email, contact_no, photo, status
             FROM users WHERE id = :id AND role IN ('student', 'teacher') LIMIT 1"
        );
        $statement->execute(['id' => $userId]);
        $borrower = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($borrower)) {
            return null;
        }

        $borrower['name'] = trim($this->string($borrower['firstname'] ?? null) . ' ' . $this->string($borrower['lastname'] ?? null));

        $historyStatement = $this->pdo->prepare($this->hasTable('borrowing_items')
            ? "SELECT MIN(bi.id) AS id, bt.transaction_code, bt.borrow_date, bt.due_date, MAX(bi.return_date) AS return_date,
                      CASE WHEN SUM(CASE WHEN bi.return_date IS NULL THEN 1 ELSE 0 END) > 0 THEN bt.status ELSE 'Returned' END AS status,
                      SUM(bi.fine_amount) AS fine_amount, t.title, t.author, COUNT(bi.id) AS quantity
               FROM borrowing_items bi JOIN borrowing_transactions bt ON bt.id = bi.transaction_id
               JOIN book_copies c ON c.id = bi.copy_id JOIN book_titles t ON t.id = c.title_id
               WHERE bt.user_id = :user_id
               GROUP BY bt.id, bt.transaction_code, bt.borrow_date, bt.due_date, bt.status, t.id, t.title, t.author
               ORDER BY bt.borrow_date DESC"
            : 'SELECT br.id, br.transaction_code, br.borrow_date, br.due_date, br.return_date, br.status, br.fine_amount,
                    b.title, b.author, 1 AS quantity
             FROM borrowing br JOIN books b ON b.id = br.book_id
             WHERE br.user_id = :user_id ORDER BY br.borrow_date DESC');
        $historyStatement->execute(['user_id' => $userId]);
        /** @var list<array<string, mixed>> $history */
        $history = $historyStatement->fetchAll(PDO::FETCH_ASSOC);

        $active = 0;
        $returned = 0;
        $overdue = 0;
        $totalFine = 0.0;
        foreach ($history as $row) {
            $quantity = is_numeric($row['quantity'] ?? null) ? (int) $row['quantity'] : 1;
            if ($row['return_date'] !== null && $row['return_date'] !== '') {
                $returned += $quantity;
                continue;
            }
            $active += $quantity;
            if ($row['status'] === 'Overdue') {
                $overdue += $quantity;
                $totalFine += $this->number($row['fine_amount'] ?? null);
            }
        }

        return [
            'borrower' => $borrower,
            'summary' => [
                'active' => $active,
                'returned' => $returned,
                'overdue' => $overdue,
                'total_fine' => $totalFine,
            ],
            'history' => $history,
        ];
    }

    public function updateBorrowerPhoto(int $userId, string $photoPath): void
    {
        $statement = $this->pdo->prepare("UPDATE users SET photo = :photo WHERE id = :id AND role IN ('student', 'teacher')");
        $statement->execute(['photo' => $photoPath, 'id' => $userId]);
    }

    public function overdue(): array
    {
        if ($this->hasTable('borrowing_items')) {
            $statement = $this->pdo->query(
                "SELECT MIN(bi.id) AS id, bt.due_date, SUM(bi.fine_amount) AS fine_amount,
                        t.title, COUNT(bi.id) AS quantity, GROUP_CONCAT(c.barcode) AS book_barcode,
                        u.id AS user_id, u.barcode AS id_barcode, u.email,
                        u.firstname, u.lastname,
                        DATEDIFF(CURRENT_DATE, bt.due_date) AS days_late
                 FROM borrowing_items bi
                 JOIN borrowing_transactions bt ON bt.id = bi.transaction_id
                 JOIN book_copies c ON c.id = bi.copy_id
                 JOIN book_titles t ON t.id = c.title_id
                 JOIN users u ON u.id = bt.user_id
                 WHERE bi.return_date IS NULL AND bt.status = 'Overdue'
                 GROUP BY bt.id, bt.due_date, t.id, t.title, u.id, u.barcode, u.email, u.firstname, u.lastname
                 ORDER BY bt.due_date ASC"
            );
            if ($statement === false) return [];
            /** @var list<array<string, mixed>> $rows */
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$row) {
                $row['borrower'] = trim($this->string($row['firstname'] ?? null) . ' ' . $this->string($row['lastname'] ?? null));
            }
            unset($row);
            return $rows;
        }

        $statement = $this->pdo->query(
            "SELECT br.id, br.due_date, br.fine_amount, b.title, 1 AS quantity, b.barcode AS book_barcode,
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
            if ($this->hasTable('book_copies')) {
                $statement = $this->pdo->query(
                    "SELECT GROUP_CONCAT(c.barcode) AS barcodes, t.title, t.author, t.category_name,
                            COUNT(c.id) AS quantity,
                            SUM(CASE WHEN c.status = 'Available' THEN 1 ELSE 0 END) AS available_quantity,
                            SUM(CASE WHEN c.status = 'Borrowed' THEN 1 ELSE 0 END) AS borrowed_quantity,
                            SUM(CASE WHEN c.status = 'Reserved' THEN 1 ELSE 0 END) AS reserved_quantity,
                            CASE WHEN SUM(CASE WHEN c.status = 'Available' THEN 1 ELSE 0 END) > 0 THEN 'Available'
                                 WHEN SUM(CASE WHEN c.status = 'Borrowed' THEN 1 ELSE 0 END) > 0 THEN 'Borrowed'
                                 ELSE 'Reserved' END AS status,
                            CONCAT('Floor ', MIN(c.floor_no), ' / ', MIN(c.section_name), ' / Shelf ', MIN(c.shelf_no)) AS location
                     FROM book_titles t JOIN book_copies c ON c.title_id = t.id
                     WHERE c.deleted_at IS NULL GROUP BY t.id, t.title, t.author, t.category_name ORDER BY t.title ASC"
                );
                $rows = $statement === false ? [] : $statement->fetchAll(PDO::FETCH_ASSOC);
                $data = [];
                foreach ($rows as $row) {
                    $data[] = [
                        $this->string($row['barcodes'] ?? null),
                        $this->string($row['title'] ?? null),
                        $this->string($row['author'] ?? null),
                        $this->string($row['category_name'] ?? null),
                        (int) ($row['quantity'] ?? 0),
                        (int) ($row['available_quantity'] ?? 0),
                        (int) ($row['borrowed_quantity'] ?? 0),
                        (int) ($row['reserved_quantity'] ?? 0),
                        $this->string($row['status'] ?? null),
                        $this->string($row['location'] ?? null),
                    ];
                }
                return ['label' => $labels[$type], 'headers' => ['Barcode(s)', 'Title', 'Author', 'Category', 'Quantity', 'Available', 'Borrowed', 'Reserved', 'Status', 'Location'], 'data' => $data];
            }

            $statement = $this->pdo->query(
                "SELECT barcode, title, author, category_name, 1 AS quantity, status,
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
                    1,
                    $this->string($row['status'] ?? null),
                    $this->string($row['location'] ?? null),
                ];
            }

            return ['label' => $labels[$type], 'headers' => ['Barcode', 'Title', 'Author', 'Category', 'Quantity', 'Status', 'Location'], 'data' => $data];
        }

        if ($this->hasTable('borrowing_items')) {
            $where = [];
            $parameters = [];
            if ($type === 'returned') {
                $where[] = 'bi.return_date IS NOT NULL';
                $dateColumn = 'bi.return_date';
            } elseif ($type === 'overdue') {
                $where[] = "bi.return_date IS NULL AND bt.status = 'Overdue'";
                $dateColumn = 'bt.borrow_date';
            } else {
                $dateColumn = 'bt.borrow_date';
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
                'SELECT bt.transaction_code, CONCAT(u.firstname, \' \', u.lastname) AS borrower, u.barcode AS id_barcode,
                        t.title, COUNT(bi.id) AS quantity, bt.borrow_date, bt.due_date, MAX(bi.return_date) AS return_date,
                        CASE WHEN SUM(CASE WHEN bi.return_date IS NULL THEN 1 ELSE 0 END) > 0 THEN bt.status ELSE \'Returned\' END AS status,
                        SUM(bi.fine_amount) AS fine_amount
                 FROM borrowing_items bi JOIN borrowing_transactions bt ON bt.id = bi.transaction_id
                 JOIN users u ON u.id = bt.user_id JOIN book_copies c ON c.id = bi.copy_id JOIN book_titles t ON t.id = c.title_id ' .
                ($where === [] ? '' : 'WHERE ' . implode(' AND ', $where)) .
                ' GROUP BY bt.id, bt.transaction_code, u.firstname, u.lastname, u.barcode, t.id, t.title, bt.borrow_date, bt.due_date, bt.status ORDER BY bt.id DESC'
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
                    (int) ($row['quantity'] ?? 0),
                    $this->date($row['borrow_date'] ?? null),
                    $this->date($row['due_date'] ?? null),
                    $this->date($row['return_date'] ?? null),
                    $this->string($row['status'] ?? null),
                    number_format($this->number($row['fine_amount'] ?? null), 2),
                ];
            }
            return ['label' => $labels[$type], 'headers' => ['Code', 'Borrower', 'ID', 'Book', 'Quantity', 'Borrowed', 'Due', 'Returned', 'Status', 'Fine'], 'data' => $data];
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
                    b.title, 1 AS quantity, br.borrow_date, br.due_date, br.return_date, br.status, br.fine_amount
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
                1,
                $this->date($row['borrow_date'] ?? null),
                $this->date($row['due_date'] ?? null),
                $this->date($row['return_date'] ?? null),
                $this->string($row['status'] ?? null),
                number_format($this->number($row['fine_amount'] ?? null), 2),
            ];
        }

        return ['label' => $labels[$type], 'headers' => ['Code', 'Borrower', 'ID', 'Book', 'Quantity', 'Borrowed', 'Due', 'Returned', 'Status', 'Fine'], 'data' => $data];
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
        if ($this->hasTable('borrowing_transactions')) {
            $statement = $this->pdo->query(
                "SELECT bt.id, bt.transaction_code, bt.borrow_date, bt.due_date, bt.status, bt.user_id,
                        u.firstname, u.lastname, u.barcode AS id_barcode, GROUP_CONCAT(DISTINCT t.title) AS title,
                        COUNT(bi.id) AS book_count
                 FROM borrowing_transactions bt JOIN borrowing_items bi ON bi.transaction_id = bt.id
                 JOIN book_copies c ON c.id = bi.copy_id JOIN book_titles t ON t.id = c.title_id
                 JOIN users u ON u.id = bt.user_id
                 WHERE bt.approval_status = 'pending' AND bi.return_date IS NULL
                 GROUP BY bt.id, bt.transaction_code, bt.borrow_date, bt.due_date, bt.status, bt.user_id,
                          u.firstname, u.lastname, u.barcode, bt.requested_at
                 ORDER BY bt.requested_at ASC, bt.id ASC"
            );
            if ($statement === false) return [];
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$row) $row['borrower'] = trim($this->string($row['firstname'] ?? null) . ' ' . $this->string($row['lastname'] ?? null));
            unset($row);
            return $rows;
        }
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
    private function recentTransactions(int $limit = 8): array
    {
        $limit = max(1, min($limit, 10));
        if ($this->hasTable('borrowing_items')) {
            $statement = $this->pdo->query(
                "SELECT bt.transaction_code, bt.borrow_date, bt.due_date, bt.status,
                        u.firstname, u.lastname, t.title, COUNT(bi.id) AS quantity
                 FROM borrowing_items bi
                 JOIN borrowing_transactions bt ON bt.id = bi.transaction_id
                 JOIN book_copies c ON c.id = bi.copy_id
                 JOIN book_titles t ON t.id = c.title_id
                 JOIN users u ON u.id = bt.user_id
                 GROUP BY bt.id, bt.transaction_code, bt.borrow_date, bt.due_date, bt.status,
                          u.firstname, u.lastname, t.id, t.title
                 ORDER BY bt.id DESC LIMIT {$limit}"
            );
            if ($statement === false) return [];
            /** @var list<array<string, mixed>> $rows */
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$row) {
                $row['borrower'] = trim($this->string($row['firstname'] ?? null) . ' ' . $this->string($row['lastname'] ?? null));
            }
            unset($row);
            return $rows;
        }

        $statement = $this->pdo->query(
            "SELECT br.transaction_code, br.borrow_date, br.due_date, br.status,
                    u.firstname, u.lastname, b.title, 1 AS quantity
             FROM borrowing br JOIN users u ON u.id = br.user_id JOIN books b ON b.id = br.book_id
             ORDER BY br.id DESC LIMIT {$limit}"
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

    /** @return array{borrowing_activity: list<array{month: string, label: string, count: int}>, category_borrowing_activity: array{months: list<array{month: string, label: string}>, series: list<array{name: string, counts: list<int>}>}, loan_status: array{available: int, borrowed: int, overdue: int, pending: int}, category_breakdown: list<array{name: string, count: int}>, top_genres: list<array{name: string, count: int}>, top_borrowers: list<array{id: int, name: string, barcode: string, borrowing_count: int}>, recent_activity: list<array<string, mixed>>} */
    private function dashboardOverview(): array
    {
        $firstMonth = (new \DateTimeImmutable('first day of this month'))->modify('-11 months');
        $normalized = $this->hasTable('borrowing_items');

        /** @var array<string, array{month: string, label: string, count: int}> $months */
        $months = [];
        for ($offset = 0; $offset < 12; $offset++) {
            $month = $firstMonth->modify('+' . $offset . ' months');
            $key = $month->format('Y-m');
            $months[$key] = [
                'month' => $key,
                'label' => $month->format('M'),
                'count' => 0,
            ];
        }

        $activityStatement = $this->pdo->prepare(
            $normalized
            ? "SELECT SUBSTR(TRIM(bt.borrow_date), 1, 7) AS month, COUNT(bi.id) AS count
             FROM borrowing_items bi JOIN borrowing_transactions bt ON bt.id = bi.transaction_id
             WHERE bt.borrow_date IS NOT NULL AND bt.borrow_date >= :start_date
             GROUP BY SUBSTR(TRIM(bt.borrow_date), 1, 7)"
            : "SELECT SUBSTR(TRIM(borrow_date), 1, 7) AS month, COUNT(*) AS count
             FROM borrowing
             WHERE borrow_date IS NOT NULL AND borrow_date >= :start_date
             GROUP BY SUBSTR(TRIM(borrow_date), 1, 7)"
        );
        $activityStatement->execute(['start_date' => $firstMonth->format('Y-m-d')]);
        while (($row = $activityStatement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (!is_array($row) || !is_string($row['month'] ?? null)) {
                continue;
            }
            $monthKey = trim($row['month']);
            if (isset($months[$monthKey])) {
                $months[$monthKey]['count'] = is_numeric($row['count'] ?? null) ? (int) $row['count'] : 0;
            }
        }

        /** @var array<string, array<string, int>> $categoryCounts */
        $categoryCounts = [];
        $categoryActivityStatement = $this->pdo->prepare(
            $normalized
            ? "SELECT SUBSTR(TRIM(bt.borrow_date), 1, 7) AS month,
                    COALESCE(NULLIF(TRIM(t.category_name), ''), 'Uncategorized') AS category,
                    COUNT(bi.id) AS count
             FROM borrowing_items bi JOIN borrowing_transactions bt ON bt.id = bi.transaction_id
             JOIN book_copies c ON c.id = bi.copy_id JOIN book_titles t ON t.id = c.title_id
             WHERE bt.borrow_date IS NOT NULL AND bt.borrow_date >= :start_date
             GROUP BY SUBSTR(TRIM(bt.borrow_date), 1, 7), COALESCE(NULLIF(TRIM(t.category_name), ''), 'Uncategorized')"
            : "SELECT SUBSTR(TRIM(br.borrow_date), 1, 7) AS month,
                    COALESCE(NULLIF(TRIM(b.category_name), ''), 'Uncategorized') AS category,
                    COUNT(*) AS count
             FROM borrowing br JOIN books b ON b.id = br.book_id
             WHERE br.borrow_date IS NOT NULL AND br.borrow_date >= :start_date
             GROUP BY SUBSTR(TRIM(br.borrow_date), 1, 7), COALESCE(NULLIF(TRIM(b.category_name), ''), 'Uncategorized')"
        );
        $categoryActivityStatement->execute(['start_date' => $firstMonth->format('Y-m-d')]);
        while (($row = $categoryActivityStatement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (!is_array($row) || !is_string($row['month'] ?? null)) {
                continue;
            }
            $monthKey = trim($row['month']);
            if (!isset($months[$monthKey])) {
                continue;
            }
            $category = $this->string($row['category'] ?? null);
            $categoryCounts[$category][$monthKey] = is_numeric($row['count'] ?? null) ? (int) $row['count'] : 0;
        }

        $categorySeries = [];
        foreach ($categoryCounts as $category => $counts) {
            $categorySeries[] = [
                'name' => $category,
                'counts' => array_map(
                    static fn (array $month): int => $counts[$month['month']] ?? 0,
                    array_values($months),
                ),
            ];
        }
        usort(
            $categorySeries,
            static function (array $left, array $right): int {
                $totalComparison = array_sum($right['counts']) <=> array_sum($left['counts']);
                if ($totalComparison !== 0) {
                    return $totalComparison;
                }

                return $left['name'] <=> $right['name'];
            },
        );

        $topBorrowersStatement = $this->pdo->prepare(
            $normalized
            ? "SELECT u.id, u.barcode, u.firstname, u.lastname, COUNT(bi.id) AS borrowing_count
               FROM users u JOIN borrowing_transactions bt ON bt.user_id = u.id
               JOIN borrowing_items bi ON bi.transaction_id = bt.id
               WHERE u.role IN ('student', 'teacher')
               GROUP BY u.id, u.barcode, u.firstname, u.lastname
               ORDER BY borrowing_count DESC, u.lastname ASC, u.firstname ASC LIMIT 10"
            : 'SELECT u.id, u.barcode, u.firstname, u.lastname, COUNT(br.id) AS borrowing_count '
            . 'FROM users u JOIN borrowing br ON br.user_id = u.id '
            . "WHERE u.role IN ('student', 'teacher') "
            . 'GROUP BY u.id, u.barcode, u.firstname, u.lastname '
            . 'ORDER BY borrowing_count DESC, u.lastname ASC, u.firstname ASC LIMIT 10'
        );
        $topBorrowersStatement->execute();
        /** @var list<array<string, mixed>> $borrowerRows */
        $borrowerRows = $topBorrowersStatement->fetchAll(PDO::FETCH_ASSOC);
        $topBorrowers = [];
        foreach ($borrowerRows as $row) {
            $topBorrowers[] = [
                'id' => is_numeric($row['id'] ?? null) ? (int) $row['id'] : 0,
                'name' => trim($this->string($row['firstname'] ?? null) . ' ' . $this->string($row['lastname'] ?? null)),
                'barcode' => $this->string($row['barcode'] ?? null),
                'borrowing_count' => is_numeric($row['borrowing_count'] ?? null) ? (int) $row['borrowing_count'] : 0,
            ];
        }

        return [
            'borrowing_activity' => array_values($months),
            'category_borrowing_activity' => [
                'months' => array_map(
                    static fn (array $month): array => [
                        'month' => $month['month'],
                        'label' => $month['label'],
                    ],
                    array_values($months),
                ),
                'series' => $categorySeries,
            ],
            'loan_status' => [
                'available' => $this->count($normalized ? "SELECT COUNT(*) FROM book_copies WHERE deleted_at IS NULL AND status = 'Available'" : "SELECT COUNT(*) FROM books WHERE deleted_at IS NULL AND status = 'Available'"),
                'borrowed' => $this->count($normalized ? "SELECT COUNT(*) FROM book_copies WHERE deleted_at IS NULL AND status = 'Borrowed'" : "SELECT COUNT(*) FROM books WHERE deleted_at IS NULL AND status = 'Borrowed'"),
                'overdue' => $this->count($normalized ? "SELECT COUNT(*) FROM borrowing_items bi JOIN borrowing_transactions bt ON bt.id = bi.transaction_id WHERE bi.return_date IS NULL AND bt.status = 'Overdue'" : "SELECT COUNT(*) FROM borrowing WHERE return_date IS NULL AND status = 'Overdue'"),
                'pending' => $this->count($normalized ? "SELECT COUNT(*) FROM borrowing_items bi JOIN borrowing_transactions bt ON bt.id = bi.transaction_id WHERE bt.approval_status = 'pending' AND bi.return_date IS NULL" : "SELECT COUNT(*) FROM borrowing WHERE approval_status = 'pending' AND return_date IS NULL"),
            ],
            'category_breakdown' => $this->namedCounts(
                $normalized
                ? "SELECT COALESCE(NULLIF(TRIM(t.category_name), ''), 'Uncategorized') AS name, COUNT(c.id) AS count
                   FROM book_titles t JOIN book_copies c ON c.title_id = t.id
                   WHERE c.deleted_at IS NULL
                   GROUP BY COALESCE(NULLIF(TRIM(t.category_name), ''), 'Uncategorized')
                   ORDER BY count DESC, name ASC"
                : "SELECT COALESCE(NULLIF(TRIM(category_name), ''), 'Uncategorized') AS name, COUNT(*) AS count
                   FROM books WHERE deleted_at IS NULL
                   GROUP BY COALESCE(NULLIF(TRIM(category_name), ''), 'Uncategorized')
                   ORDER BY count DESC, name ASC"
            ),
            'top_genres' => $this->namedCounts(
                $normalized
                ? "SELECT COALESCE(NULLIF(TRIM(t.category_name), ''), 'Uncategorized') AS name, COUNT(bi.id) AS count
                   FROM borrowing_items bi JOIN borrowing_transactions bt ON bt.id = bi.transaction_id
                   JOIN book_copies c ON c.id = bi.copy_id JOIN book_titles t ON t.id = c.title_id
                   WHERE bt.borrow_date IS NOT NULL
                   GROUP BY COALESCE(NULLIF(TRIM(t.category_name), ''), 'Uncategorized')
                   ORDER BY count DESC, name ASC LIMIT 5"
                : "SELECT COALESCE(NULLIF(TRIM(b.category_name), ''), 'Uncategorized') AS name, COUNT(br.id) AS count
                   FROM borrowing br JOIN books b ON b.id = br.book_id
                   WHERE br.borrow_date IS NOT NULL
                   GROUP BY COALESCE(NULLIF(TRIM(b.category_name), ''), 'Uncategorized')
                   ORDER BY count DESC, name ASC LIMIT 5"
            ),
            'top_borrowers' => $topBorrowers,
            'recent_activity' => $this->recentTransactions(10),
        ];
    }

    /** @return list<array{name: string, count: int}> */
    private function namedCounts(string $sql): array
    {
        $statement = $this->pdo->query($sql);
        if ($statement === false) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map(
            fn (array $row): array => [
                'name' => $this->string($row['name'] ?? null),
                'count' => is_numeric($row['count'] ?? null) ? (int) $row['count'] : 0,
            ],
            $rows,
        );
    }

    private function changeBorrowing(int $borrowingId, int $staffId, bool $approve): void
    {
        $this->pdo->beginTransaction();
        try {
            $status = $approve ? 'approved' : 'rejected';
            if ($this->hasTable('borrowing_transactions')) {
                $copyStatement = $this->pdo->prepare(
                    'SELECT c.id, c.barcode, t.title, bi.id AS item_id, c.status FROM borrowing_items bi '
                    . 'JOIN book_copies c ON c.id = bi.copy_id JOIN book_titles t ON t.id = c.title_id '
                    . 'WHERE bi.transaction_id = :transaction_id'
                );
                $copyStatement->execute(['transaction_id' => $borrowingId]);
                /** @var list<array<string, mixed>> $copies */
                $copies = $copyStatement->fetchAll(PDO::FETCH_ASSOC);
                $transactionStatus = $approve ? 'Borrowed' : 'Returned';
                $statement = $this->pdo->prepare(
                    "UPDATE borrowing_transactions SET approval_status = :approval_status,
                     status = :transaction_status,
                     approved_at = CURRENT_TIMESTAMP, approved_by = :staff_id WHERE id = :id AND approval_status = 'pending'"
                );
                $statement->execute([
                    'approval_status' => $status,
                    'transaction_status' => $transactionStatus,
                    'staff_id' => $staffId,
                    'id' => $borrowingId,
                ]);
                if ($statement->rowCount() < 1) {
                    $this->pdo->commit();
                    return;
                }
                $itemStatement = $this->pdo->prepare($approve
                    ? "UPDATE borrowing_items SET status = 'Borrowed' WHERE transaction_id = :transaction_id AND return_date IS NULL"
                    : "UPDATE borrowing_items SET status = 'Returned', return_date = CURRENT_TIMESTAMP WHERE transaction_id = :transaction_id AND return_date IS NULL"
                );
                $itemStatement->execute(['transaction_id' => $borrowingId]);
                $copyStatus = $approve ? 'Borrowed' : 'Available';
                $this->pdo->prepare("UPDATE book_copies SET status = :status, due_date = CASE WHEN :approve = 1 THEN due_date ELSE NULL END WHERE id IN (SELECT copy_id FROM borrowing_items WHERE transaction_id = :transaction_id)")
                    ->execute(['status' => $copyStatus, 'approve' => $approve ? 1 : 0, 'transaction_id' => $borrowingId]);
                $this->pdo->prepare('UPDATE notifications SET is_read = 1 WHERE related_id = :id AND user_id = :staff_id')->execute(['id' => $borrowingId, 'staff_id' => $staffId]);
                foreach ($copies as $copy) {
                    $copyId = (int) ($copy['id'] ?? 0);
                    if ($copyId < 1 || $this->audit === null) {
                        continue;
                    }
                    $fromStatus = $this->string($copy['status'] ?? null) ?: 'Reserved';
                    $this->audit->record(new AuditEvent(
                        $copyId,
                        $staffId,
                        $approve ? AuditEventType::LOANED : AuditEventType::STATUS_CHANGED,
                        $fromStatus,
                        $approve ? 'Borrowed' : 'Available',
                        $approve ? null : 'Borrow request rejected by staff.',
                        $borrowingId,
                        (int) ($copy['item_id'] ?? 0),
                        null,
                        ['barcode' => $this->string($copy['barcode'] ?? null), 'title' => $this->string($copy['title'] ?? null)],
                        new DateTimeImmutable(),
                    ));
                }
                $this->pdo->commit();
                return;
            }
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

    /** @return array{total_books: int, available_books: int, borrowed_books: int, borrowers: int, active_loans: int, overdue_loans: int, pending_approvals: int} */
    private function dashboardStats(): array
    {
        if ($this->hasTable('book_copies')) {
            return [
                'total_books' => $this->count("SELECT COUNT(*) FROM book_copies WHERE deleted_at IS NULL"),
                'available_books' => $this->count("SELECT COUNT(*) FROM book_copies WHERE deleted_at IS NULL AND status = 'Available'"),
                'borrowed_books' => $this->count("SELECT COUNT(*) FROM book_copies WHERE deleted_at IS NULL AND status = 'Borrowed'"),
                'borrowers' => $this->count("SELECT COUNT(*) FROM users WHERE role IN ('student', 'teacher')"),
                'active_loans' => $this->count("SELECT COUNT(*) FROM borrowing_items WHERE return_date IS NULL"),
                'overdue_loans' => $this->count("SELECT COUNT(*) FROM borrowing_items bi JOIN borrowing_transactions bt ON bt.id = bi.transaction_id WHERE bi.return_date IS NULL AND bt.status = 'Overdue'"),
                'pending_approvals' => $this->count("SELECT COUNT(*) FROM borrowing_items bi JOIN borrowing_transactions bt ON bt.id = bi.transaction_id WHERE bt.approval_status = 'pending' AND bi.return_date IS NULL"),
            ];
        }

        $statement = $this->pdo->query(
            "SELECT 'total_books' AS metric, COUNT(*) AS metric_count
             FROM books WHERE deleted_at IS NULL
             UNION ALL
             SELECT 'available_books', COUNT(*)
             FROM books WHERE deleted_at IS NULL AND status = 'Available'
             UNION ALL
             SELECT 'borrowed_books', COUNT(*)
             FROM books WHERE deleted_at IS NULL AND status = 'Borrowed'
             UNION ALL
             SELECT 'borrowers', COUNT(*)
             FROM users WHERE role IN ('student', 'teacher')
             UNION ALL
             SELECT 'active_loans', COUNT(*)
             FROM borrowing WHERE return_date IS NULL
             UNION ALL
             SELECT 'overdue_loans', COUNT(*)
             FROM borrowing WHERE return_date IS NULL AND status = 'Overdue'
             UNION ALL
             SELECT 'pending_approvals', COUNT(*)
             FROM borrowing WHERE approval_status = 'pending' AND return_date IS NULL"
        );
        $stats = [
            'total_books' => 0,
            'available_books' => 0,
            'borrowed_books' => 0,
            'borrowers' => 0,
            'active_loans' => 0,
            'overdue_loans' => 0,
            'pending_approvals' => 0,
        ];
        if ($statement === false) {
            return $stats;
        }

        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (!is_array($row) || !is_string($row['metric'] ?? null) || !array_key_exists($row['metric'], $stats)) {
                continue;
            }
            $stats[$row['metric']] = is_numeric($row['metric_count'] ?? null) ? (int) $row['metric_count'] : 0;
        }

        return $stats;
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
