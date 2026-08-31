<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\DTO\BookCopyMutationRequest;

interface BookAdministrationRepositoryInterface extends BookMutationRepositoryInterface
{
    /** @param list<int> $ids */
    public function archive(array $ids, int $actorId = 0): int;

    /** @param list<int> $ids */
    public function restore(array $ids, int $actorId = 0): int;

    /** @param list<int> $ids */
    public function delete(array $ids, int $actorId = 0): int;

    /** @return list<array<string, mixed>> */
    public function copies(int $titleId): array;

    public function updateCopy(BookCopyMutationRequest $request): void;

    /** @param list<int> $ids */
    public function archiveCopies(array $ids, int $actorId = 0): int;

    /** @param list<int> $ids */
    public function restoreCopies(array $ids, int $actorId = 0): int;

    /** @param list<int> $ids */
    public function deleteCopies(array $ids, int $actorId = 0): int;
}
