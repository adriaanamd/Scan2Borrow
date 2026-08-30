<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Domain\Reservation\HoldStatus;
use App\Infrastructure\Persistence\PdoHoldRepository;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoHoldRepositoryReservationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, firstname TEXT, lastname TEXT, status TEXT NOT NULL)');
        $this->pdo->exec('CREATE TABLE book_titles (id INTEGER PRIMARY KEY, title TEXT NOT NULL, author TEXT)');
        $this->pdo->exec('CREATE TABLE book_copies (id INTEGER PRIMARY KEY, title_id INTEGER NOT NULL, barcode TEXT NOT NULL, status TEXT NOT NULL, deleted_at TEXT)');
        $this->pdo->exec(
            "CREATE TABLE reservations (
                id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, title_id INTEGER NOT NULL,
                queue_sequence INTEGER NOT NULL UNIQUE, status TEXT NOT NULL, offered_copy_id INTEGER,
                offered_at TEXT, hold_expires_at TEXT, claimed_at TEXT, fulfilled_at TEXT,
                expired_at TEXT, cancelled_at TEXT, cancelled_by INTEGER, created_at TEXT, updated_at TEXT
            )"
        );
        $this->pdo->exec("INSERT INTO users (id, firstname, lastname, status) VALUES (7, 'Ada', 'Lovelace', 'active'), (8, 'Grace', 'Hopper', 'active')");
        $this->pdo->exec("INSERT INTO book_titles (id, title, author) VALUES (4, 'Clean Code', 'Robert C. Martin')");
        $this->pdo->exec("INSERT INTO book_copies (id, title_id, barcode, status, deleted_at) VALUES (11, 4, 'COPY-11', 'Available', NULL)");
    }

    public function testListForUserHydratesOnlyThatBorrowersReservation(): void
    {
        $this->pdo->exec("INSERT INTO reservations (user_id, title_id, queue_sequence, status, created_at) VALUES (7, 4, 10, 'queued', CURRENT_TIMESTAMP), (8, 4, 11, 'queued', CURRENT_TIMESTAMP)");

        $records = (new PdoHoldRepository($this->pdo))->listForUser(7);

        self::assertCount(1, $records);
        self::assertSame('Clean Code', $records[0]->title());
        self::assertSame(HoldStatus::QUEUED, $records[0]->status());
        self::assertSame(1, $records[0]->queuePosition());
    }

    public function testJoinAllocatesNextQueueSequenceAndHydratesTheRecord(): void
    {
        $repository = new PdoHoldRepository($this->pdo);
        $this->pdo->exec("INSERT INTO reservations (user_id, title_id, queue_sequence, status, created_at) VALUES (8, 4, 10, 'queued', CURRENT_TIMESTAMP)");

        $record = $repository->join(7, 4);

        self::assertSame(11, (int) $this->pdo->query('SELECT queue_sequence FROM reservations WHERE id = ' . $record->id())->fetchColumn());
        self::assertSame(2, $record->queuePosition());
        self::assertSame('Clean Code', $record->title());
    }

    public function testActiveLookupFindsQueuedOfferedOrClaimedButNotExpired(): void
    {
        $this->pdo->exec("INSERT INTO reservations (user_id, title_id, queue_sequence, status, created_at) VALUES (7, 4, 10, 'expired', CURRENT_TIMESTAMP)");

        self::assertNull((new PdoHoldRepository($this->pdo))->findActiveForUserTitle(7, 4));
    }

    public function testOfferAndExpiryUpdateOnlyExpectedState(): void
    {
        $this->pdo->exec("INSERT INTO reservations (user_id, title_id, queue_sequence, status, created_at) VALUES (7, 4, 10, 'queued', CURRENT_TIMESTAMP)");
        $repository = new PdoHoldRepository($this->pdo);
        $offeredAt = new DateTimeImmutable('2026-08-30 10:00:00');

        self::assertTrue($repository->offer(1, 11, $offeredAt, $offeredAt->modify('+24 hours')));
        self::assertSame(HoldStatus::OFFERED, $repository->listForUser(7)[0]->status());
        self::assertTrue($repository->expire(1, $offeredAt->modify('+24 hours')));
        self::assertSame(HoldStatus::EXPIRED, $repository->listForUser(7)[0]->status());
    }
}
