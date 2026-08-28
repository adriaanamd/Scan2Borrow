<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

interface RegistrationAccountRepositoryInterface extends RegistrationUserRepositoryInterface
{
    /** @param array<string, string> $payload */
    public function createAccount(array $payload, ?string $photoPath): int;
}
