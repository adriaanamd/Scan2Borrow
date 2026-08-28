<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\DTO\GuestProfileUpdateRequest;

interface VisitorDetailsRepositoryInterface extends VisitorProfileRepositoryInterface
{
    /** @return array<string, mixed>|null */
    public function find(int $visitorId): ?array;

    /** @return array<string, mixed>|null */
    public function pass(int $visitorId): ?array;
}
