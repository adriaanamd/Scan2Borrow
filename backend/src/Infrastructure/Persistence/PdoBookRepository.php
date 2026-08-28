<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\DTO\BookMutationRequest;
use App\Domain\Book\BookSearchCriteria;
use App\Domain\Book\BookSearchResult;
use PDO;

final class PdoBookRepository implements BookRepositoryInterface, BookMutationRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function search(BookSearchCriteria $criteria): BookSearchResult
    {
        $where = [$criteria->archived() ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL'];
        $parameters = [];

        if ($criteria->search() !== '') {
            $where[] = '(title LIKE :search OR author LIKE :search OR publisher LIKE :search OR ' .
                'category_name LIKE :search OR barcode LIKE :search OR accession_no LIKE :search OR isbn LIKE :search)';
            $parameters['search'] = '%' . $criteria->search() . '%';
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
}
