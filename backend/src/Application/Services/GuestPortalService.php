<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Infrastructure\Persistence\GuestPortalRepositoryInterface;

final class GuestPortalService
{
    public function __construct(
        private readonly GuestPortalRepositoryInterface $portal,
    ) {
    }

    /** @return array{summary: array<string, mixed>, notifications: list<array<string, mixed>>} */
    public function dashboard(int $visitorId): array
    {
        return [
            'summary' => $this->portal->dashboardSummary($visitorId),
            'notifications' => $this->portal->notifications($visitorId),
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function browse(array $filters): array
    {
        return $this->portal->browse($filters);
    }

    /** @return list<array<string, mixed>> */
    public function history(int $visitorId, string $status, string $from, string $to): array
    {
        return $this->portal->history($visitorId, $status, $from, $to);
    }

    /** @return array<string, mixed>|null */
    public function receipt(int $visitorId, int $borrowingId): ?array
    {
        return $this->portal->receipt($visitorId, $borrowingId);
    }
}
