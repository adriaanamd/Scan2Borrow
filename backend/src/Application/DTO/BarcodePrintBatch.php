<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class BarcodePrintBatch
{
    /**
     * @param list<array<string, mixed>> $labels
     */
    public function __construct(
        public int $id,
        public string $token,
        public int $titleId,
        public string $title,
        public string $createdAt,
        public array $labels,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'batch_token' => $this->token,
            'title_id' => $this->titleId,
            'title' => $this->title,
            'created_at' => $this->createdAt,
            'labels' => $this->labels,
            'label_count' => count($this->labels),
        ];
    }
}
