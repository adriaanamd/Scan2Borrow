<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\DTO\BarcodePrintBatch;
use App\Application\Services\BarcodePrintService;
use App\Infrastructure\Persistence\BarcodePrintRepositoryInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BarcodePrintServiceTest extends TestCase
{
    public function testCreationReturnsCreatedResultAndOpaqueToken(): void
    {
        $repository = new class implements BarcodePrintRepositoryInterface {
            public string $token = '';

            public function createBatch(int $titleId, int $staffId, string $token): ?BarcodePrintBatch
            {
                $this->token = $token;

                return new BarcodePrintBatch(1, $token, $titleId, 'Clean Code', '2026-08-29 10:00:00', []);
            }

            public function findBatch(string $token): ?BarcodePrintBatch { return null; }

            public function history(int $titleId): array { return []; }
        };

        $result = (new BarcodePrintService($repository))->create(4, 7);

        self::assertSame('created', $result->status);
        self::assertNotNull($result->batch);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/', $repository->token);
    }

    public function testNoCopiesIsARegularSkippedResult(): void
    {
        $repository = new class implements BarcodePrintRepositoryInterface {
            public function createBatch(int $titleId, int $staffId, string $token): ?BarcodePrintBatch { return null; }
            public function findBatch(string $token): ?BarcodePrintBatch { return null; }
            public function history(int $titleId): array { return []; }
        };

        $result = (new BarcodePrintService($repository))->create(4, 7);

        self::assertSame('skipped', $result->status);
        self::assertFalse($result->toArray()['printed']);
    }

    public function testRejectsMalformedTokensBeforeRepositoryLookup(): void
    {
        $repository = new class implements BarcodePrintRepositoryInterface {
            public function createBatch(int $titleId, int $staffId, string $token): ?BarcodePrintBatch { return null; }
            public function findBatch(string $token): ?BarcodePrintBatch { throw new \LogicException('Should not query.'); }
            public function history(int $titleId): array { return []; }
        };

        $this->expectException(InvalidArgumentException::class);
        (new BarcodePrintService($repository))->find('not-a-batch-token');
    }
}
