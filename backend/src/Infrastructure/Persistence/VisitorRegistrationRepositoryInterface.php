<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\DTO\GuestRegistrationRequest;

interface VisitorRegistrationRepositoryInterface
{
    public function existsByIdBarcode(string $idBarcode): bool;

    public function create(GuestRegistrationRequest $request): int;
}
