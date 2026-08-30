<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Renewal\RenewalRecord;

final readonly class RenewalResult
{
    public function __construct(private bool $isSuccessful, private string $message, private ?RenewalRecord $record = null) {}
    public static function success(string $message, ?RenewalRecord $record = null): self { return new self(true, $message, $record); }
    public static function failure(string $message): self { return new self(false, $message); }
    public function successful(): bool { return $this->isSuccessful; }
    public function message(): string { return $this->message; }
    public function record(): ?RenewalRecord { return $this->record; }
}
