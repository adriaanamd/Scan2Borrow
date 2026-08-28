<?php

declare(strict_types=1);

namespace App\Domain\Registration;

use DateTimeImmutable;

final readonly class OtpRecord
{
    /**
     * @param array<string, string> $payload
     */
    private function __construct(
        private int $id,
        private string $barcode,
        private string $otpCode,
        private string $phoneNumber,
        private array $payload,
        private DateTimeImmutable $expiresAt,
        private DateTimeImmutable $createdAt,
        private bool $isUsed,
    ) {
    }

    /**
     * @param array<string, string> $payload
     */
    public static function pending(
        int $id,
        string $barcode,
        string $otpCode,
        string $phoneNumber,
        array $payload,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $barcode, $otpCode, $phoneNumber, $payload, $expiresAt, $createdAt, false);
    }

    public function id(): int
    {
        return $this->id;
    }

    public function barcode(): string
    {
        return $this->barcode;
    }

    public function otpCode(): string
    {
        return $this->otpCode;
    }

    public function phoneNumber(): string
    {
        return $this->phoneNumber;
    }

    /**
     * @return array<string, string>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function used(): bool
    {
        return $this->isUsed;
    }

    public function usedCopy(): self
    {
        return new self(
            $this->id,
            $this->barcode,
            $this->otpCode,
            $this->phoneNumber,
            $this->payload,
            $this->expiresAt,
            $this->createdAt,
            true,
        );
    }

    public function withCode(string $code, DateTimeImmutable $expiresAt): self
    {
        return new self(
            $this->id,
            $this->barcode,
            $code,
            $this->phoneNumber,
            $this->payload,
            $expiresAt,
            $this->createdAt,
            $this->isUsed,
        );
    }
}
