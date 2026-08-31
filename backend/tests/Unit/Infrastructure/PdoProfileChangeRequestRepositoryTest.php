<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Infrastructure\Persistence\PdoProfileChangeRequestRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PdoProfileChangeRequestRepositoryTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, barcode TEXT, firstname TEXT, middlename TEXT, lastname TEXT, email TEXT, contact_no TEXT, course TEXT, year_level TEXT, department TEXT, position TEXT, photo TEXT, role TEXT, status TEXT)');
        $this->pdo->exec('CREATE TABLE profile_change_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, status TEXT, original_values TEXT, requested_values TEXT, original_photo TEXT, requested_photo TEXT, review_note TEXT, requested_at TEXT, reviewed_at TEXT, reviewed_by INTEGER)');
        $this->pdo->exec("INSERT INTO users VALUES (7, 'STU-7', 'Ada', '', 'Lovelace', 'ada@example.test', '0917', 'Math', '4', 'Science', 'Student', 'uploads/ada.jpg', 'student', 'active')");
    }

    public function testProfileReturnsOnlyTheAuthenticatedUserProfile(): void
    {
        $profile = (new PdoProfileChangeRequestRepository($this->pdo))->profile(7);

        self::assertNotNull($profile);
        self::assertSame('STU-7', $profile['barcode']);
        self::assertSame('Ada', $profile['firstname']);
        self::assertSame('uploads/ada.jpg', $profile['photo']);
        self::assertSame('student', $profile['role']);
        self::assertNull((new PdoProfileChangeRequestRepository($this->pdo))->profile(999));
    }

    public function testPendingRequestDecodesStoredMaps(): void
    {
        $this->pdo->exec("INSERT INTO profile_change_requests (user_id, status, original_values, requested_values, original_photo, requested_photo, requested_at) VALUES (7, 'pending', '{\"firstname\":\"Ada\"}', '{\"firstname\":\"Grace\"}', 'uploads/ada.jpg', 'uploads/grace.jpg', '2026-08-31 14:32:00')");

        $request = (new PdoProfileChangeRequestRepository($this->pdo))->pendingForUser(7);

        self::assertNotNull($request);
        self::assertSame(1, $request['id']);
        self::assertSame(['firstname' => 'Ada'], $request['original_values']);
        self::assertSame(['firstname' => 'Grace'], $request['requested_values']);
        self::assertSame('uploads/grace.jpg', $request['requested_photo']);
        self::assertSame('2026-08-31 14:32:00', $request['requested_at']);
    }

    public function testCreateStoresSnapshotsAndReturnsRequestId(): void
    {
        $repository = new PdoProfileChangeRequestRepository($this->pdo);

        $id = $repository->create(
            7,
            ['firstname' => 'Ada', 'email' => 'ada@example.test'],
            ['firstname' => 'Grace', 'email' => 'grace@example.test'],
            'uploads/ada.jpg',
            'uploads/grace.jpg',
        );

        self::assertSame(1, $id);
        $row = $this->pdo->query('SELECT user_id, status, original_values, requested_values, original_photo, requested_photo FROM profile_change_requests')->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('pending', $row['status']);
        self::assertSame('{"firstname":"Ada","email":"ada@example.test"}', $row['original_values']);
        self::assertSame('uploads/grace.jpg', $row['requested_photo']);
    }

    public function testCreateRejectsASecondPendingRequest(): void
    {
        $repository = new PdoProfileChangeRequestRepository($this->pdo);
        $repository->create(7, ['firstname' => 'Ada'], ['firstname' => 'Grace'], null, null);

        $this->expectException(RuntimeException::class);
        $repository->create(7, ['firstname' => 'Ada'], ['firstname' => 'Marie'], null, null);
    }
}
