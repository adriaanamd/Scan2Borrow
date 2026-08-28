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

    public function barcodeExists(string $barcode, ?int $exceptId = null): bool
    {
        return $this->exists('barcode', $barcode, $exceptId);
    }

    public function accessionExists(string $accessionNo, ?int $exceptId = null): bool
    {
        return $accessionNo !== '' && $this->exists('accession_no', $accessionNo, $exceptId);
    }

    public function create(BookMutationRequest $request): int
    {
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
