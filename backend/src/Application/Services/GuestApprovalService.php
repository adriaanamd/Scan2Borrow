<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\GuestReviewResult;
use App\Infrastructure\Persistence\GuestApprovalRepositoryInterface;
use App\Infrastructure\Persistence\VisitorNotificationRepositoryInterface;

final class GuestApprovalService
{
    public function __construct(
        private readonly GuestApprovalRepositoryInterface $requests,
        private readonly VisitorNotificationRepositoryInterface $notifications,
    ) {
    }

    public function approve(int $requestId, string $notes): GuestReviewResult
    {
        $request = $this->requests->findPending($requestId);
        if ($request === null) {
            return GuestReviewResult::failure('Request not found.');
        }

        $this->requests->approve($requestId, $notes);
        $title = $request['title'];
        $this->notifications->notifyVisitor(
            $request['visitor_id'],
            'Borrow request approved',
            sprintf('"%s" has been approved and released.', $title),
        );

        return GuestReviewResult::success('Released', 'Approved â€” the guest can now view and print their receipt.');
    }

    public function reject(int $requestId, string $reason): GuestReviewResult
    {
        if (trim($reason) === '') {
            return GuestReviewResult::failure('A reason is required to reject a request.');
        }

        $request = $this->requests->findPending($requestId);
        if ($request === null) {
            return GuestReviewResult::failure('Request not found.');
        }

        $this->requests->reject($requestId, $reason);
        $this->notifications->notifyVisitor(
            $request['visitor_id'],
            'Borrow request rejected',
            sprintf('Your request for "%s" was rejected. Reason: %s', $request['title'], $reason),
        );

        return GuestReviewResult::success('Rejected', 'Request rejected.');
    }
}
