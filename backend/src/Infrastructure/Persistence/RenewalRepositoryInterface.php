<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Renewal\RenewalRecord;
use DateTimeImmutable;

interface RenewalRepositoryInterface
{
    public function find(int $renewalId): ?RenewalRecord;

    /** @return list<RenewalRecord> */
    public function listForUser(int $userId): array;

    /** @return list<RenewalRecord> */
    public function listPending(): array;

    public function hasPendingForLoan(int $loanId, int $userId): bool;

    public function hasApprovedForLoan(int $loanId): bool;

    public function create(
        int $loanId,
        int $userId,
        DateTimeImmutable $originalDueDate,
        DateTimeImmutable $requestedDueDate,
        string $reason,
    ): RenewalRecord;

    public function approve(int $renewalId, int $staffId, string $note, DateTimeImmutable $decidedAt): ?RenewalRecord;

    public function reject(int $renewalId, int $staffId, string $note, DateTimeImmutable $decidedAt): ?RenewalRecord;

    public function cancel(int $renewalId, int $userId, DateTimeImmutable $cancelledAt): bool;
}
