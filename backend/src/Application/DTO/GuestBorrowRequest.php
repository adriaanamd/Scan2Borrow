<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class GuestBorrowRequest
{
    public function __construct(
        private int $bookId,
        private string $governmentIdBarcode,
        private string $verificationPhoto,
    ) {
    }

    public function bookId(): int
    {
        return $this->bookId;
    }

    public function governmentIdBarcode(): string
    {
        return $this->governmentIdBarcode;
    }

    public function verificationPhoto(): string
    {
        return $this->verificationPhoto;
    }
}
