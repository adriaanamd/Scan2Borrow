<?php

declare(strict_types=1);

namespace App\Application\StaffCirculation;

final readonly class StaffCirculationBorrowRequest
{
    /** @param list<string> $bookBarcodes */
    public function __construct(
        public string $borrowerBarcode,
        public array $bookBarcodes,
        public ?string $requestedDueDate = null,
    ) {
    }
}
