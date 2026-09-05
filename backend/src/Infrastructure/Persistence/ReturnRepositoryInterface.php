<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Borrowing\LoanRecord;

interface ReturnRepositoryInterface
{
    /**
     * @return list<LoanRecord>
     */
    public function activeByTransaction(int $userId, string $transactionCode): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findBookByBarcode(string $barcode): ?array;

    public function activeByBook(int $userId, int $bookId): ?LoanRecord;

    public function titleIdForBook(int $bookId): ?int;

    public function completeReturn(int $loanId, int $bookId, float $fine): void;

    public function requestReturn(int $loanId): bool;
}
