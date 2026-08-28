<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Application\Services\AuthenticationService;
use App\Domain\Auth\Role;
use App\Domain\Auth\UserAccount;
use App\Infrastructure\Persistence\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class AuthenticationServiceTest extends TestCase
{
    public function testActiveBorrowerLogsInAndReceivesRoleHome(): void
    {
        $repository = new FakeUserRepository();
        $repository->add(new UserAccount(10, '2024001', Role::STUDENT));
        $sessions = new AuthenticationSessionStore();
        $service = new AuthenticationService($repository, $sessions);

        $result = $service->loginBorrower(' 2024001 ');

        self::assertTrue($result->successful());
        self::assertSame('/student/dashboard', $result->redirectPath());
        self::assertSame(10, $sessions->identity()?->userId());
    }

    public function testInactiveBorrowerIsRejectedWithCurrentMessage(): void
    {
        $repository = new FakeUserRepository();
        $repository->add(new UserAccount(11, '2024002', Role::STUDENT, 'inactive'));
        $service = new AuthenticationService($repository, new AuthenticationSessionStore());

        $result = $service->loginBorrower('2024002');

        self::assertFalse($result->successful());
        self::assertSame('This account is inactive. Please see the librarian.', $result->message());
    }

    public function testStaffPasswordAndLockContractsArePreserved(): void
    {
        $repository = new FakeUserRepository();
        $repository->add(new UserAccount(
            12,
            'ADMIN001',
            Role::ADMIN,
            'active',
            password_hash('admin123', PASSWORD_DEFAULT),
        ));
        $service = new AuthenticationService($repository, new AuthenticationSessionStore());

        self::assertSame(
            'Invalid staff password.',
            $service->loginStaff('ADMIN001', 'wrong')->message(),
        );
        self::assertSame(
            'No staff account found for that ID.',
            $service->loginStaff('2024001', 'admin123')->message(),
        );

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $service->loginStaff('ADMIN001', 'wrong');
        }

        self::assertSame(
            'Too many failed attempts. Account locked for 15 minutes.',
            $service->loginStaff('ADMIN001', 'wrong')->message(),
        );
        self::assertSame(
            'Account temporarily locked due to too many failed attempts. Please try again later.',
            $service->loginStaff('ADMIN001', 'admin123')->message(),
        );
    }
}

final class FakeUserRepository implements UserRepositoryInterface
{
    /**
     * @var array<string, UserAccount>
     */
    private array $users = [];

    public function add(UserAccount $user): void
    {
        $this->users[$user->barcode()] = $user;
    }

    public function findByBarcode(string $barcode): ?UserAccount
    {
        return $this->users[$barcode] ?? null;
    }

    public function isLocked(string $barcode): bool
    {
        return isset($this->users[$barcode]) && $this->users[$barcode]->locked();
    }

    public function recordLoginFailure(?int $userId, string $barcode): void
    {
        if (isset($this->users[$barcode])) {
            $this->users[$barcode] = $this->users[$barcode]->withFailedAttempts(
                $this->users[$barcode]->failedAttempts() + 1,
            );
        }
    }

    public function lock(string $barcode, int $minutes): void
    {
        if (isset($this->users[$barcode])) {
            $this->users[$barcode] = $this->users[$barcode]->lockedCopy();
        }
    }

    public function recordLoginSuccess(int $userId, string $barcode): void
    {
    }
}

final class AuthenticationSessionStore implements \App\Infrastructure\Session\SessionStoreInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $values = [];

    private string $sessionId = 'authentication-session';

    public function start(): void
    {
    }

    public function regenerate(): void
    {
        $this->sessionId .= '-regenerated';
    }

    public function id(): string
    {
        return $this->sessionId;
    }

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->values[$key]);
    }

    public function identity(): ?\App\Domain\Auth\SessionIdentity
    {
        $identity = $this->values['scan2borrow.identity'] ?? null;

        return $identity instanceof \App\Domain\Auth\SessionIdentity ? $identity : null;
    }
}
