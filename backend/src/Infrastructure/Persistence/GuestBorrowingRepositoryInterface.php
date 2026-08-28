<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\DTO\GuestBorrowRequest;

interface GuestBorrowingRepositoryInterface
{
    public function isBookAvailable(int $bookId): bool;

    public function activeCount(int $visitorId): int;

    public function createPending(GuestBorrowRequest $request, int $visitorId): int;

    public function findReleasedByBarcode(int $visitorId, string $bookBarcode): ?int;

    public function markReturnVerification(int $borrowingId, string $photo): void;
}
