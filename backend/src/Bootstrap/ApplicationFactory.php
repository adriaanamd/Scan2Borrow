<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Application\Services\AuthenticationService;
use App\Application\Services\BookQueryService;
use App\Application\Services\BookArchiveService;
use App\Application\Services\BorrowerNotificationService;
use App\Application\Services\BookMutationService;
use App\Application\Services\BorrowingService;
use App\Application\Services\CsrfService;
use App\Application\Services\GuestProfileCompletionService;
use App\Application\Services\GuestProfileService;
use App\Application\Services\GuestRegistrationCompletionService;
use App\Application\Services\GuestRegistrationService;
use App\Application\Services\LocalPhotoStorage;
use App\Application\Services\NullSmsSender;
use App\Application\Services\OtpService;
use App\Application\Services\RegistrationCompletionService;
use App\Application\Services\RegistrationService;
use App\Application\Services\SmtpEmailSender;
use App\Application\Services\ReturnService;
use App\Application\Services\SystemClock;
use App\Application\Services\SessionService;
use App\Domain\Auth\AuthorizationPolicy;
use App\Domain\Borrowing\BorrowingPolicy;
use App\Application\Validators\BookMutationValidator;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BookCopyController;
use App\Http\Controllers\BarcodePrintController;
use App\Http\Controllers\BorrowerController;
use App\Http\Controllers\GuestAuthController;
use App\Http\Controllers\GuestBorrowingController;
use App\Http\Controllers\GuestDetailsController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\ApiDocumentationController;
use App\Http\Middleware\AuthorizationMiddleware;
use App\Http\Middleware\SessionPageAccessAuthorizer;
use App\Http\Responses\ResponseEmitter;
use App\Http\Routing\AuthRouteTable;
use App\Http\Routing\BookRouteTable;
use App\Http\Routing\BarcodePrintRouteTable;
use App\Http\Routing\BorrowerRouteTable;
use App\Http\Routing\GuestRouteTable;
use App\Http\Routing\PageRouteTable;
use App\Http\Routing\ReservationRouteTable;
use App\Http\Routing\Router;
use App\Http\Routing\StaffRouteTable;
use App\Http\Documentation\ApiEndpointCatalog;
use App\Infrastructure\Database\DatabaseConfig;
use App\Infrastructure\Database\PdoConnectionFactory;
use App\Infrastructure\Persistence\PdoUserRepository;
use App\Infrastructure\Persistence\PdoBookRepository;
use App\Infrastructure\Persistence\PdoBarcodePrintRepository;
use App\Infrastructure\Persistence\PdoBorrowerPortalRepository;
use App\Infrastructure\Persistence\PdoBorrowingRepository;
use App\Infrastructure\Persistence\PdoGuestIdentityRepository;
use App\Infrastructure\Persistence\PdoGuestPortalRepository;
use App\Infrastructure\Persistence\PdoGuestBorrowingRepository;
use App\Infrastructure\Persistence\PdoOtpRepository;
use App\Infrastructure\Persistence\PdoRegistrationAccountRepository;
use App\Infrastructure\Persistence\PdoVisitorDetailsRepository;
use App\Infrastructure\Persistence\PdoVisitorNotificationRepository;
use App\Infrastructure\Persistence\PdoVisitorRegistrationRepository;
use App\Infrastructure\Session\PdoGuestIdentityProvider;
use App\Infrastructure\Session\NativeSessionStore;

