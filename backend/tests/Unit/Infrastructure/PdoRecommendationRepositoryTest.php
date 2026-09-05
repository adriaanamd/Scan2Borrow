<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Infrastructure\Persistence\PdoRecommendationRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoRecommendationRepositoryTest extends TestCase
{
    public function testRankingSqlUsesFullTextFieldsAndBoundedEligibilityPredicates(): void
    {
        $repository = new PdoRecommendationRepository(new PDO('sqlite::memory:'));
        $sql = $repository->rankingSqlForTests();

        foreach ([
            'MATCH(t.title) AGAINST',
            'MATCH(t.category_name) AGAINST',
            'MATCH(t.author) AGAINST',
            'book_title_keywords',
            "available_copy.status = 'Available'",
            'NOT EXISTS',
            'LIMIT :limit',
        ] as $fragment) {
            self::assertStringContainsString($fragment, $sql);
        }
    }
}
