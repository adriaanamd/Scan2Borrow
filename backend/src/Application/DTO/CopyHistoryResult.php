<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class CopyHistoryResult
{
    /** @param array<string, mixed> $copy @param list<array<string, mixed>> $events */
    public function __construct(public array $copy, public array $events)
    {
    }

    /** @return array{copy: array<string, mixed>, events: list<array<string, mixed>>} */
    public function toArray(): array
    {
        return ['copy' => $this->copy, 'events' => $this->events];
    }
}
