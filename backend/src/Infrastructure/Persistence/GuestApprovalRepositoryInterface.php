<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

interface GuestApprovalRepositoryInterface
{
    /** @return array{id: int, visitor_id: int, title: string, due_date?: string}|null */
    public function findPending(int $requestId): ?array;

    public function approve(int $requestId, string $notes): void;

    public function reject(int $requestId, string $reason): void;
}
