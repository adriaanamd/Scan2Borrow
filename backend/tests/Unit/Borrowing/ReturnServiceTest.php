<?php

declare(strict_types=1);

namespace Tests\Unit\Borrowing;

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
        self::assertSame('Please enter a book barcode or transaction code.', $service->request(1, '')->message());
        self::assertSame('No book or transaction found for: UNKNOWN', $service->request(1, 'UNKNOWN')->message());
    }

    public function testSingleReturnQueuesReviewWithoutChangingLoanOrAvailability(): void
    {
        $repository = new FakeReturnRepository();
        $repository->book = ['id' => 4, 'title' => 'Clean Code'];
        $repository->loan = new LoanRecord(8, 4, 'S2B-20260820-ABC123', new DateTimeImmutable('2026-08-25'));
        $service = $this->service($repository);

        $result = $service->request(1, 'BK-0002');

        self::assertTrue($result->successful());
        self::assertSame('Return request submitted. Please hand the book to the librarian for verification.', $result->message());
        self::assertSame([8], $repository->requestedLoanIds);
        self::assertNull($repository->completedLoanId);
        self::assertNull($repository->availableBookId);
        self::assertNull($repository->fine);
    }

    public function testTransactionReturnQueuesEveryActiveLoanForReview(): void
    {
        $repository = new FakeReturnRepository();
        $repository->transactionLoans = [
            new LoanRecord(8, 4, 'S2B-20260820-ABC123', new DateTimeImmutable('2026-08-25')),
            new LoanRecord(9, 5, 'S2B-20260820-ABC123', new DateTimeImmutable('2026-08-28')),
        ];
        $service = $this->service($repository);

        $result = $service->request(1, 'S2B-20260820-ABC123');

        self::assertTrue($result->successful());
        self::assertSame('Return request submitted. Please hand the book to the librarian for verification.', $result->message());
        self::assertSame([8, 9], $repository->requestedLoanIds);
        self::assertNull($repository->fine);
    }

    public function testReturnReportsWhenReviewIsAlreadyPending(): void
    {
        $repository = new FakeReturnRepository();
        $repository->book = ['id' => 4, 'title' => 'Clean Code'];
        $repository->loan = new LoanRecord(8, 4, 'S2B-20260820-ABC123', new DateTimeImmutable('2026-08-25'));
        $repository->requestReturnResult = false;

        $result = $this->service($repository)->request(1, 'BK-0002');

        self::assertFalse($result->successful());
        self::assertSame('This return is already awaiting librarian verification.', $result->message());
    }

    private function service(FakeReturnRepository $repository): ReturnService
    {
        return new ReturnService($repository);
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

    /** @var list<LoanRecord> */
    public array $transactionLoans = [];

    /** @var list<int> */
    public array $requestedLoanIds = [];

    public bool $requestReturnResult = true;

    /** @return list<LoanRecord> */
    public function activeByTransaction(int $userId, string $transactionCode): array
    {
        return $this->transactionLoans;
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

    public function titleIdForBook(int $bookId): ?int
    {
        return null;
    }

    public function completeReturn(int $loanId, int $bookId, float $fine): void
    {
        $this->completedLoanId = $loanId;
        $this->availableBookId = $bookId;
        $this->fine = $fine;
    }

    public function requestReturn(int $loanId): bool
    {
        $this->requestedLoanIds[] = $loanId;

        return $this->requestReturnResult;
    }
}
