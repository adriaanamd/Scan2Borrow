<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\DTO\GuestProfileUpdateRequest;

interface VisitorProfileRepositoryInterface
{
    public function updateProfile(int $visitorId, GuestProfileUpdateRequest $request): void;
}
