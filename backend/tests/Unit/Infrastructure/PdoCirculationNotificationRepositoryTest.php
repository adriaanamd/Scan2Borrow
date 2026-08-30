<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Infrastructure\Persistence\PdoCirculationNotificationRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoCirculationNotificationRepositoryTest extends TestCase
{
    public function testBorrowerNotificationIsStoredWithItsRelatedReservation(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, type TEXT NOT NULL, title TEXT NOT NULL, message TEXT NOT NULL, related_id INTEGER, is_read INTEGER NOT NULL DEFAULT 0, created_at TEXT)');

        (new PdoCirculationNotificationRepository($pdo))->notifyBorrower(7, 'hold_available', 'Hold ready', 'Claim it today.', 12);

        $row = $pdo->query('SELECT user_id, type, title, message, related_id FROM notifications')->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('7', (string) $row['user_id']);
        self::assertSame('hold_available', $row['type']);
        self::assertSame('12', (string) $row['related_id']);
    }
}
