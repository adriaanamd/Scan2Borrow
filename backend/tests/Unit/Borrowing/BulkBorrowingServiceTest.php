<?php

declare(strict_types=1);

namespace Tests\Unit\Borrowing;

use App\Application\DTO\BulkBorrowItem;
use App\Application\DTO\BulkBorrowRequest;
use App\Application\Services\BorrowingService;
use App\Application\Services\ClockInterface;
use App\Domain\Auth\Role;
use App\Domain\Borrowing\BorrowingPolicy;
use App\Infrastructure\Persistence\BorrowingRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class BulkBorrowingServiceTest extends TestCase
{
    public function testRejectsAnEmptyCart(): void
    {
        $result = $this->service(new BulkBorrowingFakeRepository())->bulkBorrow(
            new BulkBorrowRequest(7, Role::STUDENT, []),
        );

        self::assertFalse($result->successful());
        self::assertSame('Please add at least one book to your borrowing cart.', $result->message());
    }

    public function testRejectsARequestThatWouldExceedRemainingCopyLimit(): void
    {
        $repository = new BulkBorrowingFakeRepository();
        $repository->activeApproved = 2;

        $result = $this->service($repository)->bulkBorrow(new BulkBorrowRequest(
            7,
            Role::STUDENT,
            [new BulkBorrowItem(12, 2)],
        ));

        self::assertFalse($result->successful());
        self::assertSame('You can borrow only 1 more book(s) right now.', $result->message());
    }

    public function testCreatesOneTransactionForMultipleCopies(): void
    {
        $repository = new BulkBorrowingFakeRepository();

        $result = $this->service($repository)->bulkBorrow(new BulkBorrowRequest(
            7,
            Role::STUDENT,
            [new BulkBorrowItem(12, 2), new BulkBorrowItem(18, 1)],
        ));

        self::assertTrue($result->successful());
        self::assertMatchesRegularExpression('/^S2B-20260828-[A-F0-9]{6}$/', (string) $result->transactionCode());
        self::assertSame(3, $result->copyCount());
        self::assertSame(2, count($repository->createdItems));
        self::assertSame(3, $repository->createdTotal);
    }

    public function testTeacherDueDateRulesRemainAppliedToBulkRequests(): void
    {
        $result = $this->service(new BulkBorrowingFakeRepository())->bulkBorrow(new BulkBorrowRequest(
            7,
            Role::TEACHER,
            [new BulkBorrowItem(12, 2)],
            '2026-10-01',
        ));

        self::assertFalse($result->successful());
        self::assertSame('Preferred return date cannot exceed 30 days.', $result->message());
    }

    private function service(BorrowingRepositoryInterface $repository): BorrowingService
    {
        return new BorrowingService(
            $repository,
            new BorrowingPolicy(3, 7, 30, true),
            new BulkBorrowingFixedClock(),
        );
    }
}

final class BulkBorrowingFixedClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-28 10:00:00');
    }
}

final class BulkBorrowingFakeRepository implements BorrowingRepositoryInterface
{
    public int $activeApproved = 0;

    /** @var list<BulkBorrowItem> */
    public array $createdItems = [];

    public int $createdTotal = 0;

    public function findBook(string $barcode): ?array
    {
        return null;
    }

    public function activeApprovedCount(int $userId): int
    {
        return $this->activeApproved;
    }

    public function createLoan(int $userId, int $bookId, string $transactionCode, DateTimeImmutable $dueDate, string $status, string $approvalStatus): int
    {
        return 0;
    }

    /** @return array{transaction_code: string, copy_count: int, title_count: int} */
    public function createBulkTransaction(BulkBorrowRequest $request, DateTimeImmutable $dueDate, string $transactionCode, string $status, string $approvalStatus): array
    {
        $this->createdItems = $request->items;
        $this->createdTotal = array_sum(array_map(static fn (BulkBorrowItem $item): int => $item->quantity, $request->items));

        return ['transaction_code' => $transactionCode, 'copy_count' => $this->createdTotal, 'title_count' => count($this->createdItems)];
    }
}
