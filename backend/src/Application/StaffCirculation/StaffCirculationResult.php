<?php

declare(strict_types=1);

namespace App\Application\StaffCirculation;

final readonly class StaffCirculationResult
{
    /** @param array<string, mixed> $data */
    public function __construct(
        private bool $isSuccessful,
        private string $message,
        private array $data = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function success(string $message, array $data = []): self
    {
        return new self(true, $message, $data);
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

    /** @return array<string, mixed> */
    public function data(): array
    {
        return $this->data;
    }
}
