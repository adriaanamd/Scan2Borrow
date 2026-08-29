<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class BulkBorrowItem
{
    /** @param list<string> $barcodes */
    public function __construct(
        public int $titleId,
        public int $quantity,
        public array $barcodes = [],
    ) {
    }
}
