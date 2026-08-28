<?php

declare(strict_types=1);

namespace App\Domain\Book;

final readonly class BookSearchResult
{
    /**
     * @param list<array<string, mixed>> $books
     */
    public function __construct(
        private array $books,
        private int $total,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function books(): array
    {
        return $this->books;
    }

    public function total(): int
    {
        return $this->total;
    }
}
