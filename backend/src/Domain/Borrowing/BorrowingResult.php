<?php

declare(strict_types=1);

namespace App\Domain\Borrowing;

final readonly class BorrowingResult
{
    public function __construct(
        private bool $isSuccessful,
        private string $message,
        private ?string $transactionCode = null,
    ) {
    }

    public static function success(string $message, string $transactionCode): self
    {
        return new self(true, $message, $transactionCode);
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

    public function transactionCode(): ?string
    {
        return $this->transactionCode;
    }
}
