<?php

declare(strict_types=1);

namespace Tests\Unit\Borrowing;

use App\Application\Services\ClockInterface;
use App\Application\Services\ReturnService;
use App\Domain\Borrowing\LoanRecord;
use App\Infrastructure\Persistence\ReturnRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ReturnServiceTest extends TestCase
{
    public function testEmptyAndUnknownInputsPreserveMessages(): void
    {
        $service = $this->service(new FakeReturnRepository());
        self::assertSame('Please enter a book barcode or transaction code.', $service->return(1, '')->message());
        self::assertSame('No book or transaction found for: UNKNOWN', $service->return(1, 'UNKNOWN')->message());
    }

    public function testSingleReturnCalculatesFineAndRestoresBookAvailability(): void
    {
        $repository = new FakeReturnRepository();
        $repository->book = ['id' => 4, 'title' => 'Clean Code'];
        $repository->loan = new LoanRecord(8, 4, 'S2B-20260820-ABC123', new DateTimeImmutable('2026-08-25'));
        $service = $this->service($repository);

        $result = $service->return(1, 'BK-0002');

        self::assertTrue($result->successful());
        self::assertSame('You returned "Clean Code". It was 2 day(s) overdue. Fine: ₱10.00.', $result->message());
        self::assertSame(8, $repository->completedLoanId);
        self::assertSame(4, $repository->availableBookId);
        self::assertSame(10.0, $repository->fine);
    }

    private function service(FakeReturnRepository $repository): ReturnService
    {
        return new ReturnService($repository, new ReturnFixedClock(), 5.0);
    }
}

final class ReturnFixedClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-28 10:00:00');
    }
}

final class FakeReturnRepository implements ReturnRepositoryInterface
{
    /** @var array<string, mixed>|null */
    public ?array $book = null;

    public ?LoanRecord $loan = null;

    public ?int $completedLoanId = null;

    public ?int $availableBookId = null;

    public ?float $fine = null;

    /** @return list<LoanRecord> */
    public function activeByTransaction(int $userId, string $transactionCode): array
    {
        return [];
    }

    /** @return array<string, mixed>|null */
    public function findBookByBarcode(string $barcode): ?array
    {
        return $this->book;
    }

    public function activeByBook(int $userId, int $bookId): ?LoanRecord
    {
        return $this->loan;
    }

    public function completeReturn(int $loanId, int $bookId, float $fine): void
    {
        $this->completedLoanId = $loanId;
        $this->availableBookId = $bookId;
        $this->fine = $fine;
    }
}
