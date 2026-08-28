<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Auth\UserAccount;

interface UserRepositoryInterface
{
    public function findByBarcode(string $barcode): ?UserAccount;

    public function isLocked(string $barcode): bool;

    public function recordLoginFailure(?int $userId, string $barcode): void;

    public function lock(string $barcode, int $minutes): void;

    public function recordLoginSuccess(int $userId, string $barcode): void;
}
