<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Infrastructure\Persistence\PdoContactNotificationDeliveryRepository;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoContactNotificationDeliveryRepositoryTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE users (' .
            'id INTEGER PRIMARY KEY, firstname TEXT, lastname TEXT, email TEXT, contact_no TEXT)'
        );
        $this->pdo->exec(
            'CREATE TABLE visitors (' .
            'id INTEGER PRIMARY KEY, firstname TEXT, lastname TEXT, email TEXT, contact_no TEXT)'
        );
        $this->pdo->exec(
            'CREATE TABLE contact_notification_deliveries (' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT, recipient_type TEXT NOT NULL, recipient_id INTEGER NOT NULL, ' .
            'event_type TEXT NOT NULL, borrowing_reference TEXT NOT NULL, channel TEXT NOT NULL, ' .
            'destination TEXT, subject TEXT, message TEXT NOT NULL, status TEXT NOT NULL DEFAULT \'pending\', ' .
            'attempts INTEGER NOT NULL DEFAULT 0, last_error TEXT, sent_at TEXT, created_at TEXT, updated_at TEXT, ' .
            'UNIQUE (recipient_type, recipient_id, event_type, borrowing_reference, channel))'
        );
        $this->pdo->exec("INSERT INTO users (id, firstname, lastname, email, contact_no) VALUES (7, 'Juan', 'Cruz', 'juan@example.com', '09170000001')");
        $this->pdo->exec("INSERT INTO visitors (id, firstname, lastname, email, contact_no) VALUES (9, 'Guest', 'Borrower', NULL, '09170000009')");
    }

    public function testFindsRegisteredUserAndVisitorContacts(): void
    {
        $repository = new PdoContactNotificationDeliveryRepository($this->pdo);

        self::assertSame(
            ['name' => 'Juan Cruz', 'email' => 'juan@example.com', 'phone' => '09170000001'],
            $repository->findRecipient('user', 7),
        );
        self::assertSame(
            ['name' => 'Guest Borrower', 'email' => null, 'phone' => '09170000009'],
            $repository->findRecipient('visitor', 9),
        );
        self::assertNull($repository->findRecipient('user', 404));
    }

    public function testCreatesAnIdempotentDeliveryRow(): void
    {
        $repository = new PdoContactNotificationDeliveryRepository($this->pdo);

        $first = $repository->createDelivery(
            'user',
            7,
            'borrow_requested',
            'S2B-20261110-ABC123',
            'email',
            'juan@example.com',
            'Borrow request received',
            '<p>Clean Code</p>',
            'pending',
        );
        $duplicate = $repository->createDelivery(
            'user',
            7,
            'borrow_requested',
            'S2B-20261110-ABC123',
            'email',
            'juan@example.com',
            'Borrow request received',
            '<p>Clean Code</p>',
            'pending',
        );

        self::assertIsInt($first);
        self::assertNull($duplicate);
        $countStatement = $this->pdo->query('SELECT COUNT(*) FROM contact_notification_deliveries');
        self::assertNotFalse($countStatement);
        self::assertSame(1, (int) $countStatement->fetchColumn());
    }

    public function testMarksDeliverySentAndFailed(): void
    {
        $repository = new PdoContactNotificationDeliveryRepository($this->pdo);
        $sentId = $repository->createDelivery('user', 7, 'borrow_requested', 'REF-1', 'email', 'juan@example.com', 'Subject', 'Body', 'pending');
        $failedId = $repository->createDelivery('visitor', 9, 'borrow_approved', '9', 'sms', '09170000009', null, 'Body', 'pending');
        self::assertIsInt($sentId);
        self::assertIsInt($failedId);

        $repository->markSent($sentId, new DateTimeImmutable('2026-11-10 12:00:00'));
        $repository->markFailed($failedId, 'Provider timed out');

        $rowsStatement = $this->pdo->query('SELECT status, attempts, last_error, sent_at FROM contact_notification_deliveries ORDER BY id');
        self::assertNotFalse($rowsStatement);
        $rows = $rowsStatement->fetchAll(PDO::FETCH_ASSOC);
        self::assertSame('sent', $rows[0]['status']);
        self::assertSame('1', (string) $rows[0]['attempts']);
        self::assertSame('2026-11-10 12:00:00', $rows[0]['sent_at']);
        self::assertSame('failed', $rows[1]['status']);
        self::assertSame('1', (string) $rows[1]['attempts']);
        self::assertSame('Provider timed out', $rows[1]['last_error']);
    }
}
