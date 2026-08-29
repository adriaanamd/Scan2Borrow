<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\DTO\BarcodePrintBatch;

interface BarcodePrintRepositoryInterface
{
    /** Create and irrevocably mark all active, unprinted copies for a title. */
    public function createBatch(int $titleId, int $staffId, string $token): ?BarcodePrintBatch;

    public function findBatch(string $token): ?BarcodePrintBatch;

    /** @return list<array<string, mixed>> */
    public function history(int $titleId): array;
}
