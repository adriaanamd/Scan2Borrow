<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\DTO\CopyHistoryResult;
use App\Application\Services\CopyHistoryService;
use App\Domain\Auth\Role;
use App\Domain\Auth\SessionIdentity;
use App\Http\Controllers\CopyHistoryController;
use App\Http\Requests\ServerRequest;
use App\Infrastructure\Persistence\AuditEventRepositoryInterface;
use App\Infrastructure\Session\SessionStoreInterface;
use App\Application\Services\SessionService;
use PHPUnit\Framework\TestCase;

final class CopyHistoryControllerTest extends TestCase
{
    public function testNonStaffCannotReadHistory(): void
    {
        $controller = $this->controller(new SessionIdentity(3, Role::STUDENT, 'student'));
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/staff/copy-history?barcode=BC-1';
        $_GET = ['barcode' => 'BC-1'];

        self::assertSame(401, $controller->index(ServerRequest::fromGlobals())->statusCode());
    }

    public function testStaffReceivesCopyHistoryAndBlankBarcodeIsRejected(): void
    {
        $controller = $this->controller(new SessionIdentity(7, Role::LIBRARIAN, 'staff'));
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/staff/copy-history?barcode=BC-1';
        $_GET = ['barcode' => 'BC-1'];

        $response = $controller->index(ServerRequest::fromGlobals());

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('status_changed', $response->toString());

        $_GET = [];
        $_SERVER['REQUEST_URI'] = '/api/staff/copy-history';
        self::assertSame(422, $controller->index(ServerRequest::fromGlobals())->statusCode());
    }

    private function controller(SessionIdentity $identity): CopyHistoryController
    {
        return new CopyHistoryController(
            new SessionService(new class($identity) implements SessionStoreInterface {
                public function __construct(private ?SessionIdentity $identity) {}
                public function start(): void {}
                public function regenerate(): void {}
                public function id(): string { return 'copy-history-test'; }
                public function get(string $key): mixed { return $key === 'scan2borrow.identity' ? $this->identity : null; }
                public function set(string $key, mixed $value): void {}
                public function remove(string $key): void {}
            }),
            new CopyHistoryService(new class implements AuditEventRepositoryInterface {
                public function record(\App\Domain\Audit\AuditEvent $event): void {}
                public function findCopyHistory(string $barcode): ?CopyHistoryResult
                {
                    return new CopyHistoryResult(
                        ['barcode' => $barcode, 'title' => 'Clean Code', 'status' => 'Available'],
                        [['type' => 'status_changed', 'label' => 'Status changed', 'actor' => 'Staff', 'occurred_at' => '2026-08-31 14:32:00']],
                    );
                }
            }),
        );
    }
}
