<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;

final class PdoGuestPortalRepository implements GuestPortalRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function dashboardSummary(int $visitorId): array
    {
        $visitorStatement = $this->pdo->prepare(
            'SELECT visitor_number, firstname, middlename, lastname, account_status, registration_expires_at, id_barcode '
            . 'FROM visitors WHERE id = :id LIMIT 1'
        );
        $visitorStatement->execute(['id' => $visitorId]);
        /** @var array<string, mixed>|false $visitor */
        $visitor = $visitorStatement->fetch(PDO::FETCH_ASSOC);

        $statement = $this->pdo->prepare(
            'SELECT request_status, COUNT(*) AS count FROM visitor_borrowing WHERE visitor_id = :visitor_id GROUP BY request_status'
        );
        $statement->execute(['visitor_id' => $visitorId]);
        $counts = ['active' => 0, 'returned' => 0, 'overdue' => 0, 'total' => 0];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (!is_array($row)) {
                continue;
            }
            $status = is_string($row['request_status'] ?? null) ? $row['request_status'] : '';
            $count = is_numeric($row['count'] ?? null) ? (int) $row['count'] : 0;
            $counts['total'] += $count;
            if ($status === 'Returned') {
                $counts['returned'] += $count;
            } elseif ($status === 'Released') {
                $counts['active'] += $count;
            }
        }

        $recent = $this->pdo->prepare(
            'SELECT b.title FROM visitor_borrowing vb JOIN books b ON b.id = vb.book_id WHERE vb.visitor_id = :visitor_id ORDER BY vb.id DESC LIMIT 1'
        );
        $recent->execute(['visitor_id' => $visitorId]);
        $recentBook = $recent->fetchColumn();
        $visitorData = $visitor === false ? [] : $visitor;
        $name = trim($this->string($visitorData['firstname'] ?? null) . ' ' . $this->string($visitorData['lastname'] ?? null));

        return $counts + [
            'visitor' => [
                'name' => $name,
                'visitor_number' => $this->string($visitorData['visitor_number'] ?? null),
                'account_status' => $this->string($visitorData['account_status'] ?? 'Active'),
                'registration_expires_at' => $this->string($visitorData['registration_expires_at'] ?? null),
            ],
            'days_remaining' => 0,
            'favorite_category' => 'No activity yet',
            'recent_book' => is_string($recentBook) ? $recentBook : 'No activity yet',
        ];
    }

    public function notifications(int $visitorId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, title, message, is_read, created_at FROM visitor_notifications WHERE visitor_id = :visitor_id ORDER BY id DESC LIMIT 20'
        );
        $statement->execute(['visitor_id' => $visitorId]);
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    public function browse(array $filters): array
    {
        $where = ["b.deleted_at IS NULL", "b.status = 'Available'"];
        $parameters = [];
        $search = $this->filterString($filters, 'q');
        if ($search === '') {
            $search = $this->filterString($filters, 'search');
        }
        if ($search !== '') {
            $where[] = '(b.title LIKE :search OR b.author LIKE :search OR b.category_name LIKE :search OR b.isbn LIKE :search OR b.barcode LIKE :search)';
            $parameters['search'] = '%' . $search . '%';
        }
        $category = $this->filterString($filters, 'category');
        if ($category === '') {
            $category = $this->filterString($filters, 'category_name');
        }
        if ($category !== '') {
            $where[] = 'b.category_name = :category';
            $parameters['category'] = $category;
        }
        $floor = $this->filterString($filters, 'floor');
        if ($floor !== '') {
            $where[] = 'b.floor_no = :floor';
            $parameters['floor'] = $floor;
        }
        $whereClause = implode(' AND ', $where);
        $statement = $this->pdo->prepare(
            'SELECT b.id, b.barcode, b.accession_no, b.isbn, b.title, b.author, b.publisher, b.category_name, b.cover_file, b.floor_no, b.section_name, b.shelf_no, b.row_no '
            . 'FROM books b WHERE ' . $whereClause . ' ORDER BY b.title LIMIT 100'
        );
        $statement->execute($parameters);
        /** @var list<array<string, mixed>> $books */
        $books = $statement->fetchAll(PDO::FETCH_ASSOC);

        return ['books' => $books, 'total' => count($books)];
    }

    public function history(int $visitorId, string $status, string $from, string $to): array
    {
        $where = ['vb.visitor_id = :visitor_id'];
        $parameters = ['visitor_id' => $visitorId];
        if ($status !== '' && $status !== 'all') {
            $where[] = 'vb.request_status = :status';
            $parameters['status'] = $status;
        }
        if ($from !== '') {
            $where[] = 'vb.borrow_date >= :from_date';
            $parameters['from_date'] = $from;
        }
        if ($to !== '') {
            $where[] = 'vb.borrow_date <= :to_date';
            $parameters['to_date'] = $to;
        }
        $statement = $this->pdo->prepare(
            'SELECT vb.id, vb.borrow_date, vb.due_date, vb.return_date, vb.request_status, vb.review_notes, b.title, b.author '
            . 'FROM visitor_borrowing vb JOIN books b ON b.id = vb.book_id WHERE ' . implode(' AND ', $where) . ' ORDER BY vb.id DESC'
        );
        $statement->execute($parameters);
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['status_label'] = $row['request_status'] ?? 'Pending';
        }
        unset($row);

        return $rows;
    }

    public function receipt(int $visitorId, int $borrowingId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT vb.id, vb.borrow_date, vb.due_date, vb.return_date, vb.request_status, '
            . 'v.visitor_number, CONCAT(v.firstname, \' \', v.lastname) AS full_name, '
            . 'b.title, b.author, b.barcode, b.accession_no, b.isbn, b.floor_no, b.section_name, b.shelf_no, b.row_no '
            . 'FROM visitor_borrowing vb JOIN visitors v ON v.id = vb.visitor_id JOIN books b ON b.id = vb.book_id '
            . 'WHERE vb.id = :id AND vb.visitor_id = :visitor_id LIMIT 1'
        );
        $statement->execute(['id' => $borrowingId, 'visitor_id' => $visitorId]);
        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return $row + [
            'borrowed_display' => $this->string($row['borrow_date'] ?? null),
            'due_display' => $this->string($row['due_date'] ?? null),
            'validity_display' => $this->string($row['request_status'] ?? null),
        ];
    }

    /** @param array<string, mixed> $filters */
    private function filterString(array $filters, string $key): string
    {
        $value = $filters[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
