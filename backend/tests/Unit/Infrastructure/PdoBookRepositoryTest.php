<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Application\DTO\BookMutationRequest;
use App\Domain\Book\BookSearchCriteria;
use App\Infrastructure\Persistence\PdoBookRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoBookRepositoryTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE books (' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT, barcode VARCHAR(50) NOT NULL, accession_no VARCHAR(50), ' .
            'isbn VARCHAR(30), title VARCHAR(200) NOT NULL, author VARCHAR(150), publisher VARCHAR(150), ' .
            'category_name VARCHAR(100) NOT NULL, cover_file VARCHAR(255), floor_no VARCHAR(20), ' .
            'section_name VARCHAR(80), shelf_no VARCHAR(20), row_no VARCHAR(20), due_date DATE, return_date DATE, ' .
            'status VARCHAR(20) NOT NULL DEFAULT \'Available\', deleted_at DATETIME, created_at DATETIME, description TEXT)'
        );
        $this->pdo->exec(
            "INSERT INTO books (barcode, accession_no, title, author, publisher, category_name, status, created_at) " .
            "VALUES ('BK-1', 'ACC-1', 'Clean Code', 'Robert Martin', 'Prentice Hall', 'Computer Science', 'Available', '2026-08-01')"
        );
        $this->pdo->exec(
            "INSERT INTO books (barcode, title, author, category_name, status, deleted_at, created_at) " .
            "VALUES ('BK-2', 'Archived Book', 'Someone', 'History', 'Available', '2026-08-02', '2026-08-01')"
        );
        $this->pdo->exec(
            'CREATE TABLE borrowing (' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT, book_id INTEGER NOT NULL, return_date DATETIME NULL)'
        );
    }

    public function testSearchUsesSchemaFieldsAndExcludesArchivedBooksByDefault(): void
    {
        $repository = new PdoBookRepository($this->pdo);

        $result = $repository->search(BookSearchCriteria::fromArray(['search' => 'clean']));

        self::assertSame(1, $result->total());
        self::assertSame('Clean Code', $result->books()[0]['title']);
        self::assertSame('ACC-1', $result->books()[0]['accession_no']);
    }

    public function testArchivedFilterIncludesOnlyArchivedBooks(): void
    {
        $repository = new PdoBookRepository($this->pdo);

        $result = $repository->search(BookSearchCriteria::fromArray(['archived' => '1']));

        self::assertSame(1, $result->total());
        self::assertSame('Archived Book', $result->books()[0]['title']);
    }

    public function testCreateAndUpdatePreserveExistingBookColumns(): void
    {
        $repository = new PdoBookRepository($this->pdo);
        $request = new BookMutationRequest(
            barcode: 'BK-3',
            title: 'Refactoring',
            accessionNo: 'ACC-3',
            isbn: '9780000000000',
            author: 'Martin Fowler',
            publisher: 'Addison-Wesley',
            description: 'A book',
            categoryName: 'Computer Science',
            floorNo: '2',
            sectionName: 'IT Section',
            shelfNo: 'A1',
            rowNo: '3',
            status: 'Available',
        );

        $id = $repository->create($request);
        $repository->update($id, $request->withStatus('Borrowed'));
        $statement = $this->pdo->prepare('SELECT * FROM books WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        self::assertIsArray($row);
        self::assertSame('Borrowed', $row['status']);
        self::assertSame('ACC-3', $row['accession_no']);
        self::assertSame('Martin Fowler', $row['author']);
    }

    public function testArchiveRefusesBooksWithAnActiveLoan(): void
    {
        $this->pdo->exec("INSERT INTO borrowing (book_id, return_date) VALUES (1, NULL)");
        $repository = new PdoBookRepository($this->pdo);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot archive books with active loans.');

        $repository->archive([1]);
    }

    public function testDeleteRefusesBooksWithAnActiveLoan(): void
    {
        $this->pdo->exec("INSERT INTO borrowing (book_id, return_date) VALUES (1, NULL)");
        $repository = new PdoBookRepository($this->pdo);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot delete books with active loans.');

        $repository->delete([1]);
    }
}
