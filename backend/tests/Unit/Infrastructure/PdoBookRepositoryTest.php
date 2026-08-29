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

    public function testSearchReturnsOneTitleWithTotalAndAvailableCopyCounts(): void
    {
        $this->pdo->exec('CREATE TABLE book_titles (id INTEGER PRIMARY KEY AUTOINCREMENT, isbn VARCHAR(30), title VARCHAR(200) NOT NULL, author VARCHAR(150), publisher VARCHAR(150), description TEXT, cover_file VARCHAR(255), category_name VARCHAR(100), quantity INTEGER NOT NULL, created_at DATETIME)');
        $this->pdo->exec('CREATE TABLE book_copies (id INTEGER PRIMARY KEY AUTOINCREMENT, title_id INTEGER NOT NULL, barcode VARCHAR(50) NOT NULL, floor_no VARCHAR(20), section_name VARCHAR(80), shelf_no VARCHAR(20), row_no VARCHAR(20), status VARCHAR(20) NOT NULL, deleted_at DATETIME)');
        $this->pdo->exec("INSERT INTO book_titles (isbn, title, author, category_name, quantity) VALUES ('978-1', 'Clean Code', 'Robert Martin', 'Computer Science', 3)");
        $this->pdo->exec("INSERT INTO book_copies (title_id, barcode, status) VALUES (1, 'COPY-1', 'Available'), (1, 'COPY-2', 'Borrowed'), (1, 'COPY-3', 'Available')");

        $result = (new PdoBookRepository($this->pdo))->search(BookSearchCriteria::fromArray(['search' => 'clean']));

        self::assertSame(1, $result->total());
        self::assertSame(1, $result->books()[0]['id']);
        self::assertSame(3, (int) $result->books()[0]['quantity']);
        self::assertSame(2, (int) $result->books()[0]['available_quantity']);
    }

    public function testLookupReturnsCopyAndTitleAvailabilityForScannerInput(): void
    {
        $this->pdo->exec('CREATE TABLE book_titles (id INTEGER PRIMARY KEY AUTOINCREMENT, title VARCHAR(200) NOT NULL, author VARCHAR(150), quantity INTEGER NOT NULL)');
        $this->pdo->exec('CREATE TABLE book_copies (id INTEGER PRIMARY KEY AUTOINCREMENT, title_id INTEGER NOT NULL, barcode VARCHAR(50) NOT NULL, status VARCHAR(20) NOT NULL, deleted_at DATETIME)');
        $this->pdo->exec("INSERT INTO book_titles (title, author, quantity) VALUES ('Clean Code', 'Robert Martin', 2)");
        $this->pdo->exec("INSERT INTO book_copies (title_id, barcode, status) VALUES (1, 'COPY-1', 'Available'), (1, 'COPY-2', 'Borrowed')");

        $copy = (new PdoBookRepository($this->pdo))->lookupCopyByBarcode('COPY-1');

        self::assertIsArray($copy);
        self::assertSame(1, (int) $copy['title_id']);
        self::assertSame('Clean Code', $copy['title']);
        self::assertSame(1, (int) $copy['available_quantity']);
    }

    public function testNormalizedUpdatePersistsQuantityAndCreatesMissingCopies(): void
    {
        $this->pdo->exec('CREATE TABLE book_titles (id INTEGER PRIMARY KEY AUTOINCREMENT, isbn VARCHAR(30), title VARCHAR(200) NOT NULL, author VARCHAR(150), publisher VARCHAR(150), description TEXT, cover_file VARCHAR(255), category_name VARCHAR(100), quantity INTEGER NOT NULL, created_at DATETIME)');
        $this->pdo->exec('CREATE TABLE book_copies (id INTEGER PRIMARY KEY AUTOINCREMENT, title_id INTEGER NOT NULL, barcode VARCHAR(50) NOT NULL, accession_no VARCHAR(50), floor_no VARCHAR(20), section_name VARCHAR(80), shelf_no VARCHAR(20), row_no VARCHAR(20), due_date DATE, return_date DATE, status VARCHAR(20) NOT NULL, deleted_at DATETIME)');
        $this->pdo->exec("INSERT INTO book_titles (id, title, author, category_name, quantity) VALUES (1, 'Clean Code', 'Robert Martin', 'Computer Science', 2)");
        $this->pdo->exec("INSERT INTO book_copies (title_id, barcode, accession_no, status) VALUES (1, 'COPY-1', 'ACC-1', 'Available'), (1, 'COPY-2', 'ACC-2', 'Available')");

        $request = new BookMutationRequest(
            barcode: 'COPY-1',
            title: 'Clean Code Updated',
            author: 'Robert Martin',
            categoryName: 'Computer Science',
            status: 'Available',
            quantity: 4,
        );

        (new PdoBookRepository($this->pdo))->update(1, $request);

        self::assertSame(4, (int) $this->pdo->query('SELECT quantity FROM book_titles WHERE id = 1')->fetchColumn());
        self::assertSame('Clean Code Updated', $this->pdo->query('SELECT title FROM book_titles WHERE id = 1')->fetchColumn());
        self::assertSame(4, (int) $this->pdo->query('SELECT COUNT(*) FROM book_copies WHERE title_id = 1 AND deleted_at IS NULL')->fetchColumn());
    }

    public function testNormalizedCreateGeneratesUniqueIdentifiersForEveryRequestedCopy(): void
    {
        $this->pdo->exec('CREATE TABLE book_titles (id INTEGER PRIMARY KEY AUTOINCREMENT, isbn VARCHAR(30), title VARCHAR(200) NOT NULL, author VARCHAR(150), publisher VARCHAR(150), description TEXT, cover_file VARCHAR(255), category_name VARCHAR(100), quantity INTEGER NOT NULL, created_at DATETIME)');
        $this->pdo->exec('CREATE TABLE book_copies (id INTEGER PRIMARY KEY AUTOINCREMENT, title_id INTEGER NOT NULL, barcode VARCHAR(50) NOT NULL UNIQUE, accession_no VARCHAR(50), floor_no VARCHAR(20), section_name VARCHAR(80), shelf_no VARCHAR(20), row_no VARCHAR(20), due_date DATE, return_date DATE, status VARCHAR(20) NOT NULL, deleted_at DATETIME)');

        $titleId = (new PdoBookRepository($this->pdo))->create(new BookMutationRequest(title: 'Clean Code', quantity: 3));
        $rows = $this->pdo->query('SELECT barcode, accession_no FROM book_copies WHERE title_id = ' . $titleId . ' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

        self::assertCount(3, $rows);
        self::assertCount(3, array_unique(array_column($rows, 'barcode')));
        self::assertCount(3, array_unique(array_column($rows, 'accession_no')));
        self::assertStringStartsWith('PENDING-' . $titleId . '-', $rows[0]['barcode']);
    }

    public function testNormalizedUpdateDoesNotRemoveBorrowedCopiesWhenReducingQuantity(): void
    {
        $this->pdo->exec('CREATE TABLE book_titles (id INTEGER PRIMARY KEY AUTOINCREMENT, isbn VARCHAR(30), title VARCHAR(200) NOT NULL, author VARCHAR(150), publisher VARCHAR(150), description TEXT, cover_file VARCHAR(255), category_name VARCHAR(100), quantity INTEGER NOT NULL)');
        $this->pdo->exec('CREATE TABLE book_copies (id INTEGER PRIMARY KEY AUTOINCREMENT, title_id INTEGER NOT NULL, barcode VARCHAR(50) NOT NULL, accession_no VARCHAR(50), floor_no VARCHAR(20), section_name VARCHAR(80), shelf_no VARCHAR(20), row_no VARCHAR(20), due_date DATE, return_date DATE, status VARCHAR(20) NOT NULL, deleted_at DATETIME)');
        $this->pdo->exec('CREATE TABLE borrowing_items (id INTEGER PRIMARY KEY AUTOINCREMENT, copy_id INTEGER, return_date DATETIME)');
        $this->pdo->exec("INSERT INTO book_titles (id, title, quantity) VALUES (1, 'Clean Code', 2)");
        $this->pdo->exec("INSERT INTO book_copies (title_id, barcode, status) VALUES (1, 'COPY-1', 'Borrowed'), (1, 'COPY-2', 'Available')");
        $this->pdo->exec("INSERT INTO borrowing_items (copy_id, return_date) VALUES (1, NULL)");

        (new PdoBookRepository($this->pdo))->update(1, new BookMutationRequest(title: 'Clean Code', quantity: 1));

        self::assertSame(1, (int) $this->pdo->query('SELECT quantity FROM book_titles WHERE id = 1')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM book_copies WHERE title_id = 1 AND deleted_at IS NULL')->fetchColumn());
        self::assertSame('Borrowed', $this->pdo->query("SELECT status FROM book_copies WHERE barcode = 'COPY-1'")->fetchColumn());
    }

    public function testNormalizedCopyUpdateUsesCopyIdAndReturnsTitleScopedCopies(): void
    {
        $this->pdo->exec('CREATE TABLE book_titles (id INTEGER PRIMARY KEY AUTOINCREMENT, title VARCHAR(200) NOT NULL, quantity INTEGER NOT NULL)');
        $this->pdo->exec('CREATE TABLE book_copies (id INTEGER PRIMARY KEY AUTOINCREMENT, title_id INTEGER NOT NULL, barcode VARCHAR(50) NOT NULL, accession_no VARCHAR(50), floor_no VARCHAR(20), section_name VARCHAR(80), shelf_no VARCHAR(20), row_no VARCHAR(20), due_date DATE, return_date DATE, status VARCHAR(20) NOT NULL, deleted_at DATETIME)');
        $this->pdo->exec("INSERT INTO book_titles (id, title, quantity) VALUES (1, 'Clean Code', 2)");
        $this->pdo->exec("INSERT INTO book_copies (id, title_id, barcode, accession_no, status) VALUES (11, 1, 'COPY-1', 'ACC-1', 'Available'), (12, 1, 'COPY-2', 'ACC-2', 'Available')");

        $repository = new PdoBookRepository($this->pdo);
        $copies = $repository->copies(1);

        self::assertCount(2, $copies);
        $repository->updateCopy(new \App\Application\DTO\BookCopyMutationRequest(12, 'COPY-2-UPDATED', 'ACC-2', status: 'Available'));

        self::assertSame('COPY-2-UPDATED', $this->pdo->query('SELECT barcode FROM book_copies WHERE id = 12')->fetchColumn());
        self::assertSame('COPY-1', $this->pdo->query('SELECT barcode FROM book_copies WHERE id = 11')->fetchColumn());
    }

    public function testNormalizedCopyUpdateRejectsDuplicateBarcode(): void
    {
        $this->pdo->exec('CREATE TABLE book_titles (id INTEGER PRIMARY KEY AUTOINCREMENT, title VARCHAR(200) NOT NULL, quantity INTEGER NOT NULL)');
        $this->pdo->exec('CREATE TABLE book_copies (id INTEGER PRIMARY KEY AUTOINCREMENT, title_id INTEGER NOT NULL, barcode VARCHAR(50) NOT NULL, accession_no VARCHAR(50), status VARCHAR(20) NOT NULL, deleted_at DATETIME)');
        $this->pdo->exec("INSERT INTO book_titles (id, title, quantity) VALUES (1, 'Clean Code', 2)");
        $this->pdo->exec("INSERT INTO book_copies (id, title_id, barcode, status) VALUES (11, 1, 'COPY-1', 'Available'), (12, 1, 'COPY-2', 'Available')");

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Another copy already uses this barcode.');

        (new PdoBookRepository($this->pdo))->updateCopy(
            new \App\Application\DTO\BookCopyMutationRequest(12, 'COPY-1'),
        );
    }

    public function testNormalizedCopyUpdateDoesNotAllowInventoryToChangeBorrowingStatus(): void
    {
        $this->pdo->exec('CREATE TABLE book_titles (id INTEGER PRIMARY KEY AUTOINCREMENT, title VARCHAR(200) NOT NULL, quantity INTEGER NOT NULL)');
        $this->pdo->exec('CREATE TABLE book_copies (id INTEGER PRIMARY KEY AUTOINCREMENT, title_id INTEGER NOT NULL, barcode VARCHAR(50) NOT NULL, accession_no VARCHAR(50), status VARCHAR(20) NOT NULL, deleted_at DATETIME)');
        $this->pdo->exec("INSERT INTO book_titles (id, title, quantity) VALUES (1, 'Clean Code', 1)");
        $this->pdo->exec("INSERT INTO book_copies (id, title_id, barcode, status) VALUES (11, 1, 'COPY-1', 'Available')");

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Copy status is managed by the borrowing workflow.');

        (new PdoBookRepository($this->pdo))->updateCopy(
            new \App\Application\DTO\BookCopyMutationRequest(11, 'COPY-1', status: 'Borrowed'),
        );
    }
}
