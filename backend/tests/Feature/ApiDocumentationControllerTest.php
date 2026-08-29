<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Services\SessionService;
use App\Domain\Auth\Principal;
use App\Domain\Auth\Role;
use App\Http\Controllers\ApiDocumentationController;
use App\Http\Documentation\ApiEndpointCatalog;
use App\Http\Requests\ServerRequest;
use App\Infrastructure\Session\SessionStoreInterface;
use PHPUnit\Framework\TestCase;

final class ApiDocumentationControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_POST);
        parent::tearDown();
    }

    public function testDocumentationApiRejectsUnauthenticatedRequests(): void
    {
        $response = $this->controller(new ApiDocumentationSessionStore())->index($this->request());

        self::assertSame(401, $response->statusCode());
    }

    public function testDocumentationApiRejectsLibrarians(): void
    {
        $store = new ApiDocumentationSessionStore();
        $store->setPrincipal(new Principal(2, Role::LIBRARIAN));

        $response = $this->controller($store)->index($this->request());

        self::assertSame(403, $response->statusCode());
    }

    public function testDocumentationApiReturnsTheFullCatalogToAdmins(): void
    {
        $store = new ApiDocumentationSessionStore();
        $store->setPrincipal(new Principal(1, Role::ADMIN));

        $response = $this->controller($store)->index($this->request());
        /** @var array{ok: bool, data: array{endpoints: list<array<string, mixed>>}} $payload */
        $payload = json_decode($response->toString(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->statusCode());
        self::assertTrue($payload['ok']);
        self::assertCount(51, $payload['data']['endpoints']);
    }

    private function controller(ApiDocumentationSessionStore $store): ApiDocumentationController
    {
        return new ApiDocumentationController(new SessionService($store), new ApiEndpointCatalog());
    }

    private function request(): ServerRequest
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/admin/api-docs';

        return ServerRequest::fromGlobals();
    }
}

final class ApiDocumentationSessionStore implements SessionStoreInterface
{
    /** @var array<string, mixed> */
    private array $values = [];

    public function start(): void {}
    public function regenerate(): void {}
    public function id(): string { return 'api-documentation-test'; }
    public function get(string $key): mixed { return $this->values[$key] ?? null; }
    public function set(string $key, mixed $value): void { $this->values[$key] = $value; }
    public function remove(string $key): void { unset($this->values[$key]); }

    public function setPrincipal(Principal $principal): void
    {
        (new SessionService($this))->login($principal);
    }
}
