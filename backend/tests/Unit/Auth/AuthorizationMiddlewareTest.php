<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Domain\Auth\AuthorizationPolicy;
use App\Domain\Auth\Principal;
use App\Domain\Auth\Role;
use App\Http\Middleware\AuthorizationMiddleware;
use App\Http\RequestContext;
use App\Http\Requests\ServerRequest;
use PHPUnit\Framework\TestCase;

final class AuthorizationMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/staff/dashboard';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

        parent::tearDown();
    }

    public function testMissingPrincipalRedirectsToLogin(): void
    {
        $middleware = new AuthorizationMiddleware(new AuthorizationPolicy());
        $context = new RequestContext(ServerRequest::fromGlobals(), null);

        $response = $middleware->guard($context, [Role::ADMIN]);

        self::assertNotNull($response);
        self::assertSame(302, $response->statusCode());
        self::assertSame('/login', $response->headers()['Location']);
    }

    public function testWrongRoleRedirectsToItsCurrentHome(): void
    {
        $middleware = new AuthorizationMiddleware(new AuthorizationPolicy());
        $context = new RequestContext(
            ServerRequest::fromGlobals(),
            new Principal(42, Role::STUDENT),
        );

        $response = $middleware->guard($context, [Role::ADMIN, Role::LIBRARIAN]);

        self::assertNotNull($response);
        self::assertSame('/student/dashboard', $response->headers()['Location']);
    }

    public function testAllowedRoleHasNoRedirect(): void
    {
        $middleware = new AuthorizationMiddleware(new AuthorizationPolicy());
        $context = new RequestContext(
            ServerRequest::fromGlobals(),
            new Principal(7, Role::LIBRARIAN),
        );

        self::assertNull($middleware->guard($context, [Role::ADMIN, Role::LIBRARIAN]));
    }

    public function testGuestOnlyGuardAcceptsGuestAndRejectsAuthenticatedBorrower(): void
    {
        $middleware = new AuthorizationMiddleware(new AuthorizationPolicy());
        $request = ServerRequest::fromGlobals();

        self::assertNull($middleware->guard(
            new RequestContext($request, new Principal(1, Role::GUEST)),
            [],
            true,
        ));

        $response = $middleware->guard(
            new RequestContext($request, new Principal(2, Role::STUDENT)),
            [],
            true,
        );

        self::assertNotNull($response);
        self::assertSame('/student/dashboard', $response->headers()['Location']);
    }
}
