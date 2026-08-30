<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

interface ReservationCopyRepositoryInterface
{
    public function availableCopyForTitle(int $titleId): ?int;
}
