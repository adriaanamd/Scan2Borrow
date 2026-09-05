<?php

declare(strict_types=1);

namespace Tests\Unit\Recommendation;

use App\Application\DTO\SearchProfile;
use PHPUnit\Framework\TestCase;

final class SearchProfileTest extends TestCase
{
    public function testBuildsARecentWeightedCaseInsensitiveTermProfile(): void
    {
        $profile = SearchProfile::fromRecentSearches([
            'old gardening',
            'php security',
            'PHP testing',
        ]);

        self::assertSame(['php', 'testing', 'security', 'gardening', 'old'], $profile->terms());
        self::assertGreaterThan($profile->weights()['gardening'], $profile->weights()['php']);
        self::assertFalse($profile->isEmpty());
    }

    public function testPreservesUsefulProgrammingTokensAndDropsNoise(): void
    {
        $profile = SearchProfile::fromRecentSearches(['C++ C# a !!!', '   ###   ']);

        self::assertArrayHasKey('c++', $profile->weights());
        self::assertArrayHasKey('c#', $profile->weights());
        self::assertArrayNotHasKey('a', $profile->weights());
        self::assertCount(2, $profile->terms());
    }

    public function testCapsTheProfileAtTwentyFiveTerms(): void
    {
        $terms = implode(' ', array_map(static fn (int $number): string => 'term' . $number, range(1, 30)));

        self::assertCount(25, SearchProfile::fromRecentSearches([$terms])->terms());
    }

    public function testEmptySearchesProduceAnEmptyProfile(): void
    {
        $profile = SearchProfile::fromRecentSearches(['', '  !!!  ']);

        self::assertTrue($profile->isEmpty());
        self::assertSame([], $profile->terms());
    }

    public function testFullTextQueryBoostsRecentTermsAndQuotesProgrammingTokens(): void
    {
        $profile = SearchProfile::fromRecentSearches(['old topic', 'new topic']);
        $query = $profile->fullTextQuery();

        self::assertStringContainsString('>new*', $query);
        self::assertStringContainsString('<old*', $query);
        self::assertStringContainsString('"c++"', SearchProfile::fromRecentSearches(['C++'])->fullTextQuery());
    }
}
