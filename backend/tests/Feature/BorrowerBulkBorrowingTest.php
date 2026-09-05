<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Services\BorrowingService;
use App\Application\Services\ClockInterface;
use App\Application\Services\CsrfService;
use App\Application\Services\ReturnResult;
use App\Application\Services\ReturnService;
use App\Application\Services\SessionService;
use App\Domain\Auth\Role;
use App\Domain\Borrowing\BorrowingPolicy;
use App\Infrastructure\Persistence\BorrowerPortalRepositoryInterface;
use App\Infrastructure\Persistence\BorrowingRepositoryInterface;
use App\Infrastructure\Persistence\ReturnRepositoryInterface;
use App\Infrastructure\Session\SessionStoreInterface;
use App\Http\Controllers\BorrowerController;
use App\Http\Requests\ServerRequest;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class BorrowerBulkBorrowingTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_POST);
        parent::tearDown();
    }

    public function testBorrowerControllerAcceptsMultipleItemsInOneRequest(): void
    {
        $store = new BulkBorrowingSessionStore();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/student/borrow';
        $_POST = [
            'action' => 'borrow',
            'csrf' => str_repeat('a', 64),
            'items' => [
                ['title_id' => '12', 'quantity' => '2'],
                ['title_id' => '18', 'quantity' => '1'],
            ],
        ];

        $controller = new BorrowerController(
            new SessionService($store),
            new CsrfService($store),
            new BorrowingService(new BulkBorrowingControllerRepository(), new BorrowingPolicy(5, 7, 30, true), new BulkBorrowingControllerClock()),
            new ReturnService(new BulkBorrowingControllerRepository()),
            new BulkBorrowingPortalRepository(),
        );

        $response = $controller->change(ServerRequest::fromGlobals());

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('"book_count":3', $response->toString());
    }
}

final class BulkBorrowingSessionStore implements SessionStoreInterface
{
    public function start(): void {}
    public function regenerate(): void {}
    public function id(): string { return 'bulk-test'; }
    public function get(string $key): mixed
    {
        return match ($key) {
            'scan2borrow.identity' => new \App\Domain\Auth\SessionIdentity(7, Role::STUDENT, 'bulk-test'),
            'scan2borrow.csrf' => str_repeat('a', 64),
            default => null,
        };
    }
    public function set(string $key, mixed $value): void {}
    public function remove(string $key): void {}
}

final class BulkBorrowingControllerClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-28 10:00:00');
    }
}

final class BulkBorrowingControllerRepository implements BorrowingRepositoryInterface, ReturnRepositoryInterface
{
    public function findBook(string $barcode): ?array { return null; }
    public function activeApprovedCount(int $userId): int { return 0; }
    public function createLoan(int $userId, int $bookId, string $transactionCode, DateTimeImmutable $dueDate, string $status, string $approvalStatus): int { return 0; }
    public function createBulkTransaction(\App\Application\DTO\BulkBorrowRequest $request, DateTimeImmutable $dueDate, string $transactionCode, string $status, string $approvalStatus): array
    {
        return ['transaction_code' => $transactionCode, 'copy_count' => 3, 'title_count' => 2];
    }
    public function activeByTransaction(int $userId, string $transactionCode): array { return []; }
    public function findBookByBarcode(string $barcode): ?array { return null; }
    public function activeByBook(int $userId, int $bookId): ?\App\Domain\Borrowing\LoanRecord { return null; }
    public function requestReturn(int $loanId): bool { return true; }
    public function titleIdForBook(int $bookId): ?int { return null; }
}

final class BulkBorrowingPortalRepository implements BorrowerPortalRepositoryInterface
{
    public function dashboard(int $userId): array { return []; }
    public function activity(int $userId): array { return []; }
    public function recentActivity(int $userId): array { return []; }
    public function history(int $userId): array { return []; }
    public function receipt(int $userId, string $transactionCode): ?array { return null; }
}
