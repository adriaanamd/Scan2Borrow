<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class BookMutationResult
{
    public function __construct(
        private bool $isSuccessful,
        private string $message = '',
        private ?int $bookId = null,
    ) {
    }

    public static function success(?int $bookId = null): self
    {
        return new self(true, '', $bookId);
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

    public function bookId(): ?int
    {
        return $this->bookId;
    }
}
