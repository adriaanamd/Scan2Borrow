<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\DTO\RenewalResult;
use App\Application\Services\CsrfService;
use App\Application\Services\RenewalApprovalService;
use App\Application\Services\RenewalEligibilityInterface;
use App\Application\Services\RenewalEligibilityResult;
use App\Application\Services\RenewalService;
use App\Application\Services\SessionService;
use App\Domain\Auth\Principal;
use App\Domain\Auth\Role;
use App\Domain\Renewal\RenewalLoanSnapshot;
use App\Domain\Renewal\RenewalRecord;
use App\Http\Controllers\RenewalController;
use App\Http\Controllers\StaffRenewalController;
use App\Http\Requests\ServerRequest;
use App\Infrastructure\Persistence\CirculationNotificationRepositoryInterface;
use App\Infrastructure\Persistence\RenewalRepositoryInterface;
use App\Infrastructure\Session\SessionStoreInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RenewalControllerTest extends TestCase
{
    public const CSRF = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $GLOBALS['_POST']);
        parent::tearDown();
    }

    public function testBorrowerCanSubmitAndListRenewalRequests(): void
    {
        $store = new RenewalControllerSessionStore();
        (new SessionService($store))->login(new Principal(7, Role::STUDENT));
        $record = RenewalRecord::fromRow(['id' => 12, 'loan_id' => 88, 'user_id' => 7, 'title' => 'Clean Code', 'status' => 'pending', 'original_due_date' => '2026-08-30', 'requested_due_date' => '2026-09-06']);
        $repository = new RenewalControllerRepository($record);
        $eligibility = new class($record) implements RenewalEligibilityInterface {
            public function __construct(private readonly RenewalRecord $record) {}
            public function check(int $userId, int $loanId): RenewalEligibilityResult
            {
                return RenewalEligibilityResult::allowed(new RenewalLoanSnapshot($loanId, $userId, 4, $this->record->title(), $this->record->originalDueDate()), $this->record->requestedDueDate());
            }
        };
        $controller = new RenewalController(new SessionService($store), new CsrfService($store), new RenewalService($eligibility, $repository));

        $request = $controller->create($this->request('POST', '/api/student/renewals', ['csrf' => self::CSRF, 'loan_id' => '88', 'reason' => 'Project deadline']));
        $list = $controller->list($this->request('GET', '/api/student/renewals'));

        self::assertSame(200, $request->statusCode());
        self::assertSame(200, $list->statusCode());
        self::assertStringContainsString('Clean Code', $list->toString());
    }

    public function testLibrarianCanReviewAndApproveRenewal(): void
    {
        $store = new RenewalControllerSessionStore();
        (new SessionService($store))->login(new Principal(2, Role::LIBRARIAN));
        $record = RenewalRecord::fromRow(['id' => 12, 'loan_id' => 88, 'user_id' => 7, 'title' => 'Clean Code', 'status' => 'approved', 'original_due_date' => '2026-08-30', 'requested_due_date' => '2026-09-06']);
        $repository = new RenewalControllerRepository($record);
        $notifications = $this->createMock(CirculationNotificationRepositoryInterface::class);
        $notifications->expects(self::once())->method('notifyBorrower');
        $approval = new RenewalApprovalService($repository, $notifications, new class implements \App\Application\Services\ClockInterface { public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-08-30 12:00:00'); } });
        $controller = new StaffRenewalController(new SessionService($store), new CsrfService($store), $repository, $approval);

        $list = $controller->index($this->request('GET', '/api/staff/renewals'));
        $action = $controller->action($this->request('POST', '/api/staff/renewals/action', ['csrf' => self::CSRF, 'renewal_id' => '12', 'action' => 'approve']));

        self::assertSame(200, $list->statusCode());
        self::assertSame(200, $action->statusCode());
    }

    private function request(string $method, string $uri, array $body = []): ServerRequest
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $GLOBALS['_POST'] = $body;
        return ServerRequest::fromGlobals();
    }
}

final class RenewalControllerRepository implements RenewalRepositoryInterface
{
    public function __construct(private RenewalRecord $record) {}
    public function find(int $renewalId): ?RenewalRecord { return $this->record; }
    public function listForUser(int $userId): array { return [$this->record]; }
    public function listPending(): array { return [$this->record]; }
    public function hasPendingForLoan(int $loanId, int $userId): bool { return false; }
    public function hasApprovedForLoan(int $loanId): bool { return false; }
    public function create(int $loanId, int $userId, DateTimeImmutable $originalDueDate, DateTimeImmutable $requestedDueDate, string $reason): RenewalRecord { return $this->record; }
    public function approve(int $renewalId, int $staffId, string $note, DateTimeImmutable $decidedAt): ?RenewalRecord { return $this->record; }
    public function reject(int $renewalId, int $staffId, string $note, DateTimeImmutable $decidedAt): ?RenewalRecord { return $this->record; }
    public function cancel(int $renewalId, int $userId, DateTimeImmutable $cancelledAt): bool { return true; }
}

final class RenewalControllerSessionStore implements SessionStoreInterface
{
    /** @var array<string, mixed> */
    private array $values = ['scan2borrow.csrf' => RenewalControllerTest::CSRF];
    public function start(): void {}
    public function regenerate(): void {}
    public function id(): string { return 'renewal-controller-test'; }
    public function get(string $key): mixed { return $this->values[$key] ?? null; }
    public function set(string $key, mixed $value): void { $this->values[$key] = $value; }
    public function remove(string $key): void { unset($this->values[$key]); }
}
