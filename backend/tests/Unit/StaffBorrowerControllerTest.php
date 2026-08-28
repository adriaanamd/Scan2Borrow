<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Services\CsrfService;
use App\Application\Services\SessionService;
use App\Domain\Auth\Principal;
use App\Domain\Auth\Role;
use App\Http\Controllers\StaffController;
use App\Http\Requests\ServerRequest;
use App\Infrastructure\Persistence\StaffRepositoryInterface;
use App\Infrastructure\Session\SessionStoreInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StaffBorrowerControllerTest extends TestCase
{
    public function testBorrowerDetailsRequireStaffSession(): void
    {
        /** @var StaffRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(StaffRepositoryInterface::class);
        $repository->expects(self::never())->method('borrowerDetails');
        $store = new InMemoryStaffSessionStore();
        $controller = new StaffController(
            new SessionService($store),
            $repository,
            new CsrfService($store),
        );

        self::assertSame(401, $controller->borrowerDetails(ServerRequestFactory::get('/api/staff/borrower?id=2'))->statusCode());
    }

    public function testBorrowerDetailsReturnProtectedStaffPayload(): void
    {
        /** @var StaffRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(StaffRepositoryInterface::class);
        $repository->expects(self::once())->method('borrowerDetails')->with(2)->willReturn([
            'borrower' => ['id' => 2, 'name' => 'Grace Hopper'],
            'summary' => ['active' => 1],
            'history' => [],
        ]);
        $store = new InMemoryStaffSessionStore();
        $sessions = new SessionService($store);
        $sessions->login(new Principal(1, Role::LIBRARIAN));
        $controller = new StaffController($sessions, $repository, new CsrfService($store));

        $response = $controller->borrowerDetails(ServerRequestFactory::get('/api/staff/borrower?id=2'));

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('Grace Hopper', $response->toString());
    }
}

final class ServerRequestFactory
{
    public static function get(string $uri): ServerRequest
    {
        $_SERVER = [
            'REQUEST_URI' => $uri,
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '/scan2borrow/backend/public/index.php',
        ];
        $GLOBALS['_POST'] = [];

        return ServerRequest::fromGlobals();
    }
}

final class InMemoryStaffSessionStore implements SessionStoreInterface
{
    /** @var array<string, mixed> */
    private array $values = [];

    public function start(): void
    {
    }

    public function regenerate(): void
    {
    }

    public function id(): string
    {
        return 'test-session';
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
}
