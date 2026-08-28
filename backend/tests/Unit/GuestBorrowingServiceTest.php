<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\DTO\GuestBorrowRequest;
use App\Application\DTO\GuestReturnVerificationRequest;
use App\Application\Services\GuestBorrowingService;
use App\Domain\Guest\GuestBorrowingStatus;
use App\Domain\Guest\VisitorAccount;
use App\Infrastructure\Persistence\GuestBorrowingRepositoryInterface;
use App\Infrastructure\Persistence\VisitorNotificationRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GuestBorrowingServiceTest extends TestCase
{
    private VisitorAccount $visitor;

    /** @var GuestBorrowingRepositoryInterface&MockObject */
    private GuestBorrowingRepositoryInterface $borrowings;

    /** @var VisitorNotificationRepositoryInterface&MockObject */
    private VisitorNotificationRepositoryInterface $notifications;

    protected function setUp(): void
    {
        $this->visitor = new VisitorAccount(7, 'GOV-777', 'Active', 'Visitor One');
        $this->borrowings = $this->createMock(GuestBorrowingRepositoryInterface::class);
        $this->notifications = $this->createMock(VisitorNotificationRepositoryInterface::class);
    }

    public function testRequestRequiresMatchingGovernmentIdAndVerificationPhoto(): void
    {
        $service = new GuestBorrowingService($this->borrowings, $this->notifications, 3);

        $missingId = $service->submitRequest($this->visitor, new GuestBorrowRequest(12, '', 'photo-data'));
        self::assertFalse($missingId->isSuccessful());
        self::assertSame('Scan the same government-issued ID barcode used during registration.', $missingId->message());

        $missingPhoto = $service->submitRequest($this->visitor, new GuestBorrowRequest(12, 'GOV-777', ''));
        self::assertFalse($missingPhoto->isSuccessful());
        self::assertSame('Capture a clear live verification photo before submitting.', $missingPhoto->message());
    }

    public function testRequestPreservesEligibilityAvailabilityLimitAndPendingStatus(): void
    {
        $this->borrowings->expects(self::once())->method('isBookAvailable')->with(12)->willReturn(true);
        $this->borrowings->expects(self::once())->method('activeCount')->with(7)->willReturn(0);
        $this->borrowings->expects(self::once())->method('createPending')->with(
            self::callback(static fn (GuestBorrowRequest $request): bool => $request->bookId() === 12 && $request->governmentIdBarcode() === 'GOV-777' && $request->verificationPhoto() === 'photo-data'),
        )->willReturn(41);
        $this->notifications->expects(self::once())->method('notifyVisitor')->with(7, 'Borrow request submitted', self::stringContains('pending staff approval'));
        $this->notifications->expects(self::once())->method('notifyStaff')->with(41, self::stringContains('requested to borrow'));

        $service = new GuestBorrowingService($this->borrowings, $this->notifications, 3);
        $result = $service->submitRequest($this->visitor, new GuestBorrowRequest(12, 'GOV-777', 'photo-data'));

        self::assertTrue($result->isSuccessful());
        self::assertSame(41, $result->borrowingId());
        self::assertSame(GuestBorrowingStatus::PENDING, $result->status());
    }

    public function testRequestRejectsIneligibleVisitorUnavailableBookAndFullLimit(): void
    {
        $service = new GuestBorrowingService($this->borrowings, $this->notifications, 3);
        $suspended = new VisitorAccount(7, 'GOV-777', 'Suspended', 'Visitor One');

        $ineligible = $service->submitRequest($suspended, new GuestBorrowRequest(12, 'GOV-777', 'photo-data'));
        self::assertSame('Your registration is not eligible for borrowing.', $ineligible->message());

        $this->borrowings->method('activeCount')->willReturn(0);
        $this->borrowings->method('isBookAvailable')->willReturn(false);
        $unavailable = $service->submitRequest($this->visitor, new GuestBorrowRequest(12, 'GOV-777', 'photo-data'));
        self::assertSame('This book is no longer available.', $unavailable->message());

        $this->borrowings->method('isBookAvailable')->willReturn(true);
        $this->borrowings->method('activeCount')->willReturn(3);
        $full = $service->submitRequest($this->visitor, new GuestBorrowRequest(12, 'GOV-777', 'photo-data'));
        self::assertSame('You have reached the borrowing limit.', $full->message());
    }

    public function testReturnVerificationRequiresEvidenceAndReleasedOwnedLoan(): void
    {
        $this->borrowings->expects(self::once())->method('findReleasedByBarcode')->with(7, 'BK-12')->willReturn(99);
        $this->borrowings->expects(self::once())->method('markReturnVerification')->with(99, 'return-photo');

        $service = new GuestBorrowingService($this->borrowings, $this->notifications, 3);
        $missingPhoto = $service->submitReturnVerification($this->visitor, new GuestReturnVerificationRequest('BK-12', ''));
        self::assertSame('Capture a live photo while holding the returned book.', $missingPhoto->message());

        $result = $service->submitReturnVerification($this->visitor, new GuestReturnVerificationRequest('BK-12', 'return-photo'));
        self::assertTrue($result->isSuccessful());
        self::assertSame(GuestBorrowingStatus::RETURN_VERIFICATION_PENDING, $result->status());
        self::assertSame('Return verification submitted. Please hand the book to the librarian for completion.', $result->message());
    }

    public function testReturnVerificationRejectsUnknownOwnedLoan(): void
    {
        $this->borrowings->method('findReleasedByBarcode')->willReturn(null);
        $service = new GuestBorrowingService($this->borrowings, $this->notifications, 3);

        $result = $service->submitReturnVerification($this->visitor, new GuestReturnVerificationRequest('BK-404', 'return-photo'));

        self::assertFalse($result->isSuccessful());
        self::assertSame('No active guest loan was found for that barcode.', $result->message());
    }
}
