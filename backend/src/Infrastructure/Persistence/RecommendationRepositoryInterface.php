<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\DTO\SearchProfile;

interface RecommendationRepositoryInterface
{
    /** @return list<array<string, mixed>> */
    public function recommend(SearchProfile $profile, int $userId, int $limit): array;

    /** @return list<array<string, mixed>> */
    public function newestEligible(int $userId, int $limit): array;
}
