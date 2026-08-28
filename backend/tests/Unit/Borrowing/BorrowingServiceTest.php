<?php

declare(strict_types=1);

namespace Tests\Unit\Borrowing;

use App\Application\DTO\BorrowRequest;
use App\Application\Services\BorrowingService;
use App\Application\Services\ClockInterface;
use App\Domain\Auth\Role;
use App\Domain\Borrowing\BorrowingPolicy;
use App\Domain\Borrowing\BorrowingResult;
use App\Domain\Book\BookStatus;
use App\Infrastructure\Persistence\BorrowingRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class BorrowingServiceTest extends TestCase
{
    public function testRejectsUnavailableBookAndMaximumCapacity(): void
    {
        $repository = new FakeBorrowingRepository();
        $repository->status = BookStatus::BORROWED;
        $service = $this->service($repository);

        self::assertSame(
            'Sorry, Clean Code is currently borrowed.',
            $service->borrow(new BorrowRequest(1, Role::STUDENT, 'BK-1'))->message(),
        );

        $repository->status = BookStatus::AVAILABLE;
        $repository->activeApproved = 3;
        self::assertSame(
            'You already have the maximum of 3 borrowed books.',
            $service->borrow(new BorrowRequest(1, Role::STUDENT, 'BK-1'))->message(),
        );
    }

    public function testTeacherDateIsBoundedAndBorrowerUsesSevenDayDefault(): void
    {
        $repository = new FakeBorrowingRepository();
        $service = $this->service($repository);

        self::assertSame(
            'Preferred return date cannot be in the past.',
            $service->borrow(new BorrowRequest(1, Role::TEACHER, 'BK-1', '2026-08-27'))->message(),
        );
        self::assertSame(
            'Preferred return date cannot exceed 30 days.',
            $service->borrow(new BorrowRequest(1, Role::TEACHER, 'BK-1', '2026-10-01'))->message(),
        );

        $result = $service->borrow(new BorrowRequest(1, Role::STUDENT, 'BK-1'));
        self::assertTrue($result->successful());
        self::assertSame('Pending', $repository->createdStatus);
        self::assertSame('pending', $repository->createdApproval);
        self::assertSame('2026-09-04', $repository->dueDate?->format('Y-m-d'));
    }

    private function service(FakeBorrowingRepository $repository): BorrowingService
    {
        return new BorrowingService(
            $repository,
            new BorrowingPolicy(3, 7, 30, true),
            new BorrowingFixedClock(),
        );
    }
}

final class BorrowingFixedClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-28 10:00:00');
    }
}

final class FakeBorrowingRepository implements BorrowingRepositoryInterface
{
    public BookStatus $status = BookStatus::AVAILABLE;

    public int $activeApproved = 0;

    public string $createdStatus = '';

    public string $createdApproval = '';

    public ?DateTimeImmutable $dueDate = null;

    public function findBook(string $barcode): ?array
    {
        if ($this->status === BookStatus::AVAILABLE || $this->status === BookStatus::BORROWED) {
            return ['id' => 1, 'title' => 'Clean Code', 'status' => $this->status->value];
        }

        return null;
    }

    public function activeApprovedCount(int $userId): int
    {
        return $this->activeApproved;
    }

    public function createLoan(int $userId, int $bookId, string $transactionCode, DateTimeImmutable $dueDate, string $status, string $approvalStatus): int
    {
        $this->createdStatus = $status;
        $this->createdApproval = $approvalStatus;
        $this->dueDate = $dueDate;

        return 1;
    }
}
