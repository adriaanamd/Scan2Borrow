<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Application\Services\AuthenticationService;
use App\Application\Services\CsrfService;
use App\Application\Services\SessionService;
use App\Domain\Auth\AuthorizationPolicy;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PageController;
use App\Http\Middleware\AuthorizationMiddleware;
use App\Http\Middleware\SessionPageAccessAuthorizer;
use App\Http\Responses\ResponseEmitter;
use App\Http\Routing\AuthRouteTable;
use App\Http\Routing\PageRouteTable;
use App\Http\Routing\Router;
use App\Infrastructure\Database\DatabaseConfig;
use App\Infrastructure\Database\PdoConnectionFactory;
use App\Infrastructure\Persistence\PdoUserRepository;
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
        $authentication = new AuthenticationService(new PdoUserRepository($pdo), $sessions);
        $authController = new AuthController($sessions, $csrf, $authentication);
        $apiRouter = new Router((new AuthRouteTable())->routes($authController));
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
