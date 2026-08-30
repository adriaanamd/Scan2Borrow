<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\RenewalDecisionRequest;
use App\Application\DTO\RenewalResult;
use App\Infrastructure\Persistence\CirculationNotificationRepositoryInterface;
use App\Infrastructure\Persistence\RenewalRepositoryInterface;

final readonly class RenewalApprovalService
{
    public function __construct(
        private RenewalRepositoryInterface $repository,
        private CirculationNotificationRepositoryInterface $notifications,
        private ClockInterface $clock,
    ) {
    }

    public function decide(RenewalDecisionRequest $request): RenewalResult
    {
        $record = $request->action === 'approve'
            ? $this->repository->approve($request->renewalId, $request->staffId, $request->note, $this->clock->now())
            : $this->repository->reject($request->renewalId, $request->staffId, $request->note, $this->clock->now());
        if ($record === null) return RenewalResult::failure('This renewal is no longer awaiting a decision.');

        if ($request->action === 'approve') {
            $this->notifications->notifyBorrower(
                $record->userId(),
                'renewal_approved',
                'Renewal approved',
                'Your renewal for "' . $record->title() . '" is approved through ' . $record->requestedDueDate()->format('M d, Y') . '.',
                $record->id(),
            );
            return RenewalResult::success('Renewal approved through ' . $record->requestedDueDate()->format('Y-m-d') . '.', $record);
        }

        $this->notifications->notifyBorrower(
            $record->userId(),
            'renewal_rejected',
            'Renewal request declined',
            'Your renewal request for "' . $record->title() . '" was declined.',
            $record->id(),
        );

        return RenewalResult::success('Renewal request rejected.', $record);
    }
}
