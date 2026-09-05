<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\DTO\BookMutationRequest;
use App\Application\DTO\BookCopyMutationRequest;
use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Domain\Book\BookSearchCriteria;
use App\Domain\Book\BookSearchResult;
use PDO;

final class PdoBookRepository implements BookRepositoryInterface, BookAdministrationRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?AuditEventRepositoryInterface $audit = null,
    )
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
                'Lost' => "EXISTS (SELECT 1 FROM book_copies status_copy WHERE status_copy.title_id = t.id AND status_copy.status = 'Lost' AND status_copy.deleted_at IS NULL)",
                'Damaged' => "EXISTS (SELECT 1 FROM book_copies status_copy WHERE status_copy.title_id = t.id AND status_copy.status = 'Damaged' AND status_copy.deleted_at IS NULL)",
            };
        }

        $condition = implode(' AND ', $where);
        $countStatement = $this->pdo->prepare('SELECT COUNT(*) FROM book_titles t WHERE ' . $condition);
        $countStatement->execute($parameters);
        $total = (int) $countStatement->fetchColumn();
        $offset = ($criteria->page() - 1) * $criteria->perPage();
        $keywordSelect = '';
        if ($this->hasTable('keywords') && $this->hasTable('book_title_keywords')) {
            $keywordAggregate = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
                ? "GROUP_CONCAT(keyword.name, ', ')"
                : "GROUP_CONCAT(DISTINCT keyword.name ORDER BY keyword.name SEPARATOR ', ')";
            $keywordSelect = ', (SELECT ' . $keywordAggregate . ' FROM book_title_keywords title_keyword '
                . 'JOIN keywords keyword ON keyword.id = title_keyword.keyword_id '
                . 'WHERE title_keyword.title_id = t.id) AS keywords';
        }
        $sort = in_array($criteria->sort(), ['title', 'author', 'publisher', 'category_name', 'created_at'], true)
            ? 't.' . $criteria->sort()
            : 't.created_at';
        $statement = $this->pdo->prepare(
            'SELECT t.id, t.id AS title_id, NULL AS barcode, t.isbn, t.title, t.author, t.publisher, t.category_name, '
            . 't.cover_file, t.description' . $keywordSelect . ', t.quantity, '
            . "SUM(CASE WHEN c.status = 'Available' AND c.deleted_at IS NULL THEN 1 ELSE 0 END) AS available_quantity, "
            . "SUM(CASE WHEN c.status = 'Reserved' AND c.deleted_at IS NULL THEN 1 ELSE 0 END) AS reserved_quantity, "
            . "SUM(CASE WHEN c.status = 'Borrowed' AND c.deleted_at IS NULL THEN 1 ELSE 0 END) AS borrowed_quantity, "
            . "SUM(CASE WHEN c.status = 'Lost' AND c.deleted_at IS NULL THEN 1 ELSE 0 END) AS lost_quantity, "
            . "SUM(CASE WHEN c.status = 'Damaged' AND c.deleted_at IS NULL THEN 1 ELSE 0 END) AS damaged_quantity, "
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
            $availableValue = $book['available_quantity'] ?? 0;
            $borrowedValue = $book['borrowed_quantity'] ?? 0;
            $lostValue = $book['lost_quantity'] ?? 0;
            $damagedValue = $book['damaged_quantity'] ?? 0;
            $available = is_numeric($availableValue) ? (int) $availableValue : 0;
            $borrowed = is_numeric($borrowedValue) ? (int) $borrowedValue : 0;
            $lost = is_numeric($lostValue) ? (int) $lostValue : 0;
            $damaged = is_numeric($damagedValue) ? (int) $damagedValue : 0;
            $book['status'] = $available > 0 ? 'Available' : ($borrowed > 0 ? 'Borrowed' : ($lost > 0 ? 'Lost' : ($damaged > 0 ? 'Damaged' : 'Reserved')));
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

    /** @param list<string> $keywords */
    private function syncTitleKeywords(int $titleId, array $keywords): void
    {
        if (!$this->hasTable('keywords') || !$this->hasTable('book_title_keywords')) {
            return;
        }

        $normalized = [];
        foreach ($keywords as $keyword) {
            $value = trim($keyword);
            $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
            $value = function_exists('mb_substr') ? mb_substr($value, 0, 100, 'UTF-8') : substr($value, 0, 100);
            if ($value !== '') {
                $normalized[$value] = true;
            }
        }

        $delete = $this->pdo->prepare('DELETE FROM book_title_keywords WHERE title_id = :title_id');
        $delete->execute(['title_id' => $titleId]);
        if ($normalized === []) {
            return;
        }

        $insertSql = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'INSERT OR IGNORE INTO keywords (name) VALUES (:name)'
            : 'INSERT IGNORE INTO keywords (name) VALUES (:name)';
        $insertKeyword = $this->pdo->prepare($insertSql);
        $findKeyword = $this->pdo->prepare('SELECT id FROM keywords WHERE name = :name LIMIT 1');
        $mappingSql = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'INSERT OR IGNORE INTO book_title_keywords (title_id, keyword_id) VALUES (:title_id, :keyword_id)'
            : 'INSERT IGNORE INTO book_title_keywords (title_id, keyword_id) VALUES (:title_id, :keyword_id)';
        $insertMapping = $this->pdo->prepare($mappingSql);
        foreach (array_keys($normalized) as $name) {
            $insertKeyword->execute(['name' => $name]);
            $findKeyword->execute(['name' => $name]);
            $keywordId = $findKeyword->fetchColumn();
            if ($keywordId === false) {
                continue;
            }
            $insertMapping->execute(['title_id' => $titleId, 'keyword_id' => (int) $keywordId]);
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $statement = $this->pdo->query('PRAGMA table_info(' . $table . ')');
            if ($statement === false) {
                return false;
            }
            while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                if (!is_array($row)) {
                    continue;
                }
                $name = $row['name'] ?? null;
                if (is_string($name) && $name === $column) {
                    return true;
                }
            }

            return false;
        }

        $statement = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() '
            . 'AND table_name = :table AND column_name = :column LIMIT 1'
        );
        $statement->execute(['table' => $table, 'column' => $column]);

        return $statement->fetchColumn() !== false;
    }

    public function barcodeExists(string $barcode, ?int $exceptId = null): bool
    {
        if ($this->hasTable('book_copies')) {
            $sql = 'SELECT 1 FROM book_copies WHERE barcode = :value';
            $parameters = ['value' => $barcode];
            if ($exceptId !== null) { $sql .= ' AND id <> :except_id'; $parameters['except_id'] = $exceptId; }
            $statement = $this->pdo->prepare($sql . ' LIMIT 1');
            $statement->execute($parameters);
            return $statement->fetchColumn() !== false;
        }
        return $this->exists('barcode', $barcode, $exceptId);
    }

    public function accessionExists(string $accessionNo, ?int $exceptId = null): bool
    {
        if ($this->hasTable('book_copies')) {
            $sql = 'SELECT 1 FROM book_copies WHERE accession_no = :value';
            $parameters = ['value' => $accessionNo];
            if ($exceptId !== null) { $sql .= ' AND id <> :except_id'; $parameters['except_id'] = $exceptId; }
            $statement = $this->pdo->prepare($sql . ' LIMIT 1');
            $statement->execute($parameters);
            return $statement->fetchColumn() !== false;
        }
        return $accessionNo !== '' && $this->exists('accession_no', $accessionNo, $exceptId);
    }

    public function create(BookMutationRequest $request): int
    {
        if ($this->hasTable('book_titles')) {
            $this->assertNormalizedSchema();
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
                $this->syncTitleKeywords($titleId, $request->keywords);
                $copyStatement = $this->pdo->prepare(
                    'INSERT INTO book_copies (title_id, barcode, accession_no, floor_no, section_name, shelf_no, row_no, due_date, return_date, status) VALUES (:title_id, :barcode, :accession_no, :floor_no, :section_name, :shelf_no, :row_no, :due_date, :return_date, :status)'
                );
                for ($index = 0; $index < $request->quantity; $index++) {
                    $barcode = $index === 0 && $request->barcode !== '' ? $request->barcode : 'PENDING-' . $titleId . '-' . ($index + 1) . '-' . bin2hex(random_bytes(3));
                    $accession = $index === 0 && $request->accessionNo !== '' ? $request->accessionNo : 'ACC-' . $titleId . '-' . ($index + 1);
                    $copyStatement->execute([
                        'title_id' => $titleId, 'barcode' => $barcode, 'accession_no' => $accession,
                        'floor_no' => $request->floorNo, 'section_name' => $request->sectionName,
                        'shelf_no' => $request->shelfNo, 'row_no' => $request->rowNo,
                        'due_date' => $request->dueDate !== '' ? $request->dueDate : null,
                        'return_date' => $request->returnDate !== '' ? $request->returnDate : null, 'status' => $request->status,
                    ]);
                    $this->recordCopyAudit(
                        (int) $this->pdo->lastInsertId(),
                        $request->actorId,
                        AuditEventType::ACQUIRED,
                        null,
                        $request->status,
                        null,
                        [
                            'barcode' => $barcode,
                            'accession_no' => $accession,
                            'title' => $request->title,
                            'author' => $request->author,
                            'floor_no' => $request->floorNo,
                            'section_name' => $request->sectionName,
                            'shelf_no' => $request->shelfNo,
                            'row_no' => $request->rowNo,
                        ],
                    );
                }
                $this->pdo->commit();
                return $titleId;
            } catch (\Throwable $exception) {
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                throw $exception;
            }
        }
        if ($request->quantity > 1) {
            throw new \InvalidArgumentException('Run sql/upgrade_bulk_borrowing.sql before managing quantities.');
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
            $this->assertNormalizedSchema();
            $this->updateTitle($id, $request);
            return;
        }
        if ($request->quantity > 1) {
            throw new \InvalidArgumentException('Run sql/upgrade_bulk_borrowing.sql before managing quantities.');
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
            if ($copies === [] && !$this->titleExists($id)) {
                throw new \InvalidArgumentException('Book title not found.');
            }
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
                $removedCopies = $this->copyRecords($removableIds);
                $placeholders = implode(',', array_fill(0, count($removableIds), '?'));
                $deleteStatement = $this->pdo->prepare('UPDATE book_copies SET deleted_at = CURRENT_TIMESTAMP WHERE id IN (' . $placeholders . ')');
                $deleteStatement->execute($removableIds);
                foreach ($removedCopies as $removedCopy) {
                    $this->recordCopyAudit((int) $removedCopy['id'], $request->actorId, AuditEventType::ARCHIVED, null, null, null, $removedCopy);
                }
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
            $this->syncTitleKeywords($id, $request->keywords);

            $quantityAfterRemoval = min($request->quantity, $currentQuantity);
            if ($request->quantity > $quantityAfterRemoval) {
                $copyInsert = $this->pdo->prepare(
                    'INSERT INTO book_copies (title_id, barcode, accession_no, floor_no, section_name, shelf_no, row_no, due_date, return_date, status) VALUES (:title_id, :barcode, :accession_no, :floor_no, :section_name, :shelf_no, :row_no, :due_date, :return_date, :status)'
                );
                for ($index = $quantityAfterRemoval; $index < $request->quantity; $index++) {
                    $barcode = $index === 0 && $request->barcode !== '' ? $request->barcode : 'PENDING-' . $id . '-' . ($index + 1) . '-' . bin2hex(random_bytes(3));
                    $accession = $index === 0 && $request->accessionNo !== '' ? $request->accessionNo : 'ACC-' . $id . '-' . ($index + 1);
                    $copyInsert->execute([
                        'title_id' => $id,
                        'barcode' => $barcode,
                        'accession_no' => $accession,
                        'floor_no' => $request->floorNo !== '' ? $request->floorNo : null,
                        'section_name' => $request->sectionName !== '' ? $request->sectionName : null,
                        'shelf_no' => $request->shelfNo !== '' ? $request->shelfNo : null,
                        'row_no' => $request->rowNo !== '' ? $request->rowNo : null,
                        'due_date' => $request->dueDate !== '' ? $request->dueDate : null,
                        'return_date' => $request->returnDate !== '' ? $request->returnDate : null,
                        'status' => $request->status,
                    ]);
                    $this->recordCopyAudit(
                        (int) $this->pdo->lastInsertId(),
                        $request->actorId,
                        AuditEventType::ACQUIRED,
                        null,
                        $request->status,
                        null,
                        [
                            'barcode' => $barcode,
                            'accession_no' => $accession,
                            'title' => $request->title,
                            'author' => $request->author,
                            'floor_no' => $request->floorNo,
                            'section_name' => $request->sectionName,
                            'shelf_no' => $request->shelfNo,
                            'row_no' => $request->rowNo,
                        ],
                    );
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    /** @param list<int> $ids */
    public function archive(array $ids, int $actorId = 0): int
    {
        if ($this->hasTable('book_titles')) {
            return $this->setTitleArchived($ids, true, $actorId);
        }
        $this->assertNoActiveLoans($ids, 'archive');

        return $this->setArchived($ids, true);
    }

    /** @param list<int> $ids */
    public function restore(array $ids, int $actorId = 0): int
    {
        if ($this->hasTable('book_titles')) {
            return $this->setTitleArchived($ids, false, $actorId);
        }

        return $this->setArchived($ids, false);
    }

    /** @param list<int> $ids */
    public function delete(array $ids, int $actorId = 0): int
    {
        if ($this->hasTable('book_titles')) {
            $this->assertTitleIds($ids);
            $this->assertNoActiveTitleLoans($ids, 'delete');
            $copies = $this->copyRecordsForTitles($ids);
            $this->pdo->beginTransaction();
            try {
                foreach ($copies as $copy) {
                    $this->recordCopyAudit((int) $copy['id'], $actorId, AuditEventType::DELETED, null, null, null, $copy);
                }
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $statement = $this->pdo->prepare('DELETE FROM book_titles WHERE id IN (' . $placeholders . ')');
                $statement->execute($ids);
                $this->pdo->commit();

                return $statement->rowCount();
            } catch (\Throwable $exception) {
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                throw $exception;
            }
        }
        $this->assertIds($ids);
        $this->assertNoActiveLoans($ids, 'delete');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->pdo->prepare('DELETE FROM books WHERE id IN (' . $placeholders . ')');
        $statement->execute($ids);

        return $statement->rowCount();
    }

    /** @return list<array<string, mixed>> */
    public function copies(int $titleId): array
    {
        $this->assertNormalizedSchema();
        if ($titleId < 1) {
            return [];
        }
        $printedColumn = $this->hasColumn('book_copies', 'printed_at') ? 'c.printed_at' : 'NULL';
        $statement = $this->pdo->prepare(
            'SELECT c.id AS copy_id, c.title_id, c.barcode, c.accession_no, c.floor_no, c.section_name,
                    c.shelf_no, c.row_no, c.due_date, c.return_date, c.status, c.deleted_at, ' . $printedColumn . ' AS printed_at, t.title
             FROM book_copies c JOIN book_titles t ON t.id = c.title_id
             WHERE c.title_id = :title_id
             ORDER BY c.deleted_at IS NOT NULL, c.id'
        );
        $statement->execute(['title_id' => $titleId]);

        /** @var list<array<string, mixed>> $copies */
        $copies = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $copies;
    }

    public function updateCopy(BookCopyMutationRequest $request): void
    {
        $this->assertNormalizedSchema();
        if ($request->copyId < 1) {
            throw new \InvalidArgumentException('A valid copy is required.');
        }

        $copy = $this->copyRecord($request->copyId);
        if ($copy === null) {
            throw new \InvalidArgumentException('Book copy not found.');
        }
        if ($this->copyIdentifierExists('barcode', $request->barcode, $request->copyId)) {
            throw new \InvalidArgumentException('Another copy already uses this barcode.');
        }
        if ($request->accessionNo !== '' && $this->copyIdentifierExists('accession_no', $request->accessionNo, $request->copyId)) {
            throw new \InvalidArgumentException('Another copy already uses this accession number.');
        }
        if ($request->status === 'Available' && $this->copyHasActiveLoan($request->copyId)) {
            throw new \InvalidArgumentException('A borrowed copy cannot be marked available.');
        }
        $copyStatus = is_string($copy['status'] ?? null) ? $copy['status'] : '';
        if ($request->status !== $copyStatus) {
            $isLossOrDamageTransition = in_array($copyStatus, ['Lost', 'Damaged'], true)
                || in_array($request->status, ['Lost', 'Damaged'], true);
            if (!$isLossOrDamageTransition) {
                throw new \InvalidArgumentException('Copy status is managed by the borrowing workflow.');
            }
            if (($copyStatus === 'Lost' || $copyStatus === 'Damaged' || $request->status === 'Lost' || $request->status === 'Damaged')
                && trim($request->reason) === '') {
                throw new \InvalidArgumentException('A reason is required when marking a copy lost or damaged.');
            }
        }

        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'UPDATE book_copies SET barcode = :barcode, accession_no = :accession_no, floor_no = :floor_no,
                 section_name = :section_name, shelf_no = :shelf_no, row_no = :row_no, due_date = :due_date,
                 return_date = :return_date, status = :status WHERE id = :id'
            );
            $statement->execute([
                'barcode' => $request->barcode,
                'accession_no' => $this->nullable($request->accessionNo),
                'floor_no' => $this->nullable($request->floorNo),
                'section_name' => $this->nullable($request->sectionName),
                'shelf_no' => $this->nullable($request->shelfNo),
                'row_no' => $this->nullable($request->rowNo),
                'due_date' => $this->nullable($request->dueDate),
                'return_date' => $this->nullable($request->returnDate),
                'status' => $request->status,
                'id' => $request->copyId,
            ]);
            if ($request->status !== $copyStatus) {
                $this->recordCopyAudit(
                    $request->copyId,
                    $request->actorId,
                    AuditEventType::STATUS_CHANGED,
                    $copyStatus,
                    $request->status,
                    $request->reason,
                    $copy,
                );
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    /** @param list<int> $ids */
    public function archiveCopies(array $ids, int $actorId = 0): int
    {
        return $this->setCopyArchived($ids, true, $actorId);
    }

    /** @param list<int> $ids */
    public function restoreCopies(array $ids, int $actorId = 0): int
    {
        return $this->setCopyArchived($ids, false, $actorId);
    }

    /** @param list<int> $ids */
    public function deleteCopies(array $ids, int $actorId = 0): int
    {
        $this->assertNormalizedSchema();
        $this->assertCopyIds($ids);
        $this->assertNoActiveCopyLoans($ids, 'delete');
        $copies = $this->copyRecords($ids);
        $this->pdo->beginTransaction();
        try {
            foreach ($copies as $copy) {
                $this->recordCopyAudit((int) $copy['id'], $actorId, AuditEventType::DELETED, null, null, null, $copy);
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $statement = $this->pdo->prepare('DELETE FROM book_copies WHERE id IN (' . $placeholders . ')');
            $statement->execute($ids);
            $this->pdo->commit();

            return $statement->rowCount();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<string, mixed>|null */
    private function copyRecord(int $copyId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM book_copies WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $statement->execute(['id' => $copyId]);
        /** @var array<string, mixed>|false $copy */
        $copy = $statement->fetch(PDO::FETCH_ASSOC);

        return $copy === false ? null : $copy;
    }

    /** @param list<int> $ids @return list<array<string, mixed>> */
    private function copyRecords(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->pdo->prepare(
            'SELECT c.*, t.title, t.author FROM book_copies c JOIN book_titles t ON t.id = c.title_id '
            . 'WHERE c.id IN (' . $placeholders . ')'
        );
        $statement->execute($ids);
        /** @var list<array<string, mixed>> $copies */
        $copies = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $copies;
    }

    /** @param array<string, mixed> $copy */
    private function recordCopyAudit(
        int $copyId,
        int $actorId,
        AuditEventType $type,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $reason,
        array $copy,
    ): void {
        if ($this->audit === null) {
            return;
        }

        $this->audit->record(new AuditEvent(
            $copyId,
            $actorId > 0 ? $actorId : null,
            $type,
            $fromStatus,
            $toStatus,
            $reason === '' ? null : $reason,
            null,
            null,
            null,
            [
                'barcode' => $this->stringValue($copy['barcode'] ?? null),
                'accession_no' => $this->stringValue($copy['accession_no'] ?? null),
                'title' => $this->stringValue($copy['title'] ?? null),
                'author' => $this->stringValue($copy['author'] ?? null),
                'floor_no' => $this->stringValue($copy['floor_no'] ?? null),
                'section_name' => $this->stringValue($copy['section_name'] ?? null),
                'shelf_no' => $this->stringValue($copy['shelf_no'] ?? null),
                'row_no' => $this->stringValue($copy['row_no'] ?? null),
            ],
            new \DateTimeImmutable(),
        ));
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? (string) $value : '';
    }

    private function copyIdentifierExists(string $column, string $value, int $exceptId): bool
    {
        if (!in_array($column, ['barcode', 'accession_no'], true) || $value === '') {
            return false;
        }
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM book_copies WHERE ' . $column . ' = :value AND id <> :id LIMIT 1'
        );
        $statement->execute(['value' => $value, 'id' => $exceptId]);

        return $statement->fetchColumn() !== false;
    }

    private function copyHasActiveLoan(int $copyId): bool
    {
        if (!$this->hasTable('borrowing_items')) {
            return false;
        }
        $statement = $this->pdo->prepare('SELECT 1 FROM borrowing_items WHERE copy_id = :copy_id AND return_date IS NULL LIMIT 1');
        $statement->execute(['copy_id' => $copyId]);

        return $statement->fetchColumn() !== false;
    }

    /** @param list<int> $ids */
    private function setCopyArchived(array $ids, bool $archived, int $actorId): int
    {
        $this->assertNormalizedSchema();
        $this->assertCopyIds($ids);
        if ($archived) {
            $this->assertNoActiveCopyLoans($ids, 'archive');
        }
        $copies = $this->copyRecords($ids);
        $this->pdo->beginTransaction();
        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $value = $archived ? 'CURRENT_TIMESTAMP' : 'NULL';
            $statement = $this->pdo->prepare('UPDATE book_copies SET deleted_at = ' . $value . ' WHERE id IN (' . $placeholders . ')');
            $statement->execute($ids);
            $eventType = $archived ? AuditEventType::ARCHIVED : AuditEventType::RESTORED;
            foreach ($copies as $copy) {
                $this->recordCopyAudit((int) $copy['id'], $actorId, $eventType, null, null, null, $copy);
            }
            $this->pdo->commit();

            return $statement->rowCount();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param list<int> $ids */
    private function setTitleArchived(array $ids, bool $archived, int $actorId): int
    {
        $this->assertNormalizedSchema();
        $this->assertTitleIds($ids);
        if ($archived) {
            $this->assertNoActiveTitleLoans($ids, 'archive');
        }
        $copies = $this->copyRecordsForTitles($ids);
        $this->pdo->beginTransaction();
        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $value = $archived ? 'CURRENT_TIMESTAMP' : 'NULL';
            $statement = $this->pdo->prepare('UPDATE book_copies SET deleted_at = ' . $value . ' WHERE title_id IN (' . $placeholders . ')');
            $statement->execute($ids);
            $eventType = $archived ? AuditEventType::ARCHIVED : AuditEventType::RESTORED;
            foreach ($copies as $copy) {
                $this->recordCopyAudit((int) $copy['id'], $actorId, $eventType, null, null, null, $copy);
            }
            $this->pdo->commit();

            return $statement->rowCount();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    /** @param list<int> $titleIds @return list<array<string, mixed>> */
    private function copyRecordsForTitles(array $titleIds): array
    {
        if ($titleIds === []) return [];
        $placeholders = implode(',', array_fill(0, count($titleIds), '?'));
        $statement = $this->pdo->prepare(
            'SELECT c.*, t.title, t.author FROM book_copies c JOIN book_titles t ON t.id = c.title_id WHERE c.title_id IN (' . $placeholders . ')'
        );
        $statement->execute($titleIds);
        /** @var list<array<string, mixed>> $copies */
        $copies = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $copies;
    }

    /** @param list<int> $ids */
    private function assertTitleIds(array $ids): void
    {
        if ($ids === [] || array_filter($ids, static fn (int $id): bool => $id < 1) !== []) {
            throw new \InvalidArgumentException('No books selected.');
        }
    }

    /** @param list<int> $ids */
    private function assertNoActiveTitleLoans(array $ids, string $operation): void
    {
        if (!$this->hasTable('borrowing_items')) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM borrowing_items bi JOIN book_copies bc ON bc.id = bi.copy_id
             WHERE bi.return_date IS NULL AND bc.title_id IN (' . $placeholders . ')'
        );
        $statement->execute($ids);
        if ((int) $statement->fetchColumn() > 0) {
            throw new \InvalidArgumentException('Cannot ' . $operation . ' books with active loans.');
        }
    }

    private function assertNormalizedSchema(): void
    {
        if (!$this->hasTable('book_titles') || !$this->hasTable('book_copies')) {
            throw new \InvalidArgumentException('Run sql/upgrade_bulk_borrowing.sql before managing quantities.');
        }
    }

    private function titleExists(int $titleId): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM book_titles WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $titleId]);

        return $statement->fetchColumn() !== false;
    }

    /** @param list<int> $ids */
    private function assertCopyIds(array $ids): void
    {
        if ($ids === [] || array_filter($ids, static fn (int $id): bool => $id < 1) !== []) {
            throw new \InvalidArgumentException('No book copies selected.');
        }
    }

    /** @param list<int> $ids */
    private function assertNoActiveCopyLoans(array $ids, string $operation): void
    {
        if (!$this->hasTable('borrowing_items')) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM borrowing_items WHERE return_date IS NULL AND copy_id IN (' . $placeholders . ')'
        );
        $statement->execute($ids);
        if ((int) $statement->fetchColumn() > 0) {
            throw new \InvalidArgumentException('Cannot ' . $operation . ' copies with active loans.');
        }
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
