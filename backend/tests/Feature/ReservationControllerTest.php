<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Services\CsrfService;
use App\Application\Services\ReservationService;
use App\Application\Services\SessionService;
use App\Domain\Auth\Principal;
use App\Domain\Auth\Role;
use App\Domain\Reservation\HoldRecord;
use App\Http\Controllers\ReservationController;
use App\Http\Requests\ServerRequest;
use App\Infrastructure\Persistence\HoldRepositoryInterface;
use App\Infrastructure\Session\SessionStoreInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ReservationControllerTest extends TestCase
{
    public const CSRF = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $GLOBALS['_POST']);
        parent::tearDown();
    }

    public function testHoldListRequiresBorrowerAuthentication(): void
    {
        $store = new ReservationControllerSessionStore();
        $controller = $this->controller($store, new ReservationControllerHoldRepository());

        $response = $controller->list($this->request('GET', '/api/student/holds'));

        self::assertSame(401, $response->statusCode());
    }

    public function testBorrowerCanListAndJoinReservationQueue(): void
    {
        $store = new ReservationControllerSessionStore();
        (new SessionService($store))->login(new Principal(7, Role::STUDENT));
        $repository = new ReservationControllerHoldRepository();
        $record = HoldRecord::fromRow([
            'id' => 12,
            'user_id' => 7,
            'title_id' => 4,
            'title' => 'Clean Code',
            'author' => 'Robert C. Martin',
            'status' => 'queued',
            'queue_position' => 2,
        ]);
        $repository->holds = [$record];
        $controller = $this->controller($store, $repository);

        $listResponse = $controller->list($this->request('GET', '/api/student/holds'));
        $joinResponse = $controller->create($this->request('POST', '/api/student/holds', [
            'csrf' => self::CSRF,
            'title_id' => '4',
        ]));

        self::assertSame(200, $listResponse->statusCode());
        self::assertStringContainsString('Clean Code', $listResponse->toString());
        self::assertSame(200, $joinResponse->statusCode());
        self::assertStringContainsString('joined the queue', $joinResponse->toString());
    }

    public function testBorrowerCanClaimAnOfferedHoldAndCancelIt(): void
    {
        $store = new ReservationControllerSessionStore();
        (new SessionService($store))->login(new Principal(7, Role::TEACHER));
        $repository = new ReservationControllerHoldRepository();
        $repository->holds = [HoldRecord::fromRow([
            'id' => 12,
            'user_id' => 7,
            'title_id' => 4,
            'title' => 'Clean Code',
            'status' => 'claimed',
        ])];
        $controller = $this->controller($store, $repository);

        $claimResponse = $controller->action($this->request('POST', '/api/teacher/holds/action', [
            'csrf' => self::CSRF,
            'hold_id' => '12',
            'action' => 'claim',
        ]));
        $cancelResponse = $controller->action($this->request('POST', '/api/teacher/holds/action', [
            'csrf' => self::CSRF,
            'hold_id' => '12',
            'action' => 'cancel',
        ]));

        self::assertSame(200, $claimResponse->statusCode());
        self::assertSame(200, $cancelResponse->statusCode());
    }

    private function controller(ReservationControllerSessionStore $store, HoldRepositoryInterface $repository): ReservationController
    {
        return new ReservationController(
            new SessionService($store),
            new CsrfService($store),
            new ReservationService($repository),
        );
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

final class ReservationControllerHoldRepository implements HoldRepositoryInterface
{
    /** @var list<HoldRecord> */
    public array $holds = [];

    public function find(int $holdId): ?HoldRecord { return $this->holds[0] ?? null; }
    public function findActiveForUserTitle(int $userId, int $titleId): ?HoldRecord { return null; }
    /** @return list<HoldRecord> */
    public function listForUser(int $userId): array { return $this->holds; }
    public function join(int $userId, int $titleId): HoldRecord { return $this->holds[0]; }
    public function cancel(int $holdId, int $userId): bool { return true; }
    public function claim(int $holdId, int $userId): ?HoldRecord { return $this->holds[0] ?? null; }
    public function fulfil(int $holdId, int $staffId): bool { return true; }
    public function nextEligibleQueued(int $titleId): ?HoldRecord { return null; }
    public function offer(int $holdId, int $copyId, DateTimeImmutable $offeredAt, DateTimeImmutable $expiresAt): bool { return false; }
    public function expire(int $holdId, DateTimeImmutable $expiredAt): bool { return false; }
    /** @return list<HoldRecord> */
    public function listStaff(string $status): array { return []; }
    /** @return list<int> */
    public function expireOffers(DateTimeImmutable $now): array { return []; }
}

final class ReservationControllerSessionStore implements SessionStoreInterface
{
    /** @var array<string, mixed> */
    private array $values = ['scan2borrow.csrf' => ReservationControllerTest::CSRF];

    public function start(): void {}
    public function regenerate(): void {}
    public function id(): string { return 'reservation-controller-test'; }
    public function get(string $key): mixed { return $this->values[$key] ?? null; }
    public function set(string $key, mixed $value): void { $this->values[$key] = $value; }
    public function remove(string $key): void { unset($this->values[$key]); }
}
