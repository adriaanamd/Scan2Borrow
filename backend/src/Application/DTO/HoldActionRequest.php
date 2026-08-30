<?php

declare(strict_types=1);

namespace App\Application\DTO;

use InvalidArgumentException;

final readonly class HoldActionRequest
{
    public string $action;

    public function __construct(
        public int $userId,
        public int $holdId,
        string $action,
    ) {
        if ($userId <= 0 || $holdId <= 0) {
            throw new InvalidArgumentException('A valid borrower and reservation are required.');
        }

        $normalized = strtolower(trim($action));
        if (!in_array($normalized, ['cancel', 'claim'], true)) {
            throw new InvalidArgumentException('Unsupported reservation action.');
        }

        $this->action = $normalized;
    }
}
