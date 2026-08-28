<?php

declare(strict_types=1);

namespace App\Domain\Borrowing;

final readonly class BorrowingPolicy
{
    public function __construct(
        private int $maxBooks,
        private int $loanDays,
        private int $teacherMaxDays,
        private bool $requiresApproval,
    ) {
    }

    public function maxBooks(): int
    {
        return $this->maxBooks;
    }

    public function loanDays(): int
    {
        return $this->loanDays;
    }

    public function teacherMaxDays(): int
    {
        return $this->teacherMaxDays;
    }

    public function requiresApproval(): bool
    {
        return $this->requiresApproval;
    }
}
