<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class BarcodePrintResult
{
    public function __construct(
        public string $status,
        public ?BarcodePrintBatch $batch,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'printed' => $this->batch !== null,
            'batch' => $this->batch?->toArray(),
        ];
    }
}
