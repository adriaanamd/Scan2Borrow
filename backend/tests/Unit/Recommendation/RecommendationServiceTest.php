<?php

declare(strict_types=1);

namespace Tests\Unit\Recommendation;

use App\Application\DTO\SearchProfile;
use App\Application\Services\RecommendationService;
use App\Infrastructure\Persistence\RecommendationRepositoryInterface;
use App\Infrastructure\Persistence\SearchHistoryRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class RecommendationServiceTest extends TestCase
{
    public function testUsesTwentyNewestSearchesAndFillsPartialPersonalizedResults(): void
    {
        $history = new RecordingRecommendationHistory(['php testing', 'old gardening', 'ancient history']);
        $catalog = new RecordingRecommendationCatalog([
            ['id' => 9, 'title' => 'PHP Testing'],
        ], [
            ['id' => 9, 'title' => 'PHP Testing'],
            ['id' => 10, 'title' => 'Gardening'],
            ['id' => 11, 'title' => 'New Title'],
        ]);

        $result = (new RecommendationService($history, $catalog))->forBorrower(7);

        self::assertTrue($result->personalized());
        self::assertSame(20, $history->receivedLimit);
        self::assertSame(5, $catalog->receivedRecommendationLimit);
        self::assertSame(4, $catalog->receivedFallbackLimit);
        self::assertGreaterThan(
            $catalog->profile?->weights()['gardening'] ?? 0,
            $catalog->profile?->weights()['php'] ?? 0,
        );
        self::assertSame([9, 10, 11], array_column($result->books(), 'id'));
    }

    public function testUsesNewestEligibleFallbackForEmptyHistory(): void
    {
        $history = new RecordingRecommendationHistory([]);
        $catalog = new RecordingRecommendationCatalog([], [
            ['id' => 20, 'title' => 'New Title'],
        ]);

        $result = (new RecommendationService($history, $catalog))->forBorrower(8);

        self::assertFalse($result->personalized());
        self::assertSame(5, $catalog->receivedFallbackLimit);
        self::assertNull($catalog->profile);
        self::assertSame([20], array_column($result->books(), 'id'));
    }
}

final class RecordingRecommendationHistory implements SearchHistoryRepositoryInterface
{
    public int $receivedLimit = 0;

    /** @param list<string> $queries */
    public function __construct(private readonly array $queries)
    {
    }

    public function record(int $userId, string $query): void
    {
    }

    public function recentQueries(int $userId, int $limit): array
    {
        $this->receivedLimit = $limit;

        return $this->queries;
    }
}

final class RecordingRecommendationCatalog implements RecommendationRepositoryInterface
{
    public ?SearchProfile $profile = null;
    public int $receivedRecommendationLimit = 0;
    public int $receivedFallbackLimit = 0;

    /**
     * @param list<array<string, mixed>> $ranked
     * @param list<array<string, mixed>> $fallback
     */
    public function __construct(private readonly array $ranked, private readonly array $fallback)
    {
    }

    public function recommend(SearchProfile $profile, int $userId, int $limit): array
    {
        $this->profile = $profile;
        $this->receivedRecommendationLimit = $limit;

        return $this->ranked;
    }

    public function newestEligible(int $userId, int $limit): array
    {
        $this->receivedFallbackLimit = $limit;

        return array_slice($this->fallback, 0, $limit);
    }
}
