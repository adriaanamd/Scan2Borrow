<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Requests\ServerRequest;
use App\Http\Responses\JsonResponse;
use PHPUnit\Framework\TestCase;

final class HttpPrimitivesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/books/?page=2';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

        parent::tearDown();
    }

    public function testNormalizesTrailingSlashAndSeparatesQueryString(): void
    {
        $request = ServerRequest::fromGlobals();

        self::assertSame('GET', $request->method());
        self::assertSame('/api/books', $request->path());
        self::assertSame(['page' => '2'], $request->query());
    }

    public function testSerializesSuccessEnvelopeAsJson(): void
    {
        $response = new JsonResponse(
            200,
            ['ok' => true, 'data' => []],
        );

        self::assertSame(200, $response->statusCode());
        self::assertSame(['Content-Type' => 'application/json; charset=utf-8'], $response->headers());
        self::assertSame('{"ok":true,"data":[]}', $response->toString());
    }
}
