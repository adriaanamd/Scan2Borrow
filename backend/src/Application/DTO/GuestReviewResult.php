<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class GuestReviewResult
{
    private function __construct(
        private bool $successful,
        private string $status,
        private string $message,
    ) {
    }

    public static function success(string $status, string $message): self
    {
        return new self(true, $status, $message);
    }

    public static function failure(string $message): self
    {
        return new self(false, '', $message);
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function message(): string
    {
        return $this->message;
    }
}
