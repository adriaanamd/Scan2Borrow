<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Services\CsrfService;
use App\Application\Services\PhotoStorageInterface;
use App\Application\Services\ProfileChangeRequestService;
use App\Application\Services\SessionService;
use App\Application\Validators\ProfileChangeRequestValidator;
use App\Domain\Auth\Principal;
use App\Domain\Auth\Role;
use App\Http\Controllers\StaffController;
use App\Http\Requests\ServerRequest;
use App\Infrastructure\Persistence\ProfileChangeNotificationInterface;
use App\Infrastructure\Persistence\ProfileChangeRequestRepositoryInterface;
use App\Infrastructure\Persistence\StaffRepositoryInterface;
use App\Infrastructure\Session\SessionStoreInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StaffProfileChangeControllerTest extends TestCase
{
    private const CSRF = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testLibrariansCannotReadOrDecideProfileChanges(): void
    {
        $store = new StaffProfileSessionStore(self::CSRF);
        (new SessionService($store))->login(new Principal(3, Role::LIBRARIAN));
        $controller = $this->controller($store);

        self::assertSame(401, $controller->profileChangeRequests($this->request('GET', '/api/admin/profile-change-requests'))->statusCode());
        self::assertSame(401, $controller->profileChangeRequestAction($this->request('POST', '/api/admin/profile-change-request-action', ['csrf' => self::CSRF, 'action' => 'approve', 'request_id' => '41']))->statusCode());
    }

    public function testAdminCanListAndDecideAProfileChange(): void
    {
        $store = new StaffProfileSessionStore(self::CSRF);
        (new SessionService($store))->login(new Principal(1, Role::ADMIN));
        $controller = $this->controller($store);

        self::assertSame(200, $controller->profileChangeRequests($this->request('GET', '/api/admin/profile-change-requests'))->statusCode());
        $response = $controller->profileChangeRequestAction($this->request('POST', '/api/admin/profile-change-request-action', [
            'csrf' => self::CSRF, 'action' => 'approve', 'request_id' => '41', 'review_note' => 'Verified.',
        ]));

        self::assertSame(200, $response->statusCode());
    }

    private function controller(StaffProfileSessionStore $store): StaffController
    {
        /** @var StaffRepositoryInterface&MockObject $staff */
        $staff = $this->createMock(StaffRepositoryInterface::class);
        $service = new ProfileChangeRequestService(new StaffProfileRepository(), new StaffProfileNotifications(), new ProfileChangeRequestValidator(), new StaffProfilePhotoStorage());

        return new StaffController(new SessionService($store), $staff, new CsrfService($store), profileChanges: $service);
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

final class StaffProfileRepository implements ProfileChangeRequestRepositoryInterface
{
    public function profile(int $userId): ?array { return null; }
    public function pendingForUser(int $userId): ?array { return null; }
    public function create(int $userId, array $originalValues, array $requestedValues, ?string $originalPhoto, ?string $requestedPhoto): int { return 0; }
    public function pendingRequests(): array { return [['id' => 41, 'user_name' => 'Ada Lovelace', 'requested_values' => ['firstname' => 'Grace']]]; }
    public function decide(int $requestId, int $reviewerId, string $decision, string $reviewNote): ?array { return ['id' => $requestId, 'user_id' => 7, 'status' => 'approved', 'user_name' => 'Ada Lovelace']; }
}

final class StaffProfileNotifications implements ProfileChangeNotificationInterface
{
    public function notifyAdministrators(int $requestId, string $message): void {}
    public function notifyBorrower(int $userId, int $requestId, string $title, string $message): void {}
}

final class StaffProfilePhotoStorage implements PhotoStorageInterface
{
    public function store(string $data, string $filenameSeed): ?string { return null; }
}

final class StaffProfileSessionStore implements SessionStoreInterface
{
    /** @var array<string, mixed> */
    private array $values;
    public function __construct(string $csrf) { $this->values = ['scan2borrow.csrf' => $csrf]; }
    public function start(): void {}
    public function regenerate(): void {}
    public function id(): string { return 'staff-profile-test'; }
    public function get(string $key): mixed { return $this->values[$key] ?? null; }
    public function set(string $key, mixed $value): void { $this->values[$key] = $value; }
    public function remove(string $key): void { unset($this->values[$key]); }
}
