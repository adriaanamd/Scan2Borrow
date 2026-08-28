<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\GuestBorrowRequest;
use App\Application\DTO\GuestBorrowingResult;
use App\Application\DTO\GuestReturnVerificationRequest;
use App\Domain\Guest\GuestBorrowingStatus;
use App\Domain\Guest\VisitorAccount;
use App\Infrastructure\Persistence\GuestBorrowingRepositoryInterface;
use App\Infrastructure\Persistence\VisitorNotificationRepositoryInterface;

final class GuestBorrowingService
{
    public function __construct(
        private readonly GuestBorrowingRepositoryInterface $borrowings,
        private readonly VisitorNotificationRepositoryInterface $notifications,
        private readonly int $maximumActiveLoans,
    ) {
    }

    public function submitRequest(VisitorAccount $visitor, GuestBorrowRequest $request): GuestBorrowingResult
    {
        if ($request->governmentIdBarcode() === '' || !hash_equals($visitor->governmentIdBarcode(), $request->governmentIdBarcode())) {
            return GuestBorrowingResult::failure('Scan the same government-issued ID barcode used during registration.');
        }

        if ($request->verificationPhoto() === '') {
            return GuestBorrowingResult::failure('Capture a clear live verification photo before submitting.');
        }

        if (!$visitor->isEligibleForBorrowing()) {
            return GuestBorrowingResult::failure('Your registration is not eligible for borrowing.');
        }

        if (!$this->borrowings->isBookAvailable($request->bookId())) {
            return GuestBorrowingResult::failure('This book is no longer available.');
        }

        if ($this->borrowings->activeCount($visitor->id()) >= $this->maximumActiveLoans) {
            return GuestBorrowingResult::failure('You have reached the borrowing limit.');
        }

        $borrowingId = $this->borrowings->createPending($request, $visitor->id());
        $this->notifications->notifyVisitor($visitor->id(), 'Borrow request submitted', 'Your request is now pending staff approval.');
        $this->notifications->notifyStaff($borrowingId, 'Guest requested to borrow a book.');

        return GuestBorrowingResult::success($borrowingId, GuestBorrowingStatus::PENDING);
    }

    public function submitReturnVerification(VisitorAccount $visitor, GuestReturnVerificationRequest $request): GuestBorrowingResult
    {
        if ($request->returnPhoto() === '') {
            return GuestBorrowingResult::failure('Capture a live photo while holding the returned book.');
        }

        $borrowingId = $this->borrowings->findReleasedByBarcode($visitor->id(), $request->bookBarcode());
        if ($borrowingId === null) {
            return GuestBorrowingResult::failure('No active guest loan was found for that barcode.');
        }

        $this->borrowings->markReturnVerification($borrowingId, $request->returnPhoto());

        return GuestBorrowingResult::success(
            $borrowingId,
            GuestBorrowingStatus::RETURN_VERIFICATION_PENDING,
            'Return verification submitted. Please hand the book to the librarian for completion.',
        );
    }
}
