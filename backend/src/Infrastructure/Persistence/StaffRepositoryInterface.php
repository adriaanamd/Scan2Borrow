<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

interface StaffRepositoryInterface
{
    /** @return array<string, mixed> */
    public function dashboard(): array;

    /** @return list<array<string, mixed>> */
    public function borrowers(string $search): array;

    /** @return array{borrower: array<string, mixed>, summary: array<string, mixed>, history: list<array<string, mixed>>}|null */
    public function borrowerDetails(int $userId): ?array;

    public function updateBorrowerPhoto(int $userId, string $photoPath): void;

    /** @return list<array<string, mixed>> */
    public function overdue(): array;

    /** @return array<string, mixed> */
    public function report(string $type, string $from, string $to): array;

    /** @return list<array<string, mixed>> */
    public function guestRequests(): array;

    /** @return list<array<string, mixed>> */
    public function staffAccounts(): array;

    /** @return list<array<string, mixed>> */
    public function borrowerCandidates(string $search): array;

    /** @return list<array<string, mixed>> */
    public function pendingBorrowings(): array;

    /** @return list<array<string, mixed>> */
    public function notifications(int $staffId, string $type): array;

    public function markNotificationViewed(int $notificationId, int $staffId, string $type): void;

    public function approveBorrowing(int $borrowingId, int $staffId): void;

    public function rejectBorrowing(int $borrowingId, int $staffId): void;

    public function promote(int $userId, string $role, string $password): void;

    public function resetPassword(int $userId, string $password): void;

    public function demote(int $userId): void;

    public function toggleStatus(int $userId): void;
}
