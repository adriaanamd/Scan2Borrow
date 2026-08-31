<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

interface ProfileChangeRequestRepositoryInterface
{
    /** @return array<string, mixed>|null */
    public function profile(int $userId): ?array;

    /** @return array<string, mixed>|null */
    public function pendingForUser(int $userId): ?array;

    /** @param array<string, string> $originalValues @param array<string, string> $requestedValues */
    public function create(int $userId, array $originalValues, array $requestedValues, ?string $originalPhoto, ?string $requestedPhoto): int;

    /** @return list<array<string, mixed>> */
    public function pendingRequests(): array;

    /** @return array<string, mixed>|null */
    public function decide(int $requestId, int $reviewerId, string $decision, string $reviewNote): ?array;
}
