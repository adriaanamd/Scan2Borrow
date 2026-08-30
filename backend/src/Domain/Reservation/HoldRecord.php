<?php

declare(strict_types=1);

namespace App\Domain\Reservation;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class HoldRecord
{
    private function __construct(
        private int $id,
        private int $userId,
        private int $titleId,
        private string $title,
        private string $author,
        private HoldStatus $status,
        private ?int $queuePosition,
        private ?DateTimeImmutable $holdExpiresAt,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        $id = self::positiveInt($row['id'] ?? null, 'hold id');
        $userId = self::positiveInt($row['user_id'] ?? null, 'user id');
        $titleId = self::positiveInt($row['title_id'] ?? null, 'title id');
        $status = is_string($row['status'] ?? null) ? HoldStatus::tryFrom($row['status']) : null;
        if ($status === null) {
            throw new InvalidArgumentException('Unknown reservation status.');
        }

        return new self(
            $id,
            $userId,
            $titleId,
            is_string($row['title'] ?? null) ? $row['title'] : '',
            is_string($row['author'] ?? null) ? $row['author'] : '',
            $status,
            self::nullableInt($row['queue_position'] ?? null),
            self::nullableDate($row['hold_expires_at'] ?? null),
        );
    }

    public function id(): int
    {
        return $this->id;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function titleId(): int
    {
        return $this->titleId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function author(): string
    {
        return $this->author;
    }

    public function status(): HoldStatus
    {
        return $this->status;
    }

    public function queuePosition(): ?int
    {
        return $this->queuePosition;
    }

    public function holdExpiresAt(): ?DateTimeImmutable
    {
        return $this->holdExpiresAt;
    }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'title_id' => $this->titleId,
            'title' => $this->title,
            'author' => $this->author,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'queue_position' => $this->queuePosition,
            'hold_expires_at' => $this->holdExpiresAt?->format('Y-m-d H:i:s'),
        ];
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        $normalized = is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : 0);
        if ($normalized <= 0) {
            throw new InvalidArgumentException('Invalid ' . $label . '.');
        }

        return $normalized;
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : null);
    }

    private static function nullableDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
