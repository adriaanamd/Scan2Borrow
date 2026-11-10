<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\DTO\BulkBorrowRequest;
use App\Application\Services\BorrowingService;
use App\Application\Services\ClockInterface;
use App\Application\Services\CsrfService;
use App\Application\Services\ReturnService;
use App\Application\Services\SessionService;
use App\Domain\Auth\Role;
use App\Domain\Auth\SessionIdentity;
use App\Domain\Borrowing\BorrowingPolicy;
use App\Domain\Borrowing\LoanRecord;
use App\Http\Controllers\BorrowerController;
use App\Http\Requests\ServerRequest;
use App\Http\Routing\BorrowerRouteTable;
use App\Infrastructure\Persistence\BorrowerPortalRepositoryInterface;
use App\Infrastructure\Persistence\BorrowingRepositoryInterface;
use App\Infrastructure\Persistence\ReturnRepositoryInterface;
use App\Infrastructure\Session\SessionStoreInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class BorrowerNotificationContractTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SERVER['SCRIPT_NAME'], $GLOBALS['_POST']);
    }

    public function testBothBorrowerRoutesReturnUserScopedNotificationShape(): void
    {
        $portal = $this->createMock(BorrowerPortalRepositoryInterface::class);
        $portal->expects(self::exactly(2))->method('notifications')->with(7)->willReturn([
            'notifications' => [[
                'id' => 12,
                'type' => 'borrow_approved',
                'title' => 'Borrowing request approved',
                'message' => 'Your borrowing request was approved. You borrowed: "Clean Code".',
                'related_id' => 44,
                'is_read' => 0,
                'created_at' => '2026-11-10 12:00:00',
            ]],
            'unread_count' => 1,
        ]);
        $controller = $this->controller(new SessionIdentity(7, Role::STUDENT, 'notification-session'), $portal);
        $routes = (new BorrowerRouteTable())->routes($controller);

        foreach (['/api/student/notifications', '/api/teacher/notifications'] as $path) {
            $request = $this->request('GET', $path);
            $route = $this->route($routes, $request);
            $response = $route->respond($request);

            self::assertSame(200, $response->statusCode());
            self::assertStringContainsString('"unread_count":1', $response->toString());
            self::assertStringNotContainsString('user_id', $response->toString());
        }
    }

    public function testReadActionRequiresCsrfAndPositiveIdWhileReadAllUsesAuthenticatedUser(): void
    {
        $storeIdentity = new SessionIdentity(7, Role::STUDENT, 'notification-session');
        $portal = $this->createMock(BorrowerPortalRepositoryInterface::class);
        $portal->expects(self::once())->method('markAllNotificationsRead')->with(7);
        $portal->expects(self::once())->method('notifications')->with(7)->willReturn([
            'notifications' => [],
            'unread_count' => 0,
        ]);
        $controller = $this->controller($storeIdentity, $portal);

        $invalid = $this->request('POST', '/api/student/notifications', ['csrf' => str_repeat('a', 64), 'action' => 'read', 'notification_id' => '0']);
        self::assertSame(422, $controller->notificationAction($invalid)->statusCode());

        $request = $this->request('POST', '/api/student/notifications', ['csrf' => str_repeat('a', 64), 'action' => 'read_all']);
        $response = $controller->notificationAction($request);

        self::assertSame(200, $response->statusCode());
    }

    public function testNotificationRoutesRejectStaffSessions(): void
    {
        $controller = $this->controller(new SessionIdentity(8, Role::ADMIN, 'notification-session'), $this->createMock(BorrowerPortalRepositoryInterface::class));

        $response = $controller->notifications($this->request('GET', '/api/student/notifications'));

        self::assertSame(401, $response->statusCode());
    }

    private function controller(SessionIdentity $identity, BorrowerPortalRepositoryInterface $portal): BorrowerController
    {
        return new BorrowerController(
            new SessionService(new NotificationSessionStore($identity, str_repeat('a', 64))),
            new CsrfService(new NotificationSessionStore($identity, str_repeat('a', 64))),
            new BorrowingService(new NotificationActionRepository(), new BorrowingPolicy(3, 7, 30, true), new NotificationClock()),
            new ReturnService(new NotificationActionRepository()),
            $portal,
        );
    }

    /** @param array<string, mixed> $body */
    private function request(string $method, string $path, array $body = []): ServerRequest
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $path;
        $_SERVER['SCRIPT_NAME'] = '';
        $GLOBALS['_POST'] = $body;

        return ServerRequest::fromGlobals();
    }

    /** @param list<\App\Http\Routing\Route> $routes */
    private function route(array $routes, ServerRequest $request): \App\Http\Routing\Route
    {
        foreach ($routes as $route) {
            if ($route->matches($request)) {
                return $route;
            }
        }

        self::fail('No borrower notification route matched ' . $request->method() . ' ' . $request->path());
    }
}

final class NotificationClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-10 12:00:00');
    }
}

final class NotificationActionRepository implements BorrowingRepositoryInterface, ReturnRepositoryInterface
{
    public function findBook(string $barcode): ?array { return null; }
    public function activeApprovedCount(int $userId): int { return 0; }
    public function createLoan(int $userId, int $bookId, string $transactionCode, DateTimeImmutable $dueDate, string $status, string $approvalStatus): int { return 0; }
    public function createBulkTransaction(BulkBorrowRequest $request, DateTimeImmutable $dueDate, string $transactionCode, string $status, string $approvalStatus): array { return ['transaction_code' => $transactionCode, 'copy_count' => 0, 'title_count' => 0, 'titles' => []]; }
    public function activeByTransaction(int $userId, string $transactionCode): array { return []; }
    public function activeByBook(int $userId, int $bookId): ?LoanRecord { return null; }
    public function findBookByBarcode(string $barcode): ?array { return null; }
    public function requestReturn(int $loanId): bool { return true; }
    public function titleIdForBook(int $bookId): ?int { return null; }
}

final class NotificationSessionStore implements SessionStoreInterface
{
    public function __construct(private readonly SessionIdentity $identity, private readonly string $csrf)
    {
    }

    public function start(): void {}
    public function regenerate(): void {}
    public function id(): string { return 'notification-session'; }
    public function get(string $key): mixed { return $key === 'scan2borrow.identity' ? $this->identity : ($key === 'scan2borrow.csrf' ? $this->csrf : null); }
    public function set(string $key, mixed $value): void {}
    public function remove(string $key): void {}
}