final class ApplicationFactory
{
    public static function create(string $environment = 'production'): Application
    {
        if ($environment === 'testing') {
            return new Application($environment);
        }

        $pdo = (new PdoConnectionFactory())->create(DatabaseConfig::fromEnvironment());
        $sessions = new SessionService(new NativeSessionStore());
        $csrf = new CsrfService(new NativeSessionStore());
        $otp = new OtpService(new PdoOtpRepository($pdo), new SystemClock(), new NullSmsSender(), new SmtpEmailSender());
        $registrationAccounts = new PdoRegistrationAccountRepository($pdo);
        $registration = new RegistrationController(
            new RegistrationService(new \App\Application\Validators\RegistrationValidator(), $registrationAccounts, $otp),
            new RegistrationCompletionService($otp, $registrationAccounts, new LocalPhotoStorage(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'uploads', '/scan2borrow/uploads')),
            $otp,
            $sessions,
            $csrf,
        );
        $guestVisitors = new PdoVisitorRegistrationRepository($pdo);
        $authentication = new AuthenticationService(new PdoUserRepository($pdo), $sessions, new PdoGuestIdentityRepository($pdo));
        $authController = new AuthController($sessions, $csrf, $authentication);
        $bookRepository = new PdoBookRepository($pdo);
        $bookController = new BookController(
            $sessions,
            new BookQueryService($bookRepository),
            new BookMutationService(new BookMutationValidator(), $bookRepository),
            new BookArchiveService($bookRepository),
            $csrf,
        );
        $bookCopyController = new BookCopyController(
            $sessions,
            $bookRepository,
            new \App\Application\Validators\BookCopyMutationValidator(),
            $csrf,
        );
        $barcodePrintController = new BarcodePrintController(
            $sessions,
            new \App\Application\Services\BarcodePrintService(new PdoBarcodePrintRepository($pdo)),
            $csrf,
        );
        $borrowings = new PdoBorrowingRepository($pdo);
        $borrowerController = new BorrowerController(
            $sessions,
            $csrf,
            new BorrowingService($borrowings, new BorrowingPolicy(3, 7, 30, true), new SystemClock()),
            new ReturnService($borrowings, new SystemClock(), 20.0),
            new PdoBorrowerPortalRepository($pdo),
        );
        $reservationController = new ReservationController(
            $sessions,
            $csrf,
            new \App\Application\Services\ReservationService(new \App\Infrastructure\Persistence\PdoHoldRepository($pdo)),
        );
        $guestIdentity = new PdoGuestIdentityProvider($sessions, $pdo);
        $guestBorrowing = new GuestBorrowingController(
            $guestIdentity,
            new \App\Application\Services\GuestPortalService(new PdoGuestPortalRepository($pdo)),
            new \App\Application\Services\GuestBorrowingService(new PdoGuestBorrowingRepository($pdo), new PdoVisitorNotificationRepository($pdo), 3),
            $csrf,
        );
        $visitorDetails = new PdoVisitorDetailsRepository($pdo);
        $guestDetails = new GuestDetailsController(
            $guestIdentity,
            $visitorDetails,
            new GuestProfileService($visitorDetails, $otp),
            $sessions,
            $csrf,
        );
        $guestAuth = new GuestAuthController(
            new GuestRegistrationService(new \App\Application\Validators\GuestRegistrationValidator(new SystemClock()), $guestVisitors, $otp),
            new GuestRegistrationCompletionService($otp, $guestVisitors, $sessions),
            $otp,
            $sessions,
            $csrf,
            new GuestProfileCompletionService($otp, $visitorDetails),
        );
        $staffController = new StaffController(
            $sessions,
            new \App\Infrastructure\Persistence\PdoStaffRepository($pdo),
            $csrf,
            new \App\Application\Services\GuestApprovalService(
                new \App\Infrastructure\Persistence\PdoGuestApprovalRepository($pdo),
                new \App\Infrastructure\Persistence\PdoVisitorNotificationRepository($pdo),
            ),
            new LocalPhotoStorage(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'uploads', '/scan2borrow/uploads'),
            new BorrowerNotificationService(
                new \App\Infrastructure\Persistence\PdoStaffRepository($pdo),
                new SmtpEmailSender(),
            ),
        );
        $apiDocumentationController = new ApiDocumentationController($sessions, new ApiEndpointCatalog());
        $apiRouter = new Router(array_merge(
            (new AuthRouteTable())->routes($authController, $registration),
            (new BookRouteTable())->routes($bookController, $bookCopyController),
            (new BarcodePrintRouteTable())->routes($barcodePrintController),
            (new BorrowerRouteTable())->routes($borrowerController),
            (new ReservationRouteTable())->routes($reservationController),
            (new GuestRouteTable())->routes($guestBorrowing, $guestDetails, $guestAuth),
            (new StaffRouteTable())->routes($staffController, $apiDocumentationController),
        ));
        $policy = new AuthorizationPolicy();
        $pageController = new PageController(new SessionPageAccessAuthorizer(
            $sessions,
            $policy,
            new AuthorizationMiddleware($policy),
        ), $csrf);

        return new Application(
            $environment,
            $apiRouter,
            new PageRouteTable(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend'),
            $pageController,
            new ResponseEmitter(),
        );
    }
}
