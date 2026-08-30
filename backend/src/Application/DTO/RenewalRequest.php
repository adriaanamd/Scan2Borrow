<?php

declare(strict_types=1);

namespace App\Application\DTO;

use InvalidArgumentException;

final readonly class RenewalRequest
{
    public string $reason;

    public function __construct(
        public int $userId,
        public int $loanId,
        string $reason = '',
    ) {
        if ($userId <= 0 || $loanId <= 0) {
            throw new InvalidArgumentException('A valid borrower and loan are required.');
        }

        $this->reason = trim($reason);
        if (strlen($this->reason) > 500) {
            throw new InvalidArgumentException('Renewal reason cannot exceed 500 characters.');
        }
    }
}
