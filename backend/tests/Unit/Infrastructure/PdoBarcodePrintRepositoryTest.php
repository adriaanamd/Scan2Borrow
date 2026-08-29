<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Infrastructure\Persistence\PdoBarcodePrintRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoBarcodePrintRepositoryTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, role TEXT NOT NULL)');
        $this->pdo->exec('CREATE TABLE book_titles (id INTEGER PRIMARY KEY, title TEXT NOT NULL, author TEXT)');
        $this->pdo->exec('CREATE TABLE book_copies (id INTEGER PRIMARY KEY, title_id INTEGER NOT NULL, barcode TEXT NOT NULL, accession_no TEXT, floor_no TEXT, section_name TEXT, shelf_no TEXT, row_no TEXT, deleted_at TEXT, printed_at TEXT)');
        $this->pdo->exec('CREATE TABLE barcode_print_batches (id INTEGER PRIMARY KEY AUTOINCREMENT, batch_token TEXT NOT NULL UNIQUE, title_id INTEGER NOT NULL, printed_by INTEGER NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');
        $this->pdo->exec('CREATE TABLE barcode_print_batch_items (id INTEGER PRIMARY KEY AUTOINCREMENT, batch_id INTEGER NOT NULL, copy_id INTEGER NOT NULL, title TEXT NOT NULL, author TEXT, barcode TEXT NOT NULL, accession_no TEXT, floor_no TEXT, section_name TEXT, shelf_no TEXT, row_no TEXT, UNIQUE(batch_id, copy_id))');
        $this->pdo->exec("INSERT INTO users (id, role) VALUES (7, 'librarian')");
        $this->pdo->exec("INSERT INTO book_titles (id, title, author) VALUES (4, 'Clean Code', 'Robert Martin')");
        $this->pdo->exec("INSERT INTO book_copies (id, title_id, barcode, accession_no, floor_no, section_name, shelf_no, row_no, deleted_at, printed_at) VALUES
            (11, 4, 'BC-11', 'ACC-11', '2', 'A', '3', '1', NULL, NULL),
            (12, 4, 'BC-12', 'ACC-12', '2', 'A', '3', '2', NULL, '2026-08-01 09:00:00'),
            (13, 4, 'BC-13', 'ACC-13', '2', 'A', '3', '3', '2026-08-01 10:00:00', NULL)");
    }

    public function testCreatesBatchForOnlyActiveUnprintedCopiesAndStoresSnapshots(): void
    {
        $batch = (new PdoBarcodePrintRepository($this->pdo))->createBatch(4, 7, str_repeat('a', 32));

        self::assertNotNull($batch);
        self::assertSame('Clean Code', $batch->title);
        self::assertCount(1, $batch->labels);
        self::assertSame('BC-11', $batch->labels[0]['barcode']);
        self::assertNotNull($this->pdo->query('SELECT printed_at FROM book_copies WHERE id = 11')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM barcode_print_batch_items')->fetchColumn());
    }

    public function testReturnsNullWhenAllActiveCopiesAreAlreadyPrinted(): void
    {
        $this->pdo->exec("UPDATE book_copies SET printed_at = '2026-08-02 09:00:00' WHERE id = 11");

        $batch = (new PdoBarcodePrintRepository($this->pdo))->createBatch(4, 7, str_repeat('b', 32));

        self::assertNull($batch);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM barcode_print_batches')->fetchColumn());
    }

    public function testFindBatchAndHistoryUseImmutableSnapshots(): void
    {
        $repository = new PdoBarcodePrintRepository($this->pdo);
        $repository->createBatch(4, 7, str_repeat('c', 32));
        $this->pdo->exec("UPDATE book_titles SET title = 'Refactoring' WHERE id = 4");

        $batch = $repository->findBatch(str_repeat('c', 32));
        $history = $repository->history(4);

        self::assertNotNull($batch);
        self::assertSame('Clean Code', $batch->title);
        self::assertSame('BC-11', $batch->labels[0]['barcode']);
        self::assertCount(1, $history);
        self::assertSame(1, (int) $history[0]['label_count']);
        self::assertSame(str_repeat('c', 32), $history[0]['batch_token']);
    }
}
