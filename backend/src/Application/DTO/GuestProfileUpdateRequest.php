<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class GuestProfileUpdateRequest
{
    public function __construct(
        public string $contactNo,
        public string $email,
        public string $houseNo,
        public string $street,
        public string $barangay,
        public string $municipality,
        public string $province,
        public string $purpose,
        public string $purposeOther,
    ) {
    }
}
