<?php

declare(strict_types=1);

namespace Tests\Unit\StaffCirculation;

use App\Application\Services\ClockInterface;
use App\Application\Services\CsrfService;
use App\Application\Services\SessionService;
use App\Application\StaffCirculation\StaffCirculationBorrowRequest;
use App\Application\StaffCirculation\StaffCirculationRepositoryInterface;
use App\Application\StaffCirculation\StaffCirculationReturnRequest;
use App\Application\StaffCirculation\StaffCirculationService;
use App\Domain\Auth\Principal;
use App\Domain\Auth\Role;
use App\Domain\Auth\SessionIdentity;
use App\Domain\Borrowing\BorrowingPolicy;
use App\Http\Controllers\StaffController;
use App\Http\Requests\ServerRequest;
use App\Infrastructure\Persistence\StaffRepositoryInterface;
use App\Infrastructure\Session\SessionStoreInterface;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

final class StaffCirculationControllerTest extends TestCase
{
    private const CSRF = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_POST);
        parent::tearDown();
    }

    public function testLibrarianCanLookupBorrowAndReturnThroughCirculationEndpoints(): void
    {
        $store = new StaffCirculationControllerStore(self::CSRF, new Principal(19, Role::LIBRARIAN));
        $repository = new StaffCirculationControllerRepository();
        $controller = $this->controller($store, $repository);

        $lookup = $controller->circulationBorrower($this->request('GET', '/api/staff/circulation/borrower', [], ['barcode' => 'STU-001']));
        self::assertSame(200, $lookup->statusCode());
        self::assertStringContainsString('Ada Lovelace', $lookup->toString());

        $borrow = $controller->circulationBorrow($this->request('POST', '/api/staff/circulation/borrow', [
            'csrf' => self::CSRF,
            'borrower_barcode' => 'STU-001',
            'book_barcodes' => ['COPY-001'],
        ]));
        self::assertSame(200, $borrow->statusCode());
        self::assertStringContainsString('approved', strtolower($borrow->toString()));
        self::assertSame(19, $repository->borrowStaffId);

        $return = $controller->circulationReturn($this->request('POST', '/api/staff/circulation/return', [
            'csrf' => self::CSRF,
            'borrower_id' => '7',
            'loan_ids' => ['55'],
            'copy_barcodes' => ['COPY-001'],
        ]));
        self::assertSame(200, $return->statusCode());
        self::assertStringContainsString('processed', strtolower($return->toString()));
        self::assertSame(19, $repository->returnStaffId);
    }

    public function testBorrowAndReturnRequireStaffRoleAndCsrf(): void
    {
        $borrowerStore = new StaffCirculationControllerStore(self::CSRF, new Principal(7, Role::STUDENT));
        $controller = $this->controller($borrowerStore, new StaffCirculationControllerRepository());

        self::assertSame(401, $controller->circulationBorrower($this->request('GET', '/api/staff/circulation/borrower', [], ['barcode' => 'STU-001']))->statusCode());
        self::assertSame(401, $controller->circulationBorrow($this->request('POST', '/api/staff/circulation/borrow', []))->statusCode());

        $staffStore = new StaffCirculationControllerStore(self::CSRF, new Principal(19, Role::LIBRARIAN));
        $staffController = $this->controller($staffStore, new StaffCirculationControllerRepository());
        self::assertSame(419, $staffController->circulationBorrow($this->request('POST', '/api/staff/circulation/borrow', [
            'csrf' => 'wrong',
            'borrower_barcode' => 'STU-001',
            'book_barcodes' => ['COPY-001'],
        ]))->statusCode());
    }

    public function testStaffRouteTableIncludesCirculationRoutes(): void
    {
        $store = new StaffCirculationControllerStore(self::CSRF, new Principal(7, Role::STUDENT));
        $controller = $this->controller($store, new StaffCirculationControllerRepository());
        $routes = (new \App\Http\Routing\StaffRouteTable())->routes($controller);

        foreach ([
            ['GET', '/api/staff/circulation/borrower'],
            ['POST', '/api/staff/circulation/borrow'],
            ['POST', '/api/staff/circulation/return'],
        ] as [$method, $path]) {
            $request = $this->request($method, $path);
            $matched = array_filter($routes, static fn (\App\Http\Routing\Route $route): bool => $route->matches($request));
            self::assertCount(1, $matched, $method . ' ' . $path);
        }
    }

    private function controller(StaffCirculationControllerStore $store, StaffCirculationControllerRepository $repository): StaffController
    {
        return new StaffController(
            new SessionService($store),
            $this->createMock(StaffRepositoryInterface::class),
            new CsrfService($store),
            circulation: new StaffCirculationService(
                $repository,
                new BorrowingPolicy(3, 7, 30, false),
                new StaffCirculationControllerClock(),
            ),
        );
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    private function request(string $method, string $uri, array $body = [], array $query = []): ServerRequest
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri . ($query === [] ? '' : '?' . http_build_query($query));
        $_POST = $body;

        return ServerRequest::fromGlobals();
    }
}

final class StaffCirculationControllerClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-28 10:00:00');
    }
}

final class StaffCirculationControllerStore implements SessionStoreInterface
{
    /** @var array<string, mixed> */
    private array $values;

    public function __construct(string $csrf, ?Principal $principal)
    {
        $this->values = [
            'scan2borrow.csrf' => $csrf,
            'scan2borrow.identity' => $principal === null
                ? null
                : new SessionIdentity($principal->id(), $principal->role(), 'staff-circulation-controller'),
        ];
    }

    public function start(): void {}
    public function regenerate(): void {}
    public function id(): string { return 'staff-circulation-controller'; }
    public function get(string $key): mixed { return $this->values[$key] ?? null; }
    public function set(string $key, mixed $value): void { $this->values[$key] = $value; }
    public function remove(string $key): void { unset($this->values[$key]); }
}

final class StaffCirculationControllerRepository implements StaffCirculationRepositoryInterface
{
    public int $borrowStaffId = 0;
    public int $returnStaffId = 0;

    public function staffCanCirculate(int $staffId): bool
    {
        return $staffId === 19;
    }

    public function findBorrowerByBarcode(string $barcode): array
    {
        return [
            'ok' => true,
            'borrower' => [
                'id' => 7,
                'barcode' => $barcode,
                'firstname' => 'Ada',
                'lastname' => 'Lovelace',
                'name' => 'Ada Lovelace',
                'role' => 'student',
                'status' => 'active',
                'borrowing_status' => 'active',
                'active_loans' => 0,
            ],
            'active_loans' => [],
        ];
    }

    public function createDirectBorrow(int $borrowerId, int $staffId, array $bookBarcodes, DateTimeImmutable $dueDate, string $transactionCode): array
    {
        $this->borrowStaffId = $staffId;

        return ['transaction_code' => $transactionCode, 'borrowed' => count($bookBarcodes), 'due_date' => $dueDate->format('Y-m-d')];
    }

    public function processDirectReturns(int $borrowerId, int $staffId, array $loanIds, array $copyBarcodes, DateTimeImmutable $now, float $finePerDay): array
    {
        $this->returnStaffId = $staffId;

        return ['returned' => count($loanIds), 'total_fine' => 0.0, 'items' => []];
    }
}
