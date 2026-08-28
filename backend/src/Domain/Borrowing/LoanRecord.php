<?php

declare(strict_types=1);

namespace App\Domain\Borrowing;

use DateTimeImmutable;

final readonly class LoanRecord
{
    public function __construct(
        private int $id,
        private int $bookId,
        private string $transactionCode,
        private DateTimeImmutable $dueDate,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function bookId(): int
    {
        return $this->bookId;
    }

    public function transactionCode(): string
    {
        return $this->transactionCode;
    }

    public function dueDate(): DateTimeImmutable
    {
        return $this->dueDate;
    }
}
