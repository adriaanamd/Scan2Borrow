<?php

declare(strict_types=1);

namespace App\Application\StaffCirculation;

use DateTimeImmutable;

interface StaffCirculationRepositoryInterface
{
    public function staffCanCirculate(int $staffId): bool;

    /** @return array{ok: true, borrower: array<string, mixed>, active_loans: list<array<string, mixed>>}|null */
    public function findBorrowerByBarcode(string $barcode): ?array;

    /**
     * @param list<string> $bookBarcodes
     * @return array<string, mixed>
     */
    public function createDirectBorrow(
        int $borrowerId,
        int $staffId,
        array $bookBarcodes,
        DateTimeImmutable $dueDate,
        string $transactionCode,
    ): array;

    /**
     * @param list<int> $loanIds
     * @param list<string> $copyBarcodes
     * @return array<string, mixed>
     */
    public function processDirectReturns(
        int $borrowerId,
        int $staffId,
        array $loanIds,
        array $copyBarcodes,
        DateTimeImmutable $now,
        float $finePerDay,
    ): array;
}
