<?php

declare(strict_types=1);

namespace App\Domain\Guest;

final readonly class VisitorProfile
{
    public function __construct(
        private int $id,
        private string $contactNo,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function contactNo(): string
    {
        return $this->contactNo;
    }
}
