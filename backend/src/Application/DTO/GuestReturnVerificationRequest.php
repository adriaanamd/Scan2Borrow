<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class GuestReturnVerificationRequest
{
    public function __construct(
        private string $bookBarcode,
        private string $returnPhoto,
    ) {
    }

    public function bookBarcode(): string
    {
        return $this->bookBarcode;
    }

    public function returnPhoto(): string
    {
        return $this->returnPhoto;
    }
}
