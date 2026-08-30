<?php

declare(strict_types=1);

namespace App\Application\DTO;

use InvalidArgumentException;

final readonly class RenewalDecisionRequest
{
    public string $action;
    public string $note;

    public function __construct(
        public int $renewalId,
        public int $staffId,
        string $action,
        string $note = '',
    ) {
        if ($renewalId <= 0 || $staffId <= 0) {
            throw new InvalidArgumentException('A valid renewal and staff account are required.');
        }
        $normalized = strtolower(trim($action));
        if (!in_array($normalized, ['approve', 'reject'], true)) {
            throw new InvalidArgumentException('Unsupported renewal decision.');
        }
        $this->action = $normalized;
        $this->note = trim($note);
        if (strlen($this->note) > 500) {
            throw new InvalidArgumentException('Decision note cannot exceed 500 characters.');
        }
    }
}
