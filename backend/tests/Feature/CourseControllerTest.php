<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Services\CourseService;
use App\Application\Services\CsrfService;
use App\Domain\Auth\Principal;
use App\Domain\Auth\Role;
use App\Domain\Course\Course;
use App\Http\Controllers\CourseController;
use App\Http\Requests\ServerRequest;
use App\Infrastructure\Persistence\CourseRepositoryInterface;
use App\Infrastructure\Session\SessionStoreInterface;
use App\Application\Services\SessionService;
use PHPUnit\Framework\TestCase;

final class CourseControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_POST);
        parent::tearDown();
    }

    public function testPublicCatalogReturnsCourses(): void
    {
        $store = new CourseControllerSessionStore();
        $response = $this->controller($store)->index($this->request('GET', '/api/courses'));
        $payload = json_decode($response->toString(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->statusCode());
        self::assertTrue($payload['ok']);
        self::assertSame('BSIT', $payload['data']['courses'][0]['name']);
    }

    public function testAdminCatalogRequiresAnAuthenticatedAdministrator(): void
    {
        $store = new CourseControllerSessionStore();
        $response = $this->controller($store)->adminIndex($this->request('GET', '/api/admin/courses'));

        self::assertSame(401, $response->statusCode());
    }

    public function testAdminCanCreateACourseWithCsrf(): void
    {
        $store = new CourseControllerSessionStore();
        $store->setPrincipal(new Principal(1, Role::ADMIN));
        $csrf = new CsrfService($store);
        $_POST = ['name' => '  BSA  ', 'csrf' => $csrf->token()];

        $response = $this->controller($store)->store($this->request('POST', '/api/admin/courses'));
        $payload = json_decode($response->toString(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(201, $response->statusCode());
        self::assertSame('BSA', $payload['data']['course']['name']);
    }

    public function testAdminCreateRejectsInvalidCsrf(): void
    {
        $store = new CourseControllerSessionStore();
        $store->setPrincipal(new Principal(1, Role::ADMIN));
        $_POST = ['name' => 'BSA', 'csrf' => 'invalid'];

        $response = $this->controller($store)->store($this->request('POST', '/api/admin/courses'));

        self::assertSame(419, $response->statusCode());
    }

    private function controller(CourseControllerSessionStore $store): CourseController
    {
        return new CourseController(
            new SessionService($store),
            new CsrfService($store),
            new CourseService(new CourseControllerRepository()),
        );
    }

    private function request(string $method, string $path): ServerRequest
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $path;

        return ServerRequest::fromGlobals();
    }
}

final class CourseControllerRepository implements CourseRepositoryInterface
{
    /** @return list<Course> */
    public function all(): array
    {
        return [new Course(1, 'BSIT', '2026-11-05 00:00:00')];
    }

    public function existsByName(string $name): bool
    {
        return false;
    }

    public function create(string $name): Course
    {
        return new Course(2, $name, '2026-11-05 00:00:00');
    }
}

final class CourseControllerSessionStore implements SessionStoreInterface
{
    /** @var array<string, mixed> */
    private array $values = [];

    public function start(): void {}
    public function regenerate(): void {}
    public function id(): string { return 'course-controller-test'; }
    public function get(string $key): mixed { return $this->values[$key] ?? null; }
    public function set(string $key, mixed $value): void { $this->values[$key] = $value; }
    public function remove(string $key): void { unset($this->values[$key]); }

    public function setPrincipal(Principal $principal): void
    {
        (new SessionService($this))->login($principal);
    }
}
