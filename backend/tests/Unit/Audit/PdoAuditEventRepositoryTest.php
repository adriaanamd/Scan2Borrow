<?php

declare(strict_types=1);

namespace Tests\Unit\Audit;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Infrastructure\Persistence\PdoAuditEventRepository;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoAuditEventRepositoryTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, firstname TEXT, lastname TEXT)');
        $this->pdo->exec('CREATE TABLE book_titles (id INTEGER PRIMARY KEY, title TEXT, author TEXT)');
        $this->pdo->exec('CREATE TABLE book_copies (id INTEGER PRIMARY KEY, title_id INTEGER, barcode TEXT, accession_no TEXT, floor_no TEXT, section_name TEXT, shelf_no TEXT, row_no TEXT, status TEXT, deleted_at TEXT)');
        $this->pdo->exec('CREATE TABLE audit_events (id INTEGER PRIMARY KEY AUTOINCREMENT, copy_id INTEGER, actor_user_id INTEGER, event_type TEXT, from_status TEXT, to_status TEXT, reason TEXT, transaction_id INTEGER, borrowing_item_id INTEGER, print_batch_id INTEGER, metadata TEXT, occurred_at TEXT)');
        $this->pdo->exec("INSERT INTO users (id, firstname, lastname) VALUES (7, 'Ada', 'Lovelace')");
        $this->pdo->exec("INSERT INTO book_titles (id, title, author) VALUES (3, 'Clean Code', 'Robert Martin')");
        $this->pdo->exec("INSERT INTO book_copies (id, title_id, barcode, accession_no, floor_no, section_name, shelf_no, row_no, status) VALUES (12, 3, 'BC-12', 'ACC-12', '2', 'IT', 'A1', '3', 'Lost')");
    }

    public function testRecordsAndReadsReverseChronologicalHistoryWithActorName(): void
    {
        $repository = new PdoAuditEventRepository($this->pdo);
        $repository->record(new AuditEvent(
            12,
            7,
            AuditEventType::STATUS_CHANGED,
            'Available',
            'Lost',
            'Missing after shelf check',
            null,
            null,
            null,
            ['barcode' => 'BC-12', 'title' => 'Clean Code'],
            new DateTimeImmutable('2026-08-31 14:32:00'),
        ));
        $repository->record(new AuditEvent(
            12,
            7,
            AuditEventType::ACQUIRED,
            null,
            'Available',
            null,
            null,
            null,
            null,
            ['barcode' => 'BC-12', 'title' => 'Clean Code'],
            new DateTimeImmutable('2026-08-01 09:00:00'),
        ));

        $result = $repository->findCopyHistory('BC-12');

        self::assertNotNull($result);
        self::assertSame('Lost', $result->copy['status']);
        self::assertCount(2, $result->events);
        self::assertSame('Status changed', $result->events[0]['label']);
        self::assertSame('Ada Lovelace', $result->events[0]['actor']);
        self::assertSame('Missing after shelf check', $result->events[0]['reason']);
    }
}
