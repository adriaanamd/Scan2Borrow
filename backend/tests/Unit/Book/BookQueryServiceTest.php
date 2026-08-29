<?php

declare(strict_types=1);

namespace Tests\Unit\Book;

use App\Application\Services\BookQueryService;
use App\Domain\Book\BookSearchCriteria;
use App\Domain\Book\BookStatus;
use App\Infrastructure\Persistence\BookRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class BookQueryServiceTest extends TestCase
{
    public function testPreservesBookStatusesAndNormalizesListFilters(): void
    {
        self::assertSame(BookStatus::AVAILABLE, BookStatus::from('Available'));
        self::assertSame(BookStatus::BORROWED, BookStatus::from('Borrowed'));
        self::assertSame(BookStatus::RESERVED, BookStatus::from('Reserved'));

        $criteria = BookSearchCriteria::fromArray([
            'search' => '  clean code ',
            'status' => 'Borrowed',
            'archived' => '1',
            'page' => '0',
            'per_page' => '99',
            'sort' => 'title',
            'dir' => 'asc',
        ]);

        self::assertSame('clean code', $criteria->search());
        self::assertSame(BookStatus::BORROWED, $criteria->status());
        self::assertTrue($criteria->archived());
        self::assertSame(1, $criteria->page());
        self::assertSame(50, $criteria->perPage());
        self::assertSame('title', $criteria->sort());
        self::assertSame('ASC', $criteria->direction());
    }

    public function testQueryServicePassesCriteriaToRepository(): void
    {
        $repository = new FakeBookRepository();
        $service = new BookQueryService($repository);
        $criteria = BookSearchCriteria::fromArray(['status' => 'Available']);

        $result = $service->search($criteria);

        self::assertSame($criteria, $repository->criteria);
        self::assertSame(0, $result->total());
    }
}

final class FakeBookRepository implements BookRepositoryInterface
{
    public ?BookSearchCriteria $criteria = null;

    public function search(BookSearchCriteria $criteria): \App\Domain\Book\BookSearchResult
    {
        $this->criteria = $criteria;

        return new \App\Domain\Book\BookSearchResult([], 0);
    }

    public function lookupCopyByBarcode(string $barcode): ?array
    {
        return null;
    }
}
