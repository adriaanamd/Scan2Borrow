<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Services\CsrfService;
use App\Application\DTO\AuthenticationResult;
use App\Application\Services\AuthenticationServiceInterface;
use App\Application\Services\SessionService;
use App\Domain\Auth\Principal;
use App\Domain\Auth\Role;
use App\Http\Controllers\AuthController;
use App\Http\Requests\ServerRequest;
use App\Infrastructure\Session\SessionStoreInterface;
use PHPUnit\Framework\TestCase;

final class AuthControllerTest extends TestCase
{
    private AuthController $controller;

    private AuthControllerSessionStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = new AuthControllerSessionStore();
        $sessions = new SessionService($this->store);
        $this->controller = new AuthController(
            $sessions,
            new CsrfService($this->store),
            new AuthControllerAuthenticationService(),
        );
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/auth/logout';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_POST);

        parent::tearDown();
    }

    public function testSessionEndpointExposesAuthenticatedState(): void
    {
        $this->store->setPrincipal(new Principal(33, Role::TEACHER));

        $response = $this->controller->session(ServerRequest::fromGlobals());

        self::assertSame(200, $response->statusCode());
        self::assertSame(
            [
                'ok' => true,
                'data' => ['authenticated' => true, 'user_id' => 33, 'role' => 'teacher'],
            ],
            json_decode($response->toString(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testLogoutRequiresCsrfAndClearsSession(): void
    {
        $this->store->setPrincipal(new Principal(34, Role::STUDENT));
        $token = (new CsrfService($this->store))->token();
        $_POST = ['csrf' => $token];

        $response = $this->controller->logout(ServerRequest::fromGlobals());

        self::assertSame(200, $response->statusCode());
        self::assertNull((new SessionService($this->store))->current());
    }

    public function testInvalidLogoutTokenDoesNotClearSession(): void
    {
        $this->store->setPrincipal(new Principal(35, Role::ADMIN));
        $_POST = ['csrf' => str_repeat('0', 64)];

        $response = $this->controller->logout(ServerRequest::fromGlobals());

        self::assertSame(419, $response->statusCode());
        self::assertNotNull((new SessionService($this->store))->current());
    }
}

final class AuthControllerSessionStore implements SessionStoreInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $values = [];

    private string $sessionId = 'auth-controller-session';

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

    public function setPrincipal(Principal $principal): void
    {
        (new SessionService($this))->login($principal);
    }
}

final class AuthControllerAuthenticationService implements AuthenticationServiceInterface
{
    public function loginBorrower(string $barcode): AuthenticationResult
    {
        return AuthenticationResult::failure('not used in this test');
    }

    public function loginStaff(string $barcode, string $password): AuthenticationResult
    {
        return AuthenticationResult::failure('not used in this test');
    }
}
