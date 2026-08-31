<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Infrastructure\Persistence\PdoProfileChangeNotificationRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoProfileChangeNotificationRepositoryTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, role TEXT, status TEXT)');
        $this->pdo->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, type TEXT, title TEXT, message TEXT, related_id INTEGER, is_read INTEGER DEFAULT 0)');
        $this->pdo->exec("INSERT INTO users VALUES (1, 'admin', 'active'), (2, 'admin', 'inactive'), (3, 'librarian', 'active'), (4, 'student', 'active')");
    }

    public function testSubmissionNotifiesOnlyActiveAdministrators(): void
    {
        (new PdoProfileChangeNotificationRepository($this->pdo))->notifyAdministrators(41, 'Grace requested a profile change.');

        $rows = $this->pdo->query('SELECT user_id, type, title, message, related_id FROM notifications ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

        self::assertCount(1, $rows);
        self::assertSame('1', (string) $rows[0]['user_id']);
        self::assertSame('profile_change_request', $rows[0]['type']);
        self::assertSame('41', (string) $rows[0]['related_id']);
    }

    public function testDecisionNotifiesTheBorrower(): void
    {
        (new PdoProfileChangeNotificationRepository($this->pdo))->notifyBorrower(4, 41, 'Profile change approved', 'Your changes are now active.');

        $row = $this->pdo->query('SELECT user_id, type, title, message, related_id FROM notifications')->fetch(PDO::FETCH_ASSOC);

        self::assertSame('4', (string) $row['user_id']);
        self::assertSame('profile_change_decision', $row['type']);
        self::assertSame('Profile change approved', $row['title']);
        self::assertSame('41', (string) $row['related_id']);
    }
}
