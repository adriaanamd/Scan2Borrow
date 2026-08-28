<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Application\Services\AuthenticationService;
use App\Application\Services\BookQueryService;
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
use App\Application\Services\ReturnService;
use App\Application\Services\SystemClock;
use App\Application\Services\SessionService;
use App\Domain\Auth\AuthorizationPolicy;
use App\Domain\Borrowing\BorrowingPolicy;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BorrowerController;
use App\Http\Controllers\GuestAuthController;
use App\Http\Controllers\GuestBorrowingController;
use App\Http\Controllers\GuestDetailsController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\RegistrationController;
use App\Http\Middleware\AuthorizationMiddleware;
use App\Http\Middleware\SessionPageAccessAuthorizer;
use App\Http\Responses\ResponseEmitter;
use App\Http\Routing\AuthRouteTable;
use App\Http\Routing\BookRouteTable;
use App\Http\Routing\BorrowerRouteTable;
use App\Http\Routing\GuestRouteTable;
use App\Http\Routing\PageRouteTable;
use App\Http\Routing\Router;
use App\Infrastructure\Database\DatabaseConfig;
use App\Infrastructure\Database\PdoConnectionFactory;
use App\Infrastructure\Persistence\PdoUserRepository;
use App\Infrastructure\Persistence\PdoBookRepository;
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
        $otp = new OtpService(new PdoOtpRepository($pdo), new SystemClock(), new NullSmsSender());
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
        $bookController = new BookController($sessions, new BookQueryService(new PdoBookRepository($pdo)));
        $borrowings = new PdoBorrowingRepository($pdo);
        $borrowerController = new BorrowerController(
            $sessions,
            $csrf,
            new BorrowingService($borrowings, new BorrowingPolicy(3, 7, 30, true), new SystemClock()),
            new ReturnService($borrowings, new SystemClock(), 20.0),
            new PdoBorrowerPortalRepository($pdo),
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
        $apiRouter = new Router(array_merge(
            (new AuthRouteTable())->routes($authController, $registration),
            (new BookRouteTable())->routes($bookController),
            (new BorrowerRouteTable())->routes($borrowerController),
            (new GuestRouteTable())->routes($guestBorrowing, $guestDetails, $guestAuth),
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
            new PageRouteTable(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'pages'),
            $pageController,
            new ResponseEmitter(),
        );
    }
}
