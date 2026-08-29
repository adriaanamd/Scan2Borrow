<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\DTO\BulkBorrowRequest;
use DateTimeImmutable;

interface BorrowingRepositoryInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function findBook(string $barcode): ?array;

    public function activeApprovedCount(int $userId): int;

    public function createLoan(
        int $userId,
        int $bookId,
        string $transactionCode,
        DateTimeImmutable $dueDate,
        string $status,
        string $approvalStatus,
    ): int;

    /** @return array{transaction_code: string, copy_count: int, title_count: int} */
    public function createBulkTransaction(
        BulkBorrowRequest $request,
        DateTimeImmutable $dueDate,
        string $transactionCode,
        string $status,
        string $approvalStatus,
    ): array;
}
