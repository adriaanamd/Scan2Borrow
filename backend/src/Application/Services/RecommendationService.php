<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\RecommendationResult;
use App\Application\DTO\SearchProfile;
use App\Infrastructure\Persistence\RecommendationRepositoryInterface;
use App\Infrastructure\Persistence\SearchHistoryRepositoryInterface;

final class RecommendationService
{
    private const HISTORY_LIMIT = 20;
    private const RESULT_LIMIT = 5;

    public function __construct(
        private readonly SearchHistoryRepositoryInterface $history,
        private readonly RecommendationRepositoryInterface $catalog,
    ) {
    }

    public function forBorrower(int $userId): RecommendationResult
    {
        $recent = $this->history->recentQueries($userId, self::HISTORY_LIMIT);
        $profile = SearchProfile::fromRecentSearches(array_reverse($recent));
        if ($profile->isEmpty()) {
            $fallback = $this->catalog->newestEligible($userId, self::RESULT_LIMIT);

            return new RecommendationResult($fallback, false);
        }

        $ranked = $this->catalog->recommend($profile, $userId, self::RESULT_LIMIT);
        $books = $this->uniqueBooks($this->publicBooks($ranked));
        $remaining = self::RESULT_LIMIT - count($books);
        if ($remaining > 0) {
            $books = array_merge($books, $this->withoutDuplicates(
                $this->catalog->newestEligible($userId, $remaining),
                $books,
            ));
        }

        return new RecommendationResult(array_slice($books, 0, self::RESULT_LIMIT), $ranked !== []);
    }

    /** @param list<array<string, mixed>> $books
     * @return list<array<string, mixed>> */
    private function publicBooks(array $books): array
    {
        foreach ($books as &$book) {
            unset($book['score']);
        }
        unset($book);

        return $books;
    }

    /** @param list<array<string, mixed>> $books
     * @return list<array<string, mixed>> */
    private function uniqueBooks(array $books): array
    {
        return $this->withoutDuplicates($books, []);
    }

    /** @param list<array<string, mixed>> $books
     * @param list<array<string, mixed>> $existing
     * @return list<array<string, mixed>> */
    private function withoutDuplicates(array $books, array $existing): array
    {
        $seen = [];
        foreach ($existing as $book) {
            $key = $this->bookKey($book);
            if ($key !== null) {
                $seen[$key] = true;
            }
        }

        $unique = [];
        foreach ($books as $book) {
            $key = $this->bookKey($book);
            if ($key === null || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $book;
        }

        return $unique;
    }

    /** @param array<string, mixed> $book */
    private function bookKey(array $book): ?string
    {
        $value = $book['title_id'] ?? $book['id'] ?? null;
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (string) $value;
        }

        return null;
    }
}
