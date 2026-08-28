<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\DTO\GuestBorrowRequest;
use App\Application\Services\CsrfService;
use App\Application\Services\GuestBorrowingService;
use App\Application\Services\GuestPortalService;
use App\Domain\Guest\VisitorAccount;
use App\Http\Controllers\GuestBorrowingController;
use App\Http\Requests\ServerRequest;
use App\Infrastructure\Persistence\GuestBorrowingRepositoryInterface;
use App\Infrastructure\Persistence\GuestPortalRepositoryInterface;
use App\Infrastructure\Persistence\VisitorNotificationRepositoryInterface;
use App\Infrastructure\Session\SessionStoreInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GuestBorrowingControllerTest extends TestCase
{
    /** @var GuestPortalRepositoryInterface&MockObject */
    private GuestPortalRepositoryInterface $portalRepository;

    /** @var GuestBorrowingRepositoryInterface&MockObject */
    private GuestBorrowingRepositoryInterface $borrowingRepository;

    /** @var VisitorNotificationRepositoryInterface&MockObject */
    private VisitorNotificationRepositoryInterface $notificationRepository;

    /** @var SessionStoreInterface&MockObject */
    private SessionStoreInterface $sessionStore;

    protected function setUp(): void
    {
        $this->portalRepository = $this->createMock(GuestPortalRepositoryInterface::class);
        $this->borrowingRepository = $this->createMock(GuestBorrowingRepositoryInterface::class);
        $this->notificationRepository = $this->createMock(VisitorNotificationRepositoryInterface::class);
        $this->sessionStore = $this->createMock(SessionStoreInterface::class);
    }

    public function testUnauthenticatedGuestCannotUsePortalApi(): void
    {
        $controller = $this->controller(null);

        $response = $controller->dashboard($this->request('GET', []));

        self::assertSame(401, $response->statusCode());
        self::assertStringContainsString('Guest authentication required.', $response->toString());
    }

    public function testAuthenticatedDashboardPreservesSummaryAndNotificationKeys(): void
    {
        $this->portalRepository->method('dashboardSummary')->willReturn(['active' => 2]);
        $this->portalRepository->method('notifications')->willReturn([
            ['id' => 4, 'title' => 'Approved', 'message' => 'Ready', 'created_at' => 'Aug 28, 2026'],
        ]);
        $controller = $this->controller(new VisitorAccount(7, 'GOV-777', 'Active', 'Visitor One'));

        $response = $controller->dashboard($this->request('GET', []));

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('"active":2', $response->toString());
        self::assertStringContainsString('"created_at":"Aug 28, 2026"', $response->toString());
    }

    public function testBorrowValidatesCsrfAndUsesAuthenticatedVisitor(): void
    {
        $this->sessionStore->method('get')->willReturn('csrf-token');
        $this->borrowingRepository->method('isBookAvailable')->willReturn(true);
        $this->borrowingRepository->method('activeCount')->willReturn(0);
        $this->borrowingRepository->method('createPending')->willReturn(19);
        $this->notificationRepository->expects(self::once())->method('notifyVisitor');
        $this->notificationRepository->expects(self::once())->method('notifyStaff');
        $controller = $this->controller(new VisitorAccount(7, 'GOV-777', 'Active', 'Visitor One'));

        $response = $controller->borrow($this->request('POST', [
            'csrf' => 'csrf-token', 'book_id' => '12', 'government_id_barcode' => 'GOV-777', 'verification_photo' => 'photo-data',
        ]));

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('"borrowing_id":19', $response->toString());
    }

    private function controller(?VisitorAccount $visitor): GuestBorrowingController
    {
        $store = $this->sessionStore;
        $csrf = new CsrfService($store);
        $identity = new class($visitor) implements \App\Infrastructure\Session\GuestIdentityProviderInterface {
            public function __construct(private readonly ?VisitorAccount $visitor) {}
            public function current(): ?VisitorAccount { return $this->visitor; }
        };

        return new GuestBorrowingController(
            $identity,
            new GuestPortalService($this->portalRepository),
            new GuestBorrowingService($this->borrowingRepository, $this->notificationRepository, 3),
            $csrf,
        );
    }

    /** @param array<string, mixed> $body */
    private function request(string $method, array $body): ServerRequest
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = '/api/guest/dashboard';
        $GLOBALS['_POST'] = $body;

        return ServerRequest::fromGlobals();
    }
}
