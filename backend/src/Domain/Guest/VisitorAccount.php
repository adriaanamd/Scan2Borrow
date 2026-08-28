<?php

declare(strict_types=1);

namespace App\Domain\Guest;

final readonly class VisitorAccount
{
    public function __construct(
        private int $id,
        private string $governmentIdBarcode,
        private string $status,
        private string $name,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function governmentIdBarcode(): string
    {
        return $this->governmentIdBarcode;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function isEligibleForBorrowing(): bool
    {
        return !in_array($this->status, ['Expired', 'Suspended'], true);
    }
}
