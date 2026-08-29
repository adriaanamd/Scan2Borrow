<?php

declare(strict_types=1);

namespace App\Domain\Borrowing;

final readonly class BulkBorrowingResult
{
    public function __construct(
        private bool $isSuccessful,
        private string $message,
        private ?string $transactionCode = null,
        private int $copyCount = 0,
        private int $titleCount = 0,
    ) {
    }

    public static function success(string $message, string $transactionCode, int $copyCount, int $titleCount): self
    {
        return new self(true, $message, $transactionCode, $copyCount, $titleCount);
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

    public function copyCount(): int
    {
        return $this->copyCount;
    }

    public function titleCount(): int
    {
        return $this->titleCount;
    }
}
