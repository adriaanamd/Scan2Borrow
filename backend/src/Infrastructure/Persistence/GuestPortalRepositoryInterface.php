<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

interface GuestPortalRepositoryInterface
{
    /** @return array<string, mixed> */
    public function dashboardSummary(int $visitorId): array;

    /** @return list<array<string, mixed>> */
    public function notifications(int $visitorId): array;

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function browse(array $filters): array;

    /** @return list<array<string, mixed>> */
    public function history(int $visitorId, string $status, string $from, string $to): array;

    /** @return array<string, mixed>|null */
    public function receipt(int $visitorId, int $borrowingId): ?array;
}
