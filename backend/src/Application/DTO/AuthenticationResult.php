<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Auth\Role;

final readonly class AuthenticationResult
{
    public function __construct(
        private bool $isSuccessful,
        private string $message = '',
        private ?string $redirect = null,
        private ?Role $registrationRole = null,
    ) {
    }

    public static function success(string $redirect): self
    {
        return new self(true, '', $redirect);
    }

    public static function failure(string $message): self
    {
        return new self(false, $message);
    }

    public static function registrationRequired(Role $role): self
    {
        return new self(false, '', null, $role);
    }

    public function successful(): bool
    {
        return $this->isSuccessful;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function redirectPath(): ?string
    {
        return $this->redirect;
    }

    public function registrationRole(): ?Role
    {
        return $this->registrationRole;
    }
}
