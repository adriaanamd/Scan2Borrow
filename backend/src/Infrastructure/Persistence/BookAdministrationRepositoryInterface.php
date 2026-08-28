<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

interface BookAdministrationRepositoryInterface extends BookMutationRepositoryInterface
{
    /** @param list<int> $ids */
    public function archive(array $ids): int;

    /** @param list<int> $ids */
    public function restore(array $ids): int;

    /** @param list<int> $ids */
    public function delete(array $ids): int;
}
