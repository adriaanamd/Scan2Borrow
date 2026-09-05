<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Infrastructure\Persistence\ReturnApprovalRepositoryInterface;
use DateTimeImmutable;

final class ReturnApprovalService
{
    /** @var list<string> */
    private const TYPES = ['borrower_item', 'legacy_borrowing', 'guest'];

    public function __construct(
        private readonly ReturnApprovalRepositoryInterface $repository,
        private readonly ClockInterface $clock,
        private readonly float $finePerDay,
        private readonly ?ReservationAvailabilityInterface $availability = null,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function pending(): array
    {
        return $this->repository->pending();
    }

    public function decide(string $type, int $id, string $action, int $staffId, string $note): ReturnResult
    {
        if ($id < 1 || !in_array($type, self::TYPES, true) || !in_array($action, ['approve', 'reject'], true)) {
            return ReturnResult::failure('Invalid return decision.');
        }
        if ($action === 'reject' && trim($note) === '') {
            return ReturnResult::failure('A reason is required to reject a return.');
        }

        $pending = $this->repository->findPending($type, $id);
        if ($pending === null) {
            return ReturnResult::failure('Return request not found.');
        }

        $fine = $action === 'approve' && $type !== 'guest' ? $this->fine($pending) : 0.0;
        $afterApprove = $action === 'approve' && $type !== 'guest'
            ? function () use ($pending): void {
                $this->advanceAvailability($pending);
            }
            : null;
        if (!$this->repository->decide($type, $id, $action, $staffId, $fine, trim($note), $afterApprove)) {
            return ReturnResult::failure('Return request is no longer pending.');
        }

        if ($action === 'approve') {
            return ReturnResult::success('Return approved. The book is now available.');
        }

        return ReturnResult::success('Return rejected. The loan remains active.');
    }

    /** @param array<string, mixed> $pending */
    private function fine(array $pending): float
    {
        $dueDateValue = $pending['due_date'] ?? null;
        if (!is_string($dueDateValue)) {
            return 0.0;
        }
        $dueDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dueDateValue);
        if ($dueDate === false) {
            return 0.0;
        }
        $due = $dueDate->setTime(23, 59, 59);
        $delta = $this->clock->now()->getTimestamp() - $due->getTimestamp();
        $days = $delta > 0 ? (int) floor($delta / 86400) : 0;

        return round($days * $this->finePerDay, 2);
    }

    /** @param array<string, mixed> $pending */
    private function advanceAvailability(array $pending): void
    {
        if ($this->availability === null || !is_numeric($pending['title_id'] ?? null) || !is_numeric($pending['copy_id'] ?? null)) {
            return;
        }

        $this->availability->advance(
            (int) $pending['title_id'],
            (int) $pending['copy_id'],
            $this->clock->now(),
        );
    }
}
