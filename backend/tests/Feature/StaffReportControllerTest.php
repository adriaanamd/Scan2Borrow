<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Services\CsrfService;
use App\Application\Services\SessionService;
use App\Domain\Auth\Principal;
use App\Domain\Auth\Role;
use App\Http\Controllers\StaffController;
use App\Http\Requests\ServerRequest;
use App\Infrastructure\Persistence\StaffRepositoryInterface;
use App\Infrastructure\Session\SessionStoreInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StaffReportControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SERVER['SCRIPT_NAME']);
        $GLOBALS['_POST'] = [];
        parent::tearDown();
    }

    public function testReportForwardsCategoryAndReturnsCategoryOptions(): void
    {
        /** @var StaffRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(StaffRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('report')
            ->with('borrowed', '2026-08-01', '2026-08-31', 'Computer Science')
            ->willReturn(['label' => 'Borrowed Books', 'headers' => ['Book'], 'data' => [['Clean Code']]]);
        $repository->expects(self::once())
            ->method('reportCategories')
            ->willReturn(['Computer Science', 'English']);

        $response = $this->controller($repository)->report($this->request('GET', '/api/staff/reports?type=borrowed&from=2026-08-01&to=2026-08-31&category=Computer%20Science'));
        /** @var array{data: array{report: array<string, mixed>, categories: list<string>}} $payload */
        $payload = json_decode($response->toString(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->statusCode());
        self::assertSame(['Computer Science', 'English'], $payload['data']['categories']);
        self::assertSame([['Clean Code']], $payload['data']['report']['data']);
    }

    public function testExportForwardsCategoryAndReturnsFilteredCsv(): void
    {
        /** @var StaffRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(StaffRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('report')
            ->with('inventory', '', '', 'English')
            ->willReturn(['label' => 'Inventory Status', 'headers' => ['Title', 'Category'], 'data' => [['English Grammar', 'English']]]);

        $response = $this->controller($repository)->exportReport($this->request('GET', '/api/staff/reports/export?type=inventory&category=English'));

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('"English Grammar",English', $response->toString());
        self::assertSame('text/csv; charset=utf-8', $response->headers()['Content-Type']);
    }

    public function testReportRemainsStaffProtected(): void
    {
        /** @var StaffRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(StaffRepositoryInterface::class);
        $repository->expects(self::never())->method('report');
        $response = $this->controller($repository, false)->report($this->request('GET', '/api/staff/reports?category=English'));

        self::assertSame(401, $response->statusCode());
    }

    private function controller(StaffRepositoryInterface $repository, bool $authenticated = true): StaffController
    {
        $store = new StaffReportControllerSessionStore();
        $sessions = new SessionService($store);
        if ($authenticated) {
            $sessions->login(new Principal(1, Role::ADMIN));
        }

        return new StaffController($sessions, $repository, new CsrfService($store));
    }

    private function request(string $method, string $uri): ServerRequest
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['SCRIPT_NAME'] = '/scan2borrow/backend/public/index.php';
        $GLOBALS['_POST'] = [];

        return ServerRequest::fromGlobals();
    }
}

final class StaffReportControllerSessionStore implements SessionStoreInterface
{
    /** @var array<string, mixed> */
    private array $values = [];

    public function start(): void {}
    public function regenerate(): void {}
    public function id(): string { return 'staff-report-controller-test'; }
    public function get(string $key): mixed { return $this->values[$key] ?? null; }
    public function set(string $key, mixed $value): void { $this->values[$key] = $value; }
    public function remove(string $key): void { unset($this->values[$key]); }
}
