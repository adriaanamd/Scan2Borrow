<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

interface RegistrationUserRepositoryInterface
{
    public function existsByBarcode(string $barcode): bool;
}
