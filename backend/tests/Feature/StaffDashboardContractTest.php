<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Services\CsrfService;
use App\Application\Services\SessionService;
use App\Domain\Auth\Principal;
use App\Domain\Auth\Role;
use App\Http\Controllers\StaffController;
use App\Http\Requests\ServerRequest;
use App\Infrastructure\Persistence\StaffRepositoryInterface;
use App\Infrastructure\Session\SessionStoreInterface;
use PHPUnit\Framework\TestCase;

final class StaffDashboardContractTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_POST);
        parent::tearDown();
    }

    public function testUnauthenticatedDashboardApiRemainsProtected(): void
    {
        $store = new StaffDashboardSessionStore();
        $response = $this->controller($store, $this->createMock(StaffRepositoryInterface::class))->dashboard($this->request());

        self::assertSame(401, $response->statusCode());
        self::assertStringContainsString('Staff authentication required.', $response->toString());
    }

    public function testAuthenticatedDashboardPreservesLegacyAndOverviewKeys(): void
    {
        $store = new StaffDashboardSessionStore();
        $store->setPrincipal(new Principal(1, Role::ADMIN));
        $overview = [
            'borrowing_activity' => [['month' => '2026-08', 'label' => 'Aug', 'count' => 2]],
            'loan_status' => ['available' => 4, 'borrowed' => 2, 'overdue' => 1, 'pending' => 1],
            'top_borrowers' => [['id' => 7, 'name' => 'Grace Hopper', 'barcode' => 'STU-1', 'borrowing_count' => 4]],
        ];
        $repository = new class($overview) implements StaffRepositoryInterface {
            /** @param array<string, mixed> $overview */
            public function __construct(private readonly array $overview) {}
            public function dashboard(): array { return ['stats' => [], 'recent' => [], 'pending' => [], 'overview' => $this->overview]; }
            public function borrowers(string $search): array { return []; }
            public function borrowerDetails(int $userId): ?array { return null; }
            public function updateBorrowerPhoto(int $userId, string $photoPath): void {}
            public function overdue(): array { return []; }
            public function report(string $type, string $from, string $to): array { return []; }
            public function guestRequests(): array { return []; }
            public function staffAccounts(): array { return []; }
            public function borrowerCandidates(string $search): array { return []; }
            public function pendingBorrowings(): array { return []; }
            public function notifications(int $staffId, string $type): array { return []; }
            public function markNotificationViewed(int $notificationId, int $staffId, string $type): void {}
            public function approveBorrowing(int $borrowingId, int $staffId): void {}
            public function rejectBorrowing(int $borrowingId, int $staffId): void {}
            public function promote(int $userId, string $role, string $password): void {}
            public function resetPassword(int $userId, string $password): void {}
            public function demote(int $userId): void {}
            public function toggleStatus(int $userId): void {}
        };

        $response = $this->controller($store, $repository)->dashboard($this->request());
        $payload = json_decode($response->toString(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->statusCode());
        self::assertTrue($payload['ok']);
        self::assertArrayHasKey('stats', $payload['data']);
        self::assertArrayHasKey('recent', $payload['data']);
        self::assertArrayHasKey('pending', $payload['data']);
        self::assertSame($overview, $payload['data']['overview']);
    }

    private function controller(StaffDashboardSessionStore $store, StaffRepositoryInterface $repository): StaffController
    {
        return new StaffController(
            new SessionService($store),
            $repository,
            new CsrfService($store),
        );
    }

    private function request(): ServerRequest
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/staff/dashboard';

        return ServerRequest::fromGlobals();
    }
}

final class StaffDashboardSessionStore implements SessionStoreInterface
{
    /** @var array<string, mixed> */
    private array $values = [];

    public function start(): void {}
    public function regenerate(): void {}
    public function id(): string { return 'staff-dashboard-test'; }
    public function get(string $key): mixed { return $this->values[$key] ?? null; }
    public function set(string $key, mixed $value): void { $this->values[$key] = $value; }
    public function remove(string $key): void { unset($this->values[$key]); }

    public function setPrincipal(Principal $principal): void
    {
        (new SessionService($this))->login($principal);
    }
}
