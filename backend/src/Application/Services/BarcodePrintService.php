<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\BarcodePrintBatch;
use App\Application\DTO\BarcodePrintResult;
use App\Infrastructure\Persistence\BarcodePrintRepositoryInterface;
use InvalidArgumentException;

final readonly class BarcodePrintService
{
    public function __construct(private BarcodePrintRepositoryInterface $repository)
    {
    }

    public function create(int $titleId, int $staffId): BarcodePrintResult
    {
        if ($titleId < 1 || $staffId < 1) {
            throw new InvalidArgumentException('A valid title and staff account are required.');
        }

        $batch = $this->repository->createBatch($titleId, $staffId, bin2hex(random_bytes(16)));

        return new BarcodePrintResult($batch === null ? 'skipped' : 'created', $batch);
    }

    public function find(string $token): ?BarcodePrintBatch
    {
        $token = trim($token);
        if (preg_match('/\A[a-f0-9]{32}\z/', $token) !== 1) {
            throw new InvalidArgumentException('A valid print batch token is required.');
        }

        return $this->repository->findBatch($token);
    }

    /** @return list<array<string, mixed>> */
    public function history(int $titleId): array
    {
        if ($titleId < 1) {
            throw new InvalidArgumentException('A valid title is required.');
        }

        return $this->repository->history($titleId);
    }
}
