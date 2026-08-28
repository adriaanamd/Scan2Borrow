<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Application\Services\CsrfService;
use App\Application\Services\SessionService;
use App\Domain\Auth\AuthorizationPolicy;
use App\Domain\Auth\Principal;
use App\Domain\Auth\Role;
use App\Domain\Auth\SessionIdentity;
use App\Infrastructure\Session\SessionStoreInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AuthContractsTest extends TestCase
{
    public function testSessionLoginRegeneratesIdentityAndKeepsPrincipal(): void
    {
        $store = new InMemorySessionStore();
        $service = new SessionService($store);
        $principal = new Principal(27, Role::STUDENT);

        $service->login($principal);
        $identity = $service->current();

        self::assertInstanceOf(SessionIdentity::class, $identity);
        self::assertSame(27, $identity->userId());
        self::assertSame(Role::STUDENT, $identity->role());
        self::assertSame(1, $store->regenerationCount());
        self::assertSame($store->id(), $identity->sessionId());
    }

    public function testCsrfTokenIsStableAndRejectsInvalidSubmission(): void
    {
        $service = new CsrfService(new InMemorySessionStore());
        $token = $service->token();

        self::assertSame($token, $service->token());
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        $service->assertValid($token);

        $this->expectException(InvalidArgumentException::class);
        $service->assertValid(str_repeat('0', 64));
    }

    public function testAuthorizationPolicyCoversAllCurrentPrincipalKinds(): void
    {
        $policy = new AuthorizationPolicy();

        self::assertTrue($policy->allows(new Principal(1, Role::ADMIN), [Role::ADMIN]));
        self::assertTrue($policy->allows(new Principal(2, Role::LIBRARIAN), [Role::ADMIN, Role::LIBRARIAN]));
        self::assertTrue($policy->allows(new Principal(3, Role::STUDENT), [Role::STUDENT]));
        self::assertTrue($policy->allows(new Principal(4, Role::TEACHER), [Role::TEACHER]));
        self::assertTrue($policy->allows(new Principal(5, Role::GUEST), [], true));
        self::assertFalse($policy->allows(new Principal(6, Role::STUDENT), [Role::TEACHER]));
        self::assertFalse($policy->allows(null, [Role::ADMIN]));
    }
}

final class InMemorySessionStore implements SessionStoreInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $values = [];

    private string $sessionId = 'initial-session';

    private int $regenerations = 0;

    public function start(): void
    {
    }

    public function regenerate(): void
    {
        $this->regenerations++;
        $this->sessionId = 'regenerated-session-' . $this->regenerations;
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

    public function regenerationCount(): int
    {
        return $this->regenerations;
    }
}
