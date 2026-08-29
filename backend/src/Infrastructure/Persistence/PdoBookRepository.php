<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\DTO\BookMutationRequest;
use App\Domain\Book\BookSearchCriteria;
use App\Domain\Book\BookSearchResult;
use PDO;

final class PdoBookRepository implements BookRepositoryInterface, BookAdministrationRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function search(BookSearchCriteria $criteria): BookSearchResult
    {
        if ($this->hasTable('book_titles')) {
            return $this->searchTitles($criteria);
        }

        $where = [$criteria->archived() ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL'];
        $parameters = [];

        if ($criteria->search() !== '') {
            $where[] = '(title LIKE :search_title OR author LIKE :search_author OR publisher LIKE :search_publisher OR ' .
                'category_name LIKE :search_category OR barcode LIKE :search_barcode OR accession_no LIKE :search_accession OR isbn LIKE :search_isbn)';
            $search = '%' . $criteria->search() . '%';
            $parameters = [
                'search_title' => $search,
                'search_author' => $search,
                'search_publisher' => $search,
                'search_category' => $search,
                'search_barcode' => $search,
                'search_accession' => $search,
                'search_isbn' => $search,
            ];
        }

        if ($criteria->status() !== null) {
            $where[] = 'status = :status';
            $parameters['status'] = $criteria->status()->value;
        }

        $condition = implode(' AND ', $where);
        $countStatement = $this->pdo->prepare('SELECT COUNT(*) FROM books WHERE ' . $condition);
        $countStatement->execute($parameters);
        $total = (int) $countStatement->fetchColumn();

        $offset = ($criteria->page() - 1) * $criteria->perPage();
        $statement = $this->pdo->prepare(
            'SELECT id, barcode, accession_no, isbn, title, author, publisher, category_name, cover_file, ' .
            'floor_no, section_name, shelf_no, row_no, due_date, return_date, status, deleted_at, created_at, description ' .
            'FROM books WHERE ' . $condition . ' ORDER BY ' . $criteria->sort() . ' ' . $criteria->direction() .
            ' LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->bindValue('limit', $criteria->perPage(), PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array<string, mixed>> $books */
        $books = $statement->fetchAll(PDO::FETCH_ASSOC);

        return new BookSearchResult($books, $total);
    }

    public function lookupCopyByBarcode(string $barcode): ?array
    {
        if (!$this->hasTable('book_copies')) {
            return null;
        }
        $statement = $this->pdo->prepare(
            "SELECT c.id AS copy_id, c.title_id, c.barcode, c.status, t.title, t.author, t.quantity,
                    (SELECT COUNT(*) FROM book_copies available_copy WHERE available_copy.title_id = c.title_id
                        AND available_copy.status = 'Available' AND available_copy.deleted_at IS NULL) AS available_quantity
             FROM book_copies c JOIN book_titles t ON t.id = c.title_id
             WHERE c.barcode = :barcode AND c.deleted_at IS NULL LIMIT 1"
        );
        $statement->execute(['barcode' => trim($barcode)]);
        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private function searchTitles(BookSearchCriteria $criteria): BookSearchResult
    {
        $where = [$criteria->archived()
            ? 'NOT EXISTS (SELECT 1 FROM book_copies archived_copy WHERE archived_copy.title_id = t.id AND archived_copy.deleted_at IS NULL)'
            : 'EXISTS (SELECT 1 FROM book_copies live_copy WHERE live_copy.title_id = t.id AND live_copy.deleted_at IS NULL)'];
        $parameters = [];

        if ($criteria->search() !== '') {
            $search = '%' . $criteria->search() . '%';
            $where[] = '(t.title LIKE :title_search OR t.author LIKE :author_search OR t.publisher LIKE :publisher_search OR t.category_name LIKE :category_search OR t.isbn LIKE :isbn_search)';
            $parameters = [
                'title_search' => $search,
                'author_search' => $search,
                'publisher_search' => $search,
                'category_search' => $search,
                'isbn_search' => $search,
            ];
        }

        if ($criteria->status() !== null) {
            $status = $criteria->status()->value;
            $where[] = match ($status) {
                'Available' => "EXISTS (SELECT 1 FROM book_copies status_copy WHERE status_copy.title_id = t.id AND status_copy.status = 'Available' AND status_copy.deleted_at IS NULL)",
                'Borrowed' => "EXISTS (SELECT 1 FROM book_copies status_copy WHERE status_copy.title_id = t.id AND status_copy.status = 'Borrowed' AND status_copy.deleted_at IS NULL)",
                'Reserved' => "EXISTS (SELECT 1 FROM book_copies status_copy WHERE status_copy.title_id = t.id AND status_copy.status = 'Reserved' AND status_copy.deleted_at IS NULL)",
            };
        }

        $condition = implode(' AND ', $where);
        $countStatement = $this->pdo->prepare('SELECT COUNT(*) FROM book_titles t WHERE ' . $condition);
        $countStatement->execute($parameters);
        $total = (int) $countStatement->fetchColumn();
        $offset = ($criteria->page() - 1) * $criteria->perPage();
        $sort = in_array($criteria->sort(), ['title', 'author', 'publisher', 'category_name', 'created_at'], true)
            ? 't.' . $criteria->sort()
            : 't.created_at';
        $statement = $this->pdo->prepare(
            'SELECT t.id, t.id AS title_id, NULL AS barcode, t.isbn, t.title, t.author, t.publisher, t.category_name, '
            . 't.cover_file, t.description, t.quantity, '
            . "SUM(CASE WHEN c.status = 'Available' AND c.deleted_at IS NULL THEN 1 ELSE 0 END) AS available_quantity, "
            . "SUM(CASE WHEN c.status = 'Reserved' AND c.deleted_at IS NULL THEN 1 ELSE 0 END) AS reserved_quantity, "
            . "SUM(CASE WHEN c.status = 'Borrowed' AND c.deleted_at IS NULL THEN 1 ELSE 0 END) AS borrowed_quantity, "
            . 'MIN(c.floor_no) AS floor_no, MIN(c.section_name) AS section_name, MIN(c.shelf_no) AS shelf_no, MIN(c.row_no) AS row_no '
            . 'FROM book_titles t LEFT JOIN book_copies c ON c.title_id = t.id WHERE ' . $condition
            . ' GROUP BY t.id ORDER BY ' . $sort . ' ' . $criteria->direction() . ' LIMIT :limit OFFSET :offset'
        );
        foreach ($parameters as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->bindValue('limit', $criteria->perPage(), PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        /** @var list<array<string, mixed>> $books */
        $books = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($books as &$book) {
            $available = (int) ($book['available_quantity'] ?? 0);
            $book['status'] = $available > 0 ? 'Available' : ((int) ($book['borrowed_quantity'] ?? 0) > 0 ? 'Borrowed' : 'Reserved');
        }
        unset($book);

        return new BookSearchResult($books, $total);
    }

    private function hasTable(string $table): bool
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $statement = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :table LIMIT 1");
            $statement->execute(['table' => $table]);
        } else {
            $statement = $this->pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1');
            $statement->execute(['table' => $table]);
        }

        return $statement->fetchColumn() !== false;
    }

    public function barcodeExists(string $barcode, ?int $exceptId = null): bool
    {
        if ($this->hasTable('book_copies')) {
            $sql = 'SELECT 1 FROM book_copies WHERE barcode = :value AND deleted_at IS NULL';
            $parameters = ['value' => $barcode];
            if ($exceptId !== null) { $sql .= ' AND title_id <> :except_id'; $parameters['except_id'] = $exceptId; }
            $statement = $this->pdo->prepare($sql . ' LIMIT 1');
            $statement->execute($parameters);
            return $statement->fetchColumn() !== false;
        }
        return $this->exists('barcode', $barcode, $exceptId);
    }

    public function accessionExists(string $accessionNo, ?int $exceptId = null): bool
    {
        if ($this->hasTable('book_copies')) {
            $sql = 'SELECT 1 FROM book_copies WHERE accession_no = :value AND deleted_at IS NULL';
            $parameters = ['value' => $accessionNo];
            if ($exceptId !== null) { $sql .= ' AND title_id <> :except_id'; $parameters['except_id'] = $exceptId; }
            $statement = $this->pdo->prepare($sql . ' LIMIT 1');
            $statement->execute($parameters);
            return $statement->fetchColumn() !== false;
        }
        return $accessionNo !== '' && $this->exists('accession_no', $accessionNo, $exceptId);
    }

    public function create(BookMutationRequest $request): int
    {
        if ($this->hasTable('book_titles')) {
            $this->pdo->beginTransaction();
            try {
                $titleStatement = $this->pdo->prepare(
                    'INSERT INTO book_titles (isbn, title, author, publisher, description, cover_file, category_name, quantity) VALUES (:isbn, :title, :author, :publisher, :description, :cover_file, :category_name, :quantity)'
                );
                $titleStatement->execute([
                    'isbn' => $request->isbn, 'title' => $request->title, 'author' => $request->author,
                    'publisher' => $request->publisher, 'description' => $request->description,
                    'cover_file' => $request->coverFile, 'category_name' => $request->categoryName, 'quantity' => $request->quantity,
                ]);
                $titleId = (int) $this->pdo->lastInsertId();
                $copyStatement = $this->pdo->prepare(
                    'INSERT INTO book_copies (title_id, barcode, accession_no, floor_no, section_name, shelf_no, row_no, due_date, return_date, status) VALUES (:title_id, :barcode, :accession_no, :floor_no, :section_name, :shelf_no, :row_no, :due_date, :return_date, :status)'
                );
                for ($index = 0; $index < $request->quantity; $index++) {
                    $barcode = $index === 0 && $request->barcode !== '' ? $request->barcode : 'PENDING-' . $titleId . '-' . ($index + 1) . '-' . bin2hex(random_bytes(3));
                    $accession = $index === 0 && $request->accessionNo !== '' ? $request->accessionNo : 'ACC-' . $titleId . '-' . ($index + 1);
                    $copyStatement->execute([
                        'title_id' => $titleId, 'barcode' => $barcode, 'accession_no' => $accession,
                        'floor_no' => $request->floorNo, 'section_name' => $request->sectionName,
                        'shelf_no' => $request->shelfNo, 'row_no' => $request->rowNo, 'due_date' => $request->dueDate ?: null,
                        'return_date' => $request->returnDate ?: null, 'status' => $request->status,
                    ]);
                }
                $this->pdo->commit();
                return $titleId;
            } catch (\Throwable $exception) {
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                throw $exception;
            }
        }
        $statement = $this->pdo->prepare(
            'INSERT INTO books (barcode, accession_no, isbn, title, author, publisher, category_name, cover_file, ' .
            'floor_no, section_name, shelf_no, row_no, due_date, return_date, status, description) VALUES ' .
            '(:barcode, :accession_no, :isbn, :title, :author, :publisher, :category_name, :cover_file, ' .
            ':floor_no, :section_name, :shelf_no, :row_no, :due_date, :return_date, :status, :description)'
        );
        $statement->execute($this->parameters($request));

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, BookMutationRequest $request): void
    {
        if ($this->hasTable('book_titles')) {
            $this->updateTitle($id, $request);
            return;
        }

        $parameters = $this->parameters($request);
        $parameters['id'] = $id;
        $statement = $this->pdo->prepare(
            'UPDATE books SET barcode = :barcode, accession_no = :accession_no, isbn = :isbn, title = :title, ' .
            'author = :author, publisher = :publisher, category_name = :category_name, cover_file = :cover_file, ' .
            'floor_no = :floor_no, section_name = :section_name, shelf_no = :shelf_no, row_no = :row_no, ' .
            'due_date = :due_date, return_date = :return_date, status = :status, description = :description WHERE id = :id'
        );
        $statement->execute($parameters);
    }

    private function updateTitle(int $id, BookMutationRequest $request): void
    {
        $this->pdo->beginTransaction();
        try {
            $copyStatement = $this->pdo->prepare(
                'SELECT id, status FROM book_copies WHERE title_id = :title_id AND deleted_at IS NULL ORDER BY id'
            );
            $copyStatement->execute(['title_id' => $id]);
            /** @var list<array{id: int|string, status: string}> $copies */
            $copies = $copyStatement->fetchAll(PDO::FETCH_ASSOC);
            $currentQuantity = count($copies);

            if ($request->quantity < $currentQuantity) {
                $removableIds = [];
                $activeCopyIds = [];
                if ($this->hasTable('borrowing_items')) {
                    $activeStatement = $this->pdo->prepare('SELECT copy_id FROM borrowing_items WHERE return_date IS NULL');
                    $activeStatement->execute();
                    $activeCopyIds = array_map('intval', $activeStatement->fetchAll(PDO::FETCH_COLUMN));
                }
                foreach (array_reverse($copies) as $copy) {
                    $copyId = (int) $copy['id'];
                    if ($copy['status'] === 'Available' && !in_array($copyId, $activeCopyIds, true)) {
                        $removableIds[] = $copyId;
                    }
                    if (count($removableIds) === $currentQuantity - $request->quantity) break;
                }
                if (count($removableIds) < $currentQuantity - $request->quantity) {
                    throw new \InvalidArgumentException('Cannot reduce quantity below the number of active copies.');
                }
                $placeholders = implode(',', array_fill(0, count($removableIds), '?'));
                $deleteStatement = $this->pdo->prepare('UPDATE book_copies SET deleted_at = CURRENT_TIMESTAMP WHERE id IN (' . $placeholders . ')');
                $deleteStatement->execute($removableIds);
            }

            $titleStatement = $this->pdo->prepare(
                "UPDATE book_titles SET isbn = :isbn, title = :title, author = :author, publisher = :publisher,
                 description = :description, cover_file = COALESCE(NULLIF(:cover_file, ''), cover_file),
                 category_name = :category_name, quantity = :quantity WHERE id = :id"
            );
            $titleStatement->execute([
                'isbn' => $this->nullable($request->isbn), 'title' => $request->title,
                'author' => $this->nullable($request->author), 'publisher' => $this->nullable($request->publisher),
                'description' => $this->nullable($request->description), 'cover_file' => $this->nullable($request->coverFile),
                'category_name' => $request->categoryName, 'quantity' => $request->quantity, 'id' => $id,
            ]);

            $quantityAfterRemoval = min($request->quantity, $currentQuantity);
            if ($request->quantity > $quantityAfterRemoval) {
                $copyInsert = $this->pdo->prepare(
                    'INSERT INTO book_copies (title_id, barcode, accession_no, floor_no, section_name, shelf_no, row_no, due_date, return_date, status) VALUES (:title_id, :barcode, :accession_no, :floor_no, :section_name, :shelf_no, :row_no, :due_date, :return_date, :status)'
                );
                for ($index = $quantityAfterRemoval; $index < $request->quantity; $index++) {
                    $copyInsert->execute([
                        'title_id' => $id,
                        'barcode' => $index === 0 && $request->barcode !== '' ? $request->barcode : 'PENDING-' . $id . '-' . ($index + 1) . '-' . bin2hex(random_bytes(3)),
                        'accession_no' => $index === 0 && $request->accessionNo !== '' ? $request->accessionNo : 'ACC-' . $id . '-' . ($index + 1),
                        'floor_no' => $request->floorNo !== '' ? $request->floorNo : null,
                        'section_name' => $request->sectionName !== '' ? $request->sectionName : null,
                        'shelf_no' => $request->shelfNo !== '' ? $request->shelfNo : null,
                        'row_no' => $request->rowNo !== '' ? $request->rowNo : null,
                        'due_date' => $request->dueDate !== '' ? $request->dueDate : null,
                        'return_date' => $request->returnDate !== '' ? $request->returnDate : null,
                        'status' => $request->status,
                    ]);
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    /** @param list<int> $ids */
    public function archive(array $ids): int
    {
        $this->assertNoActiveLoans($ids, 'archive');

        return $this->setArchived($ids, true);
    }

    /** @param list<int> $ids */
    public function restore(array $ids): int
    {
        return $this->setArchived($ids, false);
    }

    /** @param list<int> $ids */
    public function delete(array $ids): int
    {
        $this->assertIds($ids);
        $this->assertNoActiveLoans($ids, 'delete');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->pdo->prepare('DELETE FROM books WHERE id IN (' . $placeholders . ')');
        $statement->execute($ids);

        return $statement->rowCount();
    }

    private function exists(string $column, string $value, ?int $exceptId): bool
    {
        $sql = 'SELECT 1 FROM books WHERE ' . $column . ' = :value';
        $parameters = ['value' => $value];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :except_id';
            $parameters['except_id'] = $exceptId;
        }
        $sql .= ' LIMIT 1';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchColumn() !== false;
    }

    /** @return array<string, mixed> */
    private function parameters(BookMutationRequest $request): array
    {
        return [
            'barcode' => $request->barcode,
            'accession_no' => $this->nullable($request->accessionNo),
            'isbn' => $this->nullable($request->isbn),
            'title' => $request->title,
            'author' => $this->nullable($request->author),
            'publisher' => $this->nullable($request->publisher),
            'category_name' => $request->categoryName,
            'cover_file' => $this->nullable($request->coverFile),
            'floor_no' => $this->nullable($request->floorNo),
            'section_name' => $this->nullable($request->sectionName),
            'shelf_no' => $this->nullable($request->shelfNo),
            'row_no' => $this->nullable($request->rowNo),
            'due_date' => $this->nullable($request->dueDate),
            'return_date' => $this->nullable($request->returnDate),
            'status' => $request->status,
            'description' => $this->nullable($request->description),
        ];
    }

    private function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    /** @param list<int> $ids */
    private function setArchived(array $ids, bool $archived): int
    {
        $this->assertIds($ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $value = $archived ? 'CURRENT_TIMESTAMP' : 'NULL';
        $statement = $this->pdo->prepare('UPDATE books SET deleted_at = ' . $value . ' WHERE id IN (' . $placeholders . ')');
        $statement->execute($ids);

        return $statement->rowCount();
    }

    /** @param list<int> $ids */
    private function assertIds(array $ids): void
    {
        if ($ids === []) {
            throw new \InvalidArgumentException('No books selected.');
        }
    }

    /** @param list<int> $ids */
    private function assertNoActiveLoans(array $ids, string $operation): void
    {
        $this->assertIds($ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM borrowing WHERE return_date IS NULL AND book_id IN (' . $placeholders . ')'
        );
        $statement->execute($ids);

        if ((int) $statement->fetchColumn() > 0) {
            throw new \InvalidArgumentException('Cannot ' . $operation . ' books with active loans.');
        }
    }
}
