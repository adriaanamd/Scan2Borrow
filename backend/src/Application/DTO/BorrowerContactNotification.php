<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class BorrowerContactNotification
{
    /** @param list<array{title: string, quantity: int}> $titles */
    public function __construct(
        public string $recipientType,
        public int $recipientId,
        public string $eventType,
        public string $borrowingReference,
        public array $titles,
        public ?string $dueDate = null,
    ) {
    }
}
