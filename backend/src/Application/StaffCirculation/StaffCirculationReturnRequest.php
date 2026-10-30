<?php

declare(strict_types=1);

namespace App\Application\StaffCirculation;

final readonly class StaffCirculationReturnRequest
{
    /** @param list<int|string> $loanIds @param list<string> $copyBarcodes */
    public function __construct(
        public int $borrowerId,
        public array $loanIds = [],
        public array $copyBarcodes = [],
    ) {
    }
}
