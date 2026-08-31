<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Services\CsrfService;
use App\Application\Services\PhotoStorageInterface;
use App\Application\Services\ProfileChangeRequestService;
use App\Application\Validators\ProfileChangeRequestValidator;
use App\Domain\Auth\Principal;
use App\Domain\Auth\Role;
use App\Http\Controllers\ProfileChangeRequestController;
use App\Http\Requests\ServerRequest;
use App\Infrastructure\Persistence\ProfileChangeNotificationInterface;
use App\Infrastructure\Persistence\ProfileChangeRequestRepositoryInterface;
use App\Infrastructure\Session\SessionStoreInterface;
use PHPUnit\Framework\TestCase;

final class ProfileChangeRequestControllerTest extends TestCase
{
    private const CSRF = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $GLOBALS['_POST']);
        parent::tearDown();
    }

    public function testStudentCanReadOnlyTheirStudentSettingsEndpoint(): void
    {
        $store = new ProfileControllerSessionStore(self::CSRF);
        (new \App\Application\Services\SessionService($store))->login(new Principal(7, Role::STUDENT));
        $repository = new ProfileControllerRepository();
        $controller = $this->controller($store, $repository);

        $response = $controller->show($this->request('GET', '/api/student/settings'));

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('STU-7', $response->toString());
        self::assertSame(401, $controller->show($this->request('GET', '/api/teacher/settings'))->statusCode());
    }

    public function testBorrowerSubmitRequiresCsrfAndCanCreateRequest(): void
    {
        $store = new ProfileControllerSessionStore(self::CSRF);
        (new \App\Application\Services\SessionService($store))->login(new Principal(7, Role::STUDENT));
        $repository = new ProfileControllerRepository();
        $controller = $this->controller($store, $repository);

        self::assertSame(419, $controller->submit($this->request('POST', '/api/student/settings', ['firstname' => 'Grace']))->statusCode());
        $response = $controller->submit($this->request('POST', '/api/student/settings', ['csrf' => self::CSRF, 'firstname' => 'Grace']));

        self::assertSame(200, $response->statusCode());
        self::assertSame(41, $repository->createdId);
    }

    private function controller(ProfileControllerSessionStore $store, ProfileControllerRepository $repository): ProfileChangeRequestController
    {
        $service = new ProfileChangeRequestService(
            $repository,
            new ProfileControllerNotifications(),
            new ProfileChangeRequestValidator(),
            new ProfileControllerPhotoStorage(),
        );

        return new ProfileChangeRequestController(new \App\Application\Services\SessionService($store), new CsrfService($store), $service);
    }

    /** @param array<string, mixed> $body */
    private function request(string $method, string $uri, array $body): ServerRequest
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $GLOBALS['_POST'] = $body;

        return ServerRequest::fromGlobals();
    }
}

final class ProfileControllerRepository implements ProfileChangeRequestRepositoryInterface
{
    public int $createdId = 0;

    public function profile(int $userId): ?array
    {
        return $userId === 7 ? [
            'id' => 7, 'barcode' => 'STU-7', 'firstname' => 'Ada', 'middlename' => '', 'lastname' => 'Lovelace',
            'email' => '', 'contact_no' => '', 'course' => 'Math', 'year_level' => '4', 'department' => 'Science',
            'position' => 'Student', 'photo' => null, 'role' => 'student', 'status' => 'active',
        ] : null;
    }

    public function pendingForUser(int $userId): ?array { return null; }

    public function create(int $userId, array $originalValues, array $requestedValues, ?string $originalPhoto, ?string $requestedPhoto): int
    {
        $this->createdId = 41;
        return $this->createdId;
    }

    public function pendingRequests(): array { return []; }

    public function decide(int $requestId, int $reviewerId, string $decision, string $reviewNote): ?array { return null; }
}

final class ProfileControllerNotifications implements ProfileChangeNotificationInterface
{
    public function notifyAdministrators(int $requestId, string $message): void {}
    public function notifyBorrower(int $userId, int $requestId, string $title, string $message): void {}
}

final class ProfileControllerPhotoStorage implements PhotoStorageInterface
{
    public function store(string $data, string $filenameSeed): ?string { return null; }
}

final class ProfileControllerSessionStore implements SessionStoreInterface
{
    /** @var array<string, mixed> */
    private array $values;

    public function __construct(string $csrf) { $this->values = ['scan2borrow.csrf' => $csrf]; }
    public function start(): void {}
    public function regenerate(): void {}
    public function id(): string { return 'profile-controller-test'; }
    public function get(string $key): mixed { return $this->values[$key] ?? null; }
    public function set(string $key, mixed $value): void { $this->values[$key] = $value; }
    public function remove(string $key): void { unset($this->values[$key]); }
}
