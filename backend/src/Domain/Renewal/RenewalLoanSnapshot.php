<?php

declare(strict_types=1);

namespace App\Domain\Renewal;

use DateTimeImmutable;

final readonly class RenewalLoanSnapshot
{
    public function __construct(
        public int $loanId,
        public int $userId,
        public int $titleId,
        public string $title,
        public DateTimeImmutable $dueDate,
    ) {
    }
}
