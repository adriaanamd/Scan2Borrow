<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class RegistrationResult
{
    public function __construct(
        private bool $isSuccessful,
        private string $message = '',
        private ?string $barcode = null,
    ) {
    }

    public static function success(string $barcode): self
    {
        return new self(true, '', $barcode);
    }

    public static function failure(string $message): self
    {
        return new self(false, $message);
    }

    public function successful(): bool
    {
        return $this->isSuccessful;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function barcode(): ?string
    {
        return $this->barcode;
    }
}
