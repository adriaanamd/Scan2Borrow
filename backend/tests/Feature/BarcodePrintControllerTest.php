<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\DTO\BarcodePrintBatch;
use App\Application\Services\BarcodePrintService;
use App\Application\Services\CsrfService;
use App\Application\Services\SessionService;
use App\Domain\Auth\Role;
use App\Http\Controllers\BarcodePrintController;
use App\Http\Requests\ServerRequest;
use App\Infrastructure\Persistence\BarcodePrintRepositoryInterface;
use App\Infrastructure\Session\SessionStoreInterface;
use PHPUnit\Framework\TestCase;

final class BarcodePrintControllerTest extends TestCase
{
    public function testCreateRequiresStaffAndCsrf(): void
    {
        $store = new BarcodePrintControllerSessionStore(new \App\Domain\Auth\SessionIdentity(7, Role::STUDENT, 'student'));
        $controller = $this->controller($store);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/barcode-print-batches';
        $_POST = ['title_id' => '4'];

        self::assertSame(401, $controller->create(ServerRequest::fromGlobals())->statusCode());

        $store->identity = new \App\Domain\Auth\SessionIdentity(7, Role::LIBRARIAN, 'staff');
        self::assertSame(419, $controller->create(ServerRequest::fromGlobals())->statusCode());
    }

    public function testCreateReturnsCreatedBatchAndHistoryCanBeRead(): void
    {
        $store = new BarcodePrintControllerSessionStore(new \App\Domain\Auth\SessionIdentity(7, Role::LIBRARIAN, 'staff'));
        $controller = $this->controller($store);
        $csrf = (new CsrfService($store))->token();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/barcode-print-batches';
        $_POST = ['title_id' => '4', 'csrf' => $csrf];

        $response = $controller->create(ServerRequest::fromGlobals());

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('"status":"created"', $response->toString());

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/barcode-print-batches?title_id=4';
        $_POST = [];
        self::assertSame(200, $controller->index(ServerRequest::fromGlobals())->statusCode());
        self::assertStringContainsString('batch_token', $controller->index(ServerRequest::fromGlobals())->toString());
    }

    private function controller(BarcodePrintControllerSessionStore $store): BarcodePrintController
    {
        return new BarcodePrintController(
            new SessionService($store),
            new BarcodePrintService(new class implements BarcodePrintRepositoryInterface {
                /** @var list<BarcodePrintBatch> */
                public array $batches = [];

                public function createBatch(int $titleId, int $staffId, string $token): ?BarcodePrintBatch
                {
                    $batch = new BarcodePrintBatch(1, $token, $titleId, 'Clean Code', '2026-08-29 10:00:00', [['barcode' => 'BC-1']]);
                    $this->batches[] = $batch;

                    return $batch;
                }

                public function findBatch(string $token): ?BarcodePrintBatch
                {
                    return $this->batches[0] ?? null;
                }

                public function history(int $titleId): array
                {
                    return [['batch_token' => $this->batches[0]->token ?? '', 'label_count' => 1]];
                }
            }),
            new CsrfService($store),
        );
    }
}

final class BarcodePrintControllerSessionStore implements SessionStoreInterface
{
    /** @var array<string, mixed> */
    private array $values = [];

    public function __construct(public ?\App\Domain\Auth\SessionIdentity $identity) {}
    public function start(): void {}
    public function regenerate(): void {}
    public function id(): string { return 'barcode-print-test'; }
    public function get(string $key): mixed { return $key === 'scan2borrow.identity' ? $this->identity : ($this->values[$key] ?? null); }
    public function set(string $key, mixed $value): void { $this->values[$key] = $value; }
    public function remove(string $key): void { unset($this->values[$key]); }
}
