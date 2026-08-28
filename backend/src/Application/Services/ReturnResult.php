<?php

declare(strict_types=1);

namespace App\Application\Services;

final readonly class ReturnResult
{
    public function __construct(
        private bool $isSuccessful,
        private string $message,
    ) {
    }

    public static function success(string $message): self
    {
        return new self(true, $message);
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
}
