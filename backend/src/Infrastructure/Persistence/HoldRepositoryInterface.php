<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Reservation\HoldRecord;
use DateTimeImmutable;

interface HoldRepositoryInterface
{
    public function find(int $holdId): ?HoldRecord;

    public function findActiveForUserTitle(int $userId, int $titleId): ?HoldRecord;

    /** @return list<HoldRecord> */
    public function listForUser(int $userId): array;

    public function join(int $userId, int $titleId): HoldRecord;

    public function cancel(int $holdId, int $userId): bool;

    public function claim(int $holdId, int $userId): ?HoldRecord;

    public function fulfil(int $holdId, int $staffId): bool;

    public function nextEligibleQueued(int $titleId): ?HoldRecord;

    public function offer(int $holdId, int $copyId, DateTimeImmutable $offeredAt, DateTimeImmutable $expiresAt): bool;

    public function expire(int $holdId, DateTimeImmutable $expiredAt): bool;

    /** @return list<HoldRecord> */
    public function listStaff(string $status): array;

    /** @return list<int> */
    public function expireOffers(DateTimeImmutable $now): array;
}
