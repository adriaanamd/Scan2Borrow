<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Book\BookSearchCriteria;
use App\Domain\Book\BookSearchResult;

interface BookRepositoryInterface
{
    public function search(BookSearchCriteria $criteria): BookSearchResult;
}
