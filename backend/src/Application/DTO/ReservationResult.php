<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Reservation\HoldRecord;

final readonly class ReservationResult
{
    public function __construct(
        private bool $isSuccessful,
        private string $message,
        private ?HoldRecord $record = null,
    ) {
    }

    public static function success(string $message, ?HoldRecord $record = null): self
    {
        return new self(true, $message, $record);
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

    public function record(): ?HoldRecord
    {
        return $this->record;
    }
}
