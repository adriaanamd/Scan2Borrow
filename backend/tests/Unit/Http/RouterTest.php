<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Requests\ServerRequest;
use App\Http\Routing\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/not-a-real-route/';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

        parent::tearDown();
    }

    public function testUnknownRouteReturnsNotFoundJsonEnvelope(): void
    {
        $response = (new Router([]))->dispatch(ServerRequest::fromGlobals());

        self::assertSame(404, $response->statusCode());
        self::assertSame(
            ['ok' => false, 'errors' => ['Route not found.']],
            json_decode($response->toString(), true, 512, JSON_THROW_ON_ERROR),
        );
    }
}
