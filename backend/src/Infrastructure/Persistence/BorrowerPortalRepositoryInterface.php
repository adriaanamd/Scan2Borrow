<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

interface BorrowerPortalRepositoryInterface
{
    /** @return array<string, mixed> */
    public function dashboard(int $userId): array;

    /** @return list<array<string, mixed>> */
    public function history(int $userId): array;

    /** @return array<string, mixed>|null */
    public function receipt(int $userId, string $transactionCode): ?array;
}
