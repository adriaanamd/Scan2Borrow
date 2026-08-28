<?php

declare(strict_types=1);

namespace App\Domain\Auth;

final readonly class UserAccount
{
    public function __construct(
        private int $id,
        private string $barcode,
        private Role $role,
        private string $status = 'active',
        private ?string $passwordHash = null,
        private int $failedAttempts = 0,
        private bool $isLocked = false,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function barcode(): string
    {
        return $this->barcode;
    }

    public function role(): Role
    {
        return $this->role;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function passwordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function failedAttempts(): int
    {
        return $this->failedAttempts;
    }

    public function locked(): bool
    {
        return $this->isLocked;
    }

    public function withFailedAttempts(int $attempts): self
    {
        return new self(
            $this->id,
            $this->barcode,
            $this->role,
            $this->status,
            $this->passwordHash,
            $attempts,
            $this->isLocked,
        );
    }

    public function lockedCopy(): self
    {
        return new self(
            $this->id,
            $this->barcode,
            $this->role,
            $this->status,
            $this->passwordHash,
            $this->failedAttempts,
            true,
        );
    }
}
