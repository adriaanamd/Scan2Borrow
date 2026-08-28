<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class RegistrationRequest
{
    public function __construct(
        public string $barcode,
        public string $firstname,
        public string $middlename,
        public string $lastname,
        public string $role,
        public string $department = '',
        public string $position = '',
        public string $course = '',
        public string $yearLevel = '',
        public string $email = '',
        public string $contactNo = '',
        public string $photoData = '',
    ) {
    }

    public function withEmail(string $email): self
    {
        return new self(
            $this->barcode,
            $this->firstname,
            $this->middlename,
            $this->lastname,
            $this->role,
            $this->department,
            $this->position,
            $this->course,
            $this->yearLevel,
            $email,
            $this->contactNo,
            $this->photoData,
        );
    }
}
