<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Book\BookSearchCriteria;
use App\Domain\Book\BookSearchResult;
use App\Infrastructure\Persistence\BookRepositoryInterface;

final class BookQueryService
{
    public function __construct(
        private readonly BookRepositoryInterface $books,
    ) {
    }

    public function search(BookSearchCriteria $criteria): BookSearchResult
    {
        return $this->books->search($criteria);
    }
}
