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
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SERVER['SCRIPT_NAME']);

        parent::tearDown();
    }

    public function testNormalizesTrailingSlashAndSeparatesQueryString(): void
    {
        $request = ServerRequest::fromGlobals();

        self::assertSame('GET', $request->method());
        self::assertSame('/api/books', $request->path());
        self::assertSame(['page' => '2'], $request->query());
    }

    public function testRemovesApplicationPrefixAfterApacheFrontControllerRewrite(): void
    {
        $_SERVER['REQUEST_URI'] = '/scan2borrow/student/dashboard?tab=history';
        $_SERVER['SCRIPT_NAME'] = '/scan2borrow/backend/public/index.php';

        $request = ServerRequest::fromGlobals();

        self::assertSame('/student/dashboard', $request->path());
        self::assertSame(['tab' => 'history'], $request->query());
        self::assertSame('/scan2borrow', $request->applicationPrefix());
        self::assertSame('/scan2borrow/login', $request->applicationPath('/login'));
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
