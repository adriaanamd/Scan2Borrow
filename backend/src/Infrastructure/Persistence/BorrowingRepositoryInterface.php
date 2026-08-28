<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

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
}
