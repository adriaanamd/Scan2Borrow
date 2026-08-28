<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Guest\GuestBorrowingStatus;

final readonly class GuestBorrowingResult
{
    private function __construct(
        private bool $successful,
        private string $message,
        private ?int $borrowingId,
        private ?GuestBorrowingStatus $status,
    ) {
    }

    public static function success(int $borrowingId, GuestBorrowingStatus $status, string $message = ''): self
    {
        return new self(true, $message, $borrowingId, $status);
    }

    public static function failure(string $message): self
    {
        return new self(false, $message, null, null);
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function borrowingId(): ?int
    {
        return $this->borrowingId;
    }

    public function status(): ?GuestBorrowingStatus
    {
        return $this->status;
    }
}
