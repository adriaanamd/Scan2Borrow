<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Services\CsrfService;
use App\Application\Services\ReservationService;
use App\Application\Services\SessionService;
use App\Domain\Auth\Principal;
use App\Domain\Auth\Role;
use App\Domain\Reservation\HoldRecord;
use App\Http\Controllers\StaffReservationController;
use App\Http\Requests\ServerRequest;
use App\Infrastructure\Persistence\HoldRepositoryInterface;
use App\Infrastructure\Session\SessionStoreInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class StaffReservationControllerTest extends TestCase
{
    public const CSRF = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $GLOBALS['_POST']);
        parent::tearDown();
    }

    public function testQueueRequiresStaffAuthentication(): void
    {
        $store = new StaffReservationSessionStore();
        $controller = $this->controller($store, new StaffReservationHoldRepository());

        self::assertSame(401, $controller->index($this->request('GET', '/api/staff/reservations'))->statusCode());
    }

    public function testStaffCanReviewAndFulfilClaimedReservation(): void
    {
        $store = new StaffReservationSessionStore();
        (new SessionService($store))->login(new Principal(2, Role::LIBRARIAN));
        $repository = new StaffReservationHoldRepository();
        $repository->holds = [HoldRecord::fromRow([
            'id' => 12, 'user_id' => 7, 'title_id' => 4, 'title' => 'Clean Code', 'status' => 'claimed',
        ])];
        $controller = $this->controller($store, $repository);

        $list = $controller->index($this->request('GET', '/api/staff/reservations?status=claimed'));
        $fulfil = $controller->action($this->request('POST', '/api/staff/reservations/action', [
            'csrf' => self::CSRF, 'hold_id' => '12', 'action' => 'fulfil',
        ]));

        self::assertSame(200, $list->statusCode());
        self::assertStringContainsString('Clean Code', $list->toString());
        self::assertSame(200, $fulfil->statusCode());
    }

    private function controller(StaffReservationSessionStore $store, HoldRepositoryInterface $repository): StaffReservationController
    {
        return new StaffReservationController(new SessionService($store), new CsrfService($store), new ReservationService($repository));
    }

    /** @param array<string, mixed> $body */
    private function request(string $method, string $uri, array $body = []): ServerRequest
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $GLOBALS['_POST'] = $body;

        return ServerRequest::fromGlobals();
    }
}

final class StaffReservationHoldRepository implements HoldRepositoryInterface
{
    /** @var list<HoldRecord> */
    public array $holds = [];
    public function find(int $holdId): ?HoldRecord { return $this->holds[0] ?? null; }
    public function findActiveForUserTitle(int $userId, int $titleId): ?HoldRecord { return null; }
    /** @return list<HoldRecord> */
    public function listForUser(int $userId): array { return []; }
    public function join(int $userId, int $titleId): HoldRecord { return $this->holds[0]; }
    public function cancel(int $holdId, int $userId): bool { return false; }
    public function claim(int $holdId, int $userId): ?HoldRecord { return null; }
    public function fulfil(int $holdId, int $staffId): bool { return true; }
    public function nextEligibleQueued(int $titleId): ?HoldRecord { return null; }
    public function offer(int $holdId, int $copyId, DateTimeImmutable $offeredAt, DateTimeImmutable $expiresAt): bool { return false; }
    public function expire(int $holdId, DateTimeImmutable $expiredAt): bool { return false; }
    /** @return list<HoldRecord> */
    public function listStaff(string $status): array { return $this->holds; }
    /** @return list<int> */
    public function expireOffers(DateTimeImmutable $now): array { return []; }
}

final class StaffReservationSessionStore implements SessionStoreInterface
{
    /** @var array<string, mixed> */
    private array $values = ['scan2borrow.csrf' => StaffReservationControllerTest::CSRF];
    public function start(): void {}
    public function regenerate(): void {}
    public function id(): string { return 'staff-reservation-test'; }
    public function get(string $key): mixed { return $this->values[$key] ?? null; }
    public function set(string $key, mixed $value): void { $this->values[$key] = $value; }
    public function remove(string $key): void { unset($this->values[$key]); }
}
