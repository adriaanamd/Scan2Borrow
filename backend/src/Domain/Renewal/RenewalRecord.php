<?php

declare(strict_types=1);

namespace App\Domain\Renewal;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class RenewalRecord
{
    private function __construct(
        private int $id,
        private int $loanId,
        private int $userId,
        private string $title,
        private string $author,
        private string $transactionCode,
        private DateTimeImmutable $originalDueDate,
        private DateTimeImmutable $requestedDueDate,
        private RenewalStatus $status,
        private string $reason,
        private string $decisionNote,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $status = is_string($row['status'] ?? null) ? RenewalStatus::tryFrom($row['status']) : null;
        if ($status === null) {
            throw new InvalidArgumentException('Unknown renewal status.');
        }

        return new self(
            self::positiveInt($row['id'] ?? null, 'renewal id'),
            self::positiveInt($row['loan_id'] ?? null, 'loan id'),
            self::positiveInt($row['user_id'] ?? null, 'user id'),
            self::string($row['title'] ?? null),
            self::string($row['author'] ?? null),
            self::string($row['transaction_code'] ?? null),
            self::date($row['original_due_date'] ?? null, 'original due date'),
            self::date($row['requested_due_date'] ?? null, 'requested due date'),
            $status,
            self::string($row['reason'] ?? null),
            self::string($row['decision_note'] ?? null),
        );
    }

    public function id(): int { return $this->id; }
    public function loanId(): int { return $this->loanId; }
    public function userId(): int { return $this->userId; }
    public function title(): string { return $this->title; }
    public function author(): string { return $this->author; }
    public function transactionCode(): string { return $this->transactionCode; }
    public function originalDueDate(): DateTimeImmutable { return $this->originalDueDate; }
    public function requestedDueDate(): DateTimeImmutable { return $this->requestedDueDate; }
    public function status(): RenewalStatus { return $this->status; }
    public function reason(): string { return $this->reason; }
    public function decisionNote(): string { return $this->decisionNote; }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'loan_id' => $this->loanId,
            'user_id' => $this->userId,
            'title' => $this->title,
            'author' => $this->author,
            'transaction_code' => $this->transactionCode,
            'original_due_date' => $this->originalDueDate->format('Y-m-d'),
            'requested_due_date' => $this->requestedDueDate->format('Y-m-d'),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'reason' => $this->reason,
            'decision_note' => $this->decisionNote,
        ];
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        $number = is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : 0);
        if ($number <= 0) throw new InvalidArgumentException('Invalid ' . $label . '.');
        return $number;
    }

    private static function string(mixed $value): string { return is_string($value) ? $value : ''; }

    private static function date(mixed $value, string $label): DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') throw new InvalidArgumentException('Invalid ' . $label . '.');
        try { return new DateTimeImmutable($value); } catch (\Exception) { throw new InvalidArgumentException('Invalid ' . $label . '.'); }
    }
}
