<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class BookCopyMutationRequest
{
    public function __construct(
        public int $copyId,
        public string $barcode = '',
        public string $accessionNo = '',
        public string $floorNo = '',
        public string $sectionName = '',
        public string $shelfNo = '',
        public string $rowNo = '',
        public string $dueDate = '',
        public string $returnDate = '',
        public string $status = 'Available',
        public string $reason = '',
        public int $actorId = 0,
    ) {
    }
}
