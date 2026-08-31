<?php

declare(strict_types=1);

namespace App\Domain\Audit;

use App\Domain\Book\BookStatus;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class AuditEvent
{
    /** @param array<string, scalar|null> $metadata */
    public function __construct(
        public int $copyId,
        public ?int $actorUserId,
        public AuditEventType $type,
        public ?string $fromStatus,
        public ?string $toStatus,
        public ?string $reason,
        public ?int $transactionId,
        public ?int $borrowingItemId,
        public ?int $printBatchId,
        public array $metadata,
        public DateTimeImmutable $occurredAt,
        public ?string $legacySource = null,
    ) {
        if ($this->copyId < 1) {
            throw new InvalidArgumentException('A valid copy is required for an audit event.');
        }
        $this->assertStatus($this->fromStatus);
        $this->assertStatus($this->toStatus);
        if ($this->type === AuditEventType::STATUS_CHANGED && $this->fromStatus === $this->toStatus) {
            throw new InvalidArgumentException('A status change must contain different statuses.');
        }
        if ($this->touchesLossOrDamage() && trim((string) $this->reason) === '') {
            throw new InvalidArgumentException('A reason is required for lost or damaged copy events.');
        }
        if ($this->reason !== null && mb_strlen(trim($this->reason)) > 500) {
            throw new InvalidArgumentException('Audit reason cannot exceed 500 characters.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'label' => $this->type->label(),
            'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s'),
            'actor_user_id' => $this->actorUserId,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'reason' => $this->reason,
            'transaction_id' => $this->transactionId,
            'borrowing_item_id' => $this->borrowingItemId,
            'print_batch_id' => $this->printBatchId,
            'metadata' => $this->metadata,
        ];
    }

    private function assertStatus(?string $status): void
    {
        if ($status !== null && BookStatus::tryFrom($status) === null) {
            throw new InvalidArgumentException('Unknown copy status in audit event.');
        }
    }

    private function touchesLossOrDamage(): bool
    {
        return in_array($this->fromStatus, ['Lost', 'Damaged'], true)
            || in_array($this->toStatus, ['Lost', 'Damaged'], true);
    }
}
