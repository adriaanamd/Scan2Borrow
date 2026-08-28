<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class GuestRegistrationRequest
{
    public function __construct(
        public string $firstname = '',
        public string $middlename = '',
        public string $lastname = '',
        public string $suffix = '',
        public string $gender = '',
        public string $birthdate = '',
        public string $contactNo = '',
        public string $email = '',
        public string $houseNo = '',
        public string $street = '',
        public string $barangay = '',
        public string $municipality = '',
        public string $province = '',
        public string $purpose = '',
        public string $purposeOther = '',
        public string $idType = '',
        public string $idBarcode = '',
        public string $photoData = '',
    ) {
    }

    public function withGender(string $gender): self
    {
        return $this->copy(gender: $gender);
    }

    public function withPurpose(string $purpose): self
    {
        return $this->copy(purpose: $purpose);
    }

    public function withPurposeOther(string $purposeOther): self
    {
        return $this->copy(purposeOther: $purposeOther);
    }

    public function withContactNo(string $contactNo): self
    {
        return $this->copy(contactNo: $contactNo);
    }

    public function withEmail(string $email): self
    {
        return $this->copy(email: $email);
    }

    public function withBirthdate(string $birthdate): self
    {
        return $this->copy(birthdate: $birthdate);
    }

    public function withPhotoData(string $photoData): self
    {
        return $this->copy(photoData: $photoData);
    }

    private function copy(
        ?string $gender = null,
        ?string $purpose = null,
        ?string $purposeOther = null,
        ?string $contactNo = null,
        ?string $email = null,
        ?string $birthdate = null,
        ?string $photoData = null,
    ): self {
        return new self(
            $this->firstname,
            $this->middlename,
            $this->lastname,
            $this->suffix,
            $gender ?? $this->gender,
            $birthdate ?? $this->birthdate,
            $contactNo ?? $this->contactNo,
            $email ?? $this->email,
            $this->houseNo,
            $this->street,
            $this->barangay,
            $this->municipality,
            $this->province,
            $purpose ?? $this->purpose,
            $purposeOther ?? $this->purposeOther,
            $this->idType,
            $this->idBarcode,
            $photoData ?? $this->photoData,
        );
    }
}
