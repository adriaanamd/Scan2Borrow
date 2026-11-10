<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Services\CourseService;
use App\Application\Services\CsrfService;
use App\Application\Services\SessionService;
use App\Application\Services\BookQueryService;
use App\Application\Validators\BookCopyMutationValidator;
use App\Domain\Auth\AuthorizationPolicy;
use App\Domain\Auth\Principal;
use App\Domain\Auth\Role;
use App\Domain\Auth\StaffAccessPolicy;
use App\Domain\Auth\StaffAccessProfile;
use App\Http\Controllers\ApiDocumentationController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BookCopyController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\StaffController;
use App\Http\Documentation\ApiEndpointCatalog;
use App\Http\Middleware\AuthorizationMiddleware;
use App\Http\Middleware\SessionPageAccessAuthorizer;
use App\Http\Requests\ServerRequest;
use App\Http\Routing\PageRouteTable;
use App\Infrastructure\Persistence\BookAdministrationRepositoryInterface;
use App\Infrastructure\Persistence\BookRepositoryInterface;
use App\Infrastructure\Persistence\CourseRepositoryInterface;
use App\Infrastructure\Persistence\StaffRepositoryInterface;
use App\Infrastructure\Session\SessionStoreInterface;
use PHPUnit\Framework\TestCase;

final class LibraryAideAuthorizationTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_POST);
        parent::tearDown();
    }

    public function testLibraryAideCannotReachCriticalAdminEndpoints(): void
    {
        $store = $this->aideStore();
        $sessions = new SessionService($store);
        $access = new StaffAccessPolicy();
        $csrf = new CsrfService($store);

        $staff = new StaffController($sessions, $this->createMock(StaffRepositoryInterface::class), $csrf, access: $access);
        self::assertSame(401, $staff->adminStaff($this->request('GET', '/api/admin/staff'))->statusCode());
        self::assertSame(401, $staff->adminAction($this->request('POST', '/api/admin/staff-action'))->statusCode());
        self::assertSame(401, $staff->profileChangeRequests($this->request('GET', '/api/admin/profile-change-requests'))->statusCode());

        $courses = new CourseController(
            $sessions,
            $csrf,
            new CourseService($this->createMock(CourseRepositoryInterface::class)),
            $access,
        );
        self::assertSame(401, $courses->adminIndex($this->request('GET', '/api/admin/courses'))->statusCode());
        self::assertSame(401, $courses->store($this->request('POST', '/api/admin/courses'))->statusCode());

        $documentation = new ApiDocumentationController($sessions, new ApiEndpointCatalog(), $access);
        self::assertSame(403, $documentation->index($this->request('GET', '/api/admin/api-docs'))->statusCode());

        $books = new BookController(
            $sessions,
            new BookQueryService($this->createMock(BookRepositoryInterface::class)),
            access: $access,
        );
        self::assertSame(401, $books->mutate($this->request('POST', '/api/staff/books', ['action' => 'archive', 'id' => 1]))->statusCode());

        $copies = new BookCopyController(
            $sessions,
            $this->createMock(BookAdministrationRepositoryInterface::class),
            new BookCopyMutationValidator(),
            $csrf,
            $access,
        );
        self::assertSame(401, $copies->mutate($this->request('POST', '/api/staff/book-copies', ['action' => 'delete', 'copy_id' => 1]))->statusCode());
    }

    public function testLibraryAideCanUseOperationalPageRoutesButNotAdminPages(): void
    {
        $store = $this->aideStore();
        $sessions = new SessionService($store);
        $authorization = new AuthorizationPolicy();
        $access = new StaffAccessPolicy();
        $authorizer = new SessionPageAccessAuthorizer(
            $sessions,
            $authorization,
            new AuthorizationMiddleware($authorization, $access),
            $access,
        );
        $table = new PageRouteTable(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend');

        foreach ([
            '/staff/aide/dashboard',
            '/staff/dashboard',
            '/staff/circulation',
            '/staff/students',
            '/staff/books',
            '/staff/reports',
            '/staff/notify',
        ] as $path) {
            self::assertTrue($authorizer->allows($this->request('GET', $path), $table->forPath($path)), $path);
        }

        self::assertFalse($authorizer->allows($this->request('GET', '/admin/staff'), $table->forPath('/admin/staff')));
        self::assertFalse($authorizer->allows($this->request('GET', '/admin/api-docs'), $table->forPath('/admin/api-docs')));
    }

    private function aideStore(): LibraryAideAuthorizationSessionStore
    {
        $store = new LibraryAideAuthorizationSessionStore();
        (new SessionService($store))->login(new Principal(91, Role::ADMIN, StaffAccessProfile::LIBRARY_AIDE));

        return $store;
    }

    /** @param array<string, mixed> $body */
    private function request(string $method, string $uri, array $body = []): ServerRequest
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_POST = $body;

        return ServerRequest::fromGlobals();
    }
}

final class LibraryAideAuthorizationSessionStore implements SessionStoreInterface
{
    /** @var array<string, mixed> */
    private array $values = [];

    public function start(): void {}
    public function regenerate(): void {}
    public function id(): string { return 'library-aide-authorization-test'; }
    public function get(string $key): mixed { return $this->values[$key] ?? null; }
    public function set(string $key, mixed $value): void { $this->values[$key] = $value; }
    public function remove(string $key): void { unset($this->values[$key]); }
}
