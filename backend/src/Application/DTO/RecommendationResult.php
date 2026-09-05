<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class RecommendationResult
{
    /** @param list<array<string, mixed>> $books */
    public function __construct(private array $books, private bool $personalized)
    {
    }

    /** @return list<array<string, mixed>> */
    public function books(): array
    {
        return $this->books;
    }

    public function personalized(): bool
    {
        return $this->personalized;
    }
}
