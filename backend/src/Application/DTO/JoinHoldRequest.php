<?php

declare(strict_types=1);

namespace App\Application\DTO;

use InvalidArgumentException;

final readonly class JoinHoldRequest
{
    public function __construct(
        public int $userId,
        public int $titleId,
    ) {
        if ($userId <= 0 || $titleId <= 0) {
            throw new InvalidArgumentException('A valid borrower and title are required.');
        }
    }
}
