<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class GuestProfileUpdateResult
{
    public function __construct(
        private bool $needsVerification,
        private bool $wasUpdated,
        private ?string $verificationToken = null,
    ) {
    }

    public static function verificationRequired(): self
    {
        return new self(true, false);
    }

    public static function verificationRequiredFor(string $token): self
    {
        return new self(true, false, $token);
    }

    public static function success(): self
    {
        return new self(false, true);
    }

    public function requiresVerification(): bool
    {
        return $this->needsVerification;
    }

    public function updated(): bool
    {
        return $this->wasUpdated;
    }

    public function verificationToken(): ?string
    {
        return $this->verificationToken;
    }
}
