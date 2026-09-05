<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Services\ClockInterface;
use App\Application\Services\CsrfService;
use App\Application\Services\ReturnApprovalService;
use App\Application\Services\SessionService;
use App\Domain\Auth\Principal;
use App\Domain\Auth\Role;
use App\Domain\Auth\SessionIdentity;
use App\Http\Controllers\StaffController;
use App\Http\Requests\ServerRequest;
use App\Infrastructure\Persistence\ReturnApprovalRepositoryInterface;
use App\Infrastructure\Persistence\StaffRepositoryInterface;
use App\Infrastructure\Session\SessionStoreInterface;
use DateTimeImmutable;
use Closure;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StaffReturnApprovalControllerTest extends TestCase
{
    private const CSRF = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_POST);
        parent::tearDown();
    }

    public function testLibrarianCanReadPendingReturnsAndApproveOne(): void
    {
        $store = new StaffReturnSessionStore(self::CSRF, new Principal(19, Role::LIBRARIAN));
        /** @var ReturnApprovalRepositoryInterface&MockObject $returns */
        $returns = $this->createMock(ReturnApprovalRepositoryInterface::class);
        $pending = [
            'type' => 'borrower_item',
            'id' => 7,
            'due_date' => '2026-08-25',
            'title_id' => 4,
            'copy_id' => 3,
        ];
        $returns->expects(self::once())->method('pending')->willReturn([$pending]);
        $returns->expects(self::once())->method('findPending')->with('borrower_item', 7)->willReturn($pending);
        $returns->expects(self::once())->method('decide')->with(
            'borrower_item', 7, 'approve', 19, 0.0, '', self::isInstanceOf(Closure::class),
        )->willReturn(true);

        $controller = $this->controller($store, $returns);

        self::assertSame(200, $controller->returnApprovals($this->request('GET', '/api/staff/return-approvals'))->statusCode());
        $response = $controller->returnAction($this->request('POST', '/api/staff/return-action', [
            'csrf' => self::CSRF,
            'type' => 'borrower_item',
            'id' => '7',
            'action' => 'approve',
        ]));

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('Return approved.', $response->toString());
    }

    public function testBorrowersCannotReadOrDecideReturns(): void
    {
        $store = new StaffReturnSessionStore(self::CSRF, new Principal(7, Role::STUDENT));
        /** @var ReturnApprovalRepositoryInterface&MockObject $returns */
        $returns = $this->createMock(ReturnApprovalRepositoryInterface::class);
        $controller = $this->controller($store, $returns);

        self::assertSame(401, $controller->returnApprovals($this->request('GET', '/api/staff/return-approvals'))->statusCode());
        self::assertSame(401, $controller->returnAction($this->request('POST', '/api/staff/return-action', [
            'csrf' => self::CSRF,
            'type' => 'borrower_item',
            'id' => '7',
            'action' => 'approve',
        ]))->statusCode());
    }

    public function testReturnDecisionRequiresCsrfAndRejectReason(): void
    {
        $store = new StaffReturnSessionStore(self::CSRF, new Principal(19, Role::LIBRARIAN));
        /** @var ReturnApprovalRepositoryInterface&MockObject $returns */
        $returns = $this->createMock(ReturnApprovalRepositoryInterface::class);
        $controller = $this->controller($store, $returns);

        self::assertSame(419, $controller->returnAction($this->request('POST', '/api/staff/return-action', [
            'csrf' => 'wrong',
            'type' => 'guest',
            'id' => '3',
            'action' => 'approve',
        ]))->statusCode());

        self::assertSame(422, $controller->returnAction($this->request('POST', '/api/staff/return-action', [
            'csrf' => self::CSRF,
            'type' => 'guest',
            'id' => '3',
            'action' => 'reject',
        ]))->statusCode());
    }

    private function controller(StaffReturnSessionStore $store, ReturnApprovalRepositoryInterface $returns): StaffController
    {
        return new StaffController(
            new SessionService($store),
            $this->createMock(StaffRepositoryInterface::class),
            new CsrfService($store),
            returnApprovals: new ReturnApprovalService($returns, new StaffReturnClock(), 20.0),
        );
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

final class StaffReturnClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-25 10:00:00');
    }
}

final class StaffReturnSessionStore implements SessionStoreInterface
{
    /** @var array<string, mixed> */
    private array $values;

    public function __construct(string $csrf, ?Principal $principal)
    {
        $this->values = [
            'scan2borrow.csrf' => $csrf,
            'scan2borrow.identity' => $principal === null
                ? null
                : new SessionIdentity($principal->id(), $principal->role(), 'staff-return-test'),
        ];
    }

    public function start(): void {}
    public function regenerate(): void {}
    public function id(): string { return 'staff-return-test'; }
    public function get(string $key): mixed { return $this->values[$key] ?? null; }
    public function set(string $key, mixed $value): void { $this->values[$key] = $value; }
    public function remove(string $key): void { unset($this->values[$key]); }
}
