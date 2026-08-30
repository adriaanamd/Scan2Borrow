<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Renewal\RenewalLoanSnapshot;
use DateTimeImmutable;

final readonly class RenewalEligibilityResult
{
    public function __construct(
        private bool $isEligible,
        private string $message,
        private ?RenewalLoanSnapshot $loan = null,
        private ?DateTimeImmutable $requestedDueDate = null,
    ) {
    }

    public static function allowed(RenewalLoanSnapshot $loan, DateTimeImmutable $requestedDueDate): self
    {
        return new self(true, 'This loan is eligible for one standard renewal.', $loan, $requestedDueDate);
    }

    public static function denied(string $message): self { return new self(false, $message); }
    public function eligible(): bool { return $this->isEligible; }
    public function message(): string { return $this->message; }
    public function loan(): ?RenewalLoanSnapshot { return $this->loan; }
    public function requestedDueDate(): ?DateTimeImmutable { return $this->requestedDueDate; }
}
