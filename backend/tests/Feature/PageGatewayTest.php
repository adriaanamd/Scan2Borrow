<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\PageController;
use App\Http\Requests\ServerRequest;
use App\Http\Routing\PageAccessAuthorizerInterface;
use App\Http\Routing\PageRoute;
use PHPUnit\Framework\TestCase;

final class PageGatewayTest extends TestCase
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

    public function testDeniedPageDoesNotReturnProtectedHtml(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'scan2borrow-page-');
        self::assertIsString($path);
        file_put_contents($path, '<main>protected dashboard</main>');

        try {
            $route = new PageRoute('/staff/dashboard', $path, ['admin', 'librarian']);
            $response = (new PageController(new DenyingPageAccessAuthorizer()))->__invoke(
                ServerRequest::fromGlobals(),
                $route,
            );

            self::assertSame(302, $response->statusCode());
            self::assertSame('/login', $response->headers()['Location']);
            self::assertSame('', $response->toString());
        } finally {
            unlink($path);
        }
    }

    public function testAllowedPageReturnsOnlyAllowlistedFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'scan2borrow-page-');
        self::assertIsString($path);
        file_put_contents($path, '<main>allowed dashboard</main>');

        try {
            $route = new PageRoute('/staff/dashboard', $path, ['admin', 'librarian']);
            $response = (new PageController(new AllowingPageAccessAuthorizer()))->__invoke(
                ServerRequest::fromGlobals(),
                $route,
            );

            self::assertSame(200, $response->statusCode());
            self::assertSame('<main>allowed dashboard</main>', $response->toString());
        } finally {
            unlink($path);
        }
    }
}

final class DenyingPageAccessAuthorizer implements PageAccessAuthorizerInterface
{
    public function allows(ServerRequest $request, PageRoute $route): bool
    {
        return false;
    }

    public function denialLocation(ServerRequest $request, PageRoute $route): string
    {
        return '/login';
    }
}

final class AllowingPageAccessAuthorizer implements PageAccessAuthorizerInterface
{
    public function allows(ServerRequest $request, PageRoute $route): bool
    {
        return true;
    }

    public function denialLocation(ServerRequest $request, PageRoute $route): string
    {
        return '/login';
    }
}
