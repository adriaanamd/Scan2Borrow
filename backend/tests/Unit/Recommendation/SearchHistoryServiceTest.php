<?php

declare(strict_types=1);

namespace Tests\Unit\Recommendation;

use App\Application\Services\SearchHistoryService;
use App\Infrastructure\Persistence\SearchHistoryRepositoryInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SearchHistoryServiceTest extends TestCase
{
    public function testRecordsTrimmedCollapsedSearchText(): void
    {
        $repository = new RecordingSearchHistoryRepository();

        (new SearchHistoryService($repository))->record(7, '  php   testing  ');

        self::assertSame([7, 'php testing'], $repository->recorded);
    }

    public function testRejectsInvalidUserAndSearchValues(): void
    {
        $service = new SearchHistoryService(new RecordingSearchHistoryRepository());

        foreach ([[0, 'php'], [7, '   '], [7, str_repeat('x', 256)]] as [$userId, $query]) {
            try {
                $service->record($userId, $query);
                self::fail('Expected invalid search input to be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }
}

final class RecordingSearchHistoryRepository implements SearchHistoryRepositoryInterface
{
    /** @var array{int, string}|null */
    public ?array $recorded = null;

    public function record(int $userId, string $query): void
    {
        $this->recorded = [$userId, $query];
    }

    public function recentQueries(int $userId, int $limit): array
    {
        return [];
    }
}
