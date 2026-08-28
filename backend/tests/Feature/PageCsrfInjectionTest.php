<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Services\CsrfService;
use App\Http\Controllers\PageController;
use App\Http\Requests\ServerRequest;
use App\Http\Routing\PageRoute;
use App\Infrastructure\Session\SessionStoreInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PageCsrfInjectionTest extends TestCase
{
    /** @var SessionStoreInterface&MockObject */
    private SessionStoreInterface $store;

    protected function setUp(): void
    {
        $this->store = $this->createMock(SessionStoreInterface::class);
        $this->store->method('get')->willReturn(str_repeat('a', 64));
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/student/dashboard';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
    }

    public function testAllowedStaticPageReceivesSessionCsrfToken(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'scan2borrow-csrf-');
        self::assertIsString($path);
        file_put_contents($path, '<meta name="csrf" content="">');

        try {
            $response = (new PageController(new AllowingPageAccessAuthorizer(), new CsrfService($this->store)))->__invoke(
                ServerRequest::fromGlobals(),
                new PageRoute('/student/dashboard', $path, ['student']),
            );

            self::assertStringContainsString('content="' . str_repeat('a', 64) . '"', $response->toString());
        } finally {
            unlink($path);
        }
    }
}
