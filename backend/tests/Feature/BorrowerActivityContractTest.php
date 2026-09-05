<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\DTO\BulkBorrowRequest;
use App\Application\Services\ClockInterface;
use App\Application\Services\BorrowingService;
use App\Application\Services\CsrfService;
use App\Application\Services\ReturnService;
use App\Application\Services\SessionService;
use App\Domain\Auth\Role;
use App\Domain\Auth\SessionIdentity;
use App\Http\Controllers\BorrowerController;
use App\Http\Requests\ServerRequest;
use App\Infrastructure\Persistence\BorrowerPortalRepositoryInterface;
use App\Domain\Borrowing\BorrowingPolicy;
use App\Domain\Borrowing\LoanRecord;
use App\Infrastructure\Persistence\BorrowingRepositoryInterface;
use App\Infrastructure\Persistence\ReturnRepositoryInterface;
use App\Infrastructure\Session\SessionStoreInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class BorrowerActivityContractTest extends TestCase
{
    public function testActivityReturnsAuthenticatedRepositoryTimelineWithoutUsingClientUserId(): void
    {
        $store = new ActivitySessionStore(new SessionIdentity(7, Role::STUDENT, 'activity-session'));
        $portal = $this->createMock(BorrowerPortalRepositoryInterface::class);
        $expected = [[
            'id' => 'audit:1',
            'type' => 'login',
            'label' => 'Signed in',
            'details' => 'Signed in to Scan2Borrow',
            'title' => '',
            'transaction_code' => '',
            'status' => '',
            'occurred_at' => '2026-08-06 14:00:00',
        ]];
        $portal->expects(self::once())->method('activity')->with(7)->willReturn($expected);
        $controller = $this->controller($store, $portal);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/student/activity?user_id=99';
        $response = $controller->activity(ServerRequest::fromGlobals());

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('"activity":[{"id":"audit:1"', $response->toString());
        self::assertStringNotContainsString('99', $response->toString());
    }

    public function testActivityRejectsStaffSessions(): void
    {
        $store = new ActivitySessionStore(new SessionIdentity(8, Role::ADMIN, 'activity-session'));
        $portal = $this->createMock(BorrowerPortalRepositoryInterface::class);
        $response = $this->controller($store, $portal)->activity(ServerRequest::fromGlobals());

        self::assertSame(401, $response->statusCode());
    }

    private function controller(SessionStoreInterface $store, BorrowerPortalRepositoryInterface $portal): BorrowerController
    {
        return new BorrowerController(
            new SessionService($store),
            new CsrfService($store),
            new BorrowingService(new ActivityActionRepository(), new BorrowingPolicy(3, 7, 30, true), new ActivityClock()),
            new ReturnService(new ActivityActionRepository(), new ActivityClock(), 20.0),
            $portal,
        );
    }
}

final class ActivityClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-05 10:00:00');
    }
}

final class ActivityActionRepository implements BorrowingRepositoryInterface, ReturnRepositoryInterface
{
    public function findBook(string $barcode): ?array { return null; }
    public function activeApprovedCount(int $userId): int { return 0; }
    public function createLoan(int $userId, int $bookId, string $transactionCode, DateTimeImmutable $dueDate, string $status, string $approvalStatus): int { return 0; }
    public function createBulkTransaction(BulkBorrowRequest $request, DateTimeImmutable $dueDate, string $transactionCode, string $status, string $approvalStatus): array { return ['transaction_code' => $transactionCode, 'copy_count' => 0, 'title_count' => 0]; }
    public function activeByTransaction(int $userId, string $transactionCode): array { return []; }
    public function findBookByBarcode(string $barcode): ?array { return null; }
    public function activeByBook(int $userId, int $bookId): ?LoanRecord { return null; }
    public function titleIdForBook(int $bookId): ?int { return null; }
    public function completeReturn(int $loanId, int $bookId, float $fine): void {}
}

final class ActivitySessionStore implements SessionStoreInterface
{
    public function __construct(private readonly ?SessionIdentity $identity)
    {
    }

    public function start(): void {}

    public function regenerate(): void {}

    public function id(): string
    {
        return 'activity-session';
    }

    public function get(string $key): mixed
    {
        return $key === 'scan2borrow.identity' ? $this->identity : null;
    }

    public function set(string $key, mixed $value): void {}

    public function remove(string $key): void {}
}
