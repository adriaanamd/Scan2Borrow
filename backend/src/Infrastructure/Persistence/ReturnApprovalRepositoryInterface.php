<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

interface ReturnApprovalRepositoryInterface
{
    /** @return list<array<string, mixed>> */
    public function pending(): array;

    /** @return array<string, mixed>|null */
    public function findPending(string $type, int $id): ?array;

    public function decide(string $type, int $id, string $action, int $staffId, float $fine, string $note): bool;
}
