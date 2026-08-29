<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Services\BookQueryService;
use App\Domain\Auth\Role;
use App\Domain\Book\BookSearchResult;
use App\Http\Controllers\BookController;
use App\Http\Requests\ServerRequest;
use App\Infrastructure\Persistence\BookRepositoryInterface;
use App\Infrastructure\Session\SessionStoreInterface;
use App\Application\Services\SessionService;
use PHPUnit\Framework\TestCase;

final class BookControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_POST);
        parent::tearDown();
    }

    public function testStudentSearchReturnsLegacyBookEnvelope(): void
    {
        $store = new class implements SessionStoreInterface {
            public function start(): void {}
            public function regenerate(): void {}
            public function id(): string { return 'test'; }
            public function get(string $key): mixed { return $key === 'scan2borrow.identity' ? new \App\Domain\Auth\SessionIdentity(7, Role::STUDENT, 'test') : null; }
            public function set(string $key, mixed $value): void {}
            public function remove(string $key): void {}
        };
        $repository = new class implements BookRepositoryInterface {
            public function search(\App\Domain\Book\BookSearchCriteria $criteria): BookSearchResult
            {
                return new BookSearchResult([['id' => 1, 'title' => 'Clean Code', 'status' => 'Available']], 1);
            }

            public function lookupCopyByBarcode(string $barcode): ?array
            {
                return null;
            }
        };
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/student/books?search=clean';

        $response = (new BookController(new SessionService($store), new BookQueryService($repository)))
            ->studentSearch(ServerRequest::fromGlobals());

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('"books"', $response->toString());
        self::assertStringContainsString('Clean Code', $response->toString());
    }

    public function testStudentBorrowLookupReturnsCopyAndAvailableQuantity(): void
    {
        $store = new class implements SessionStoreInterface {
            public function start(): void {}
            public function regenerate(): void {}
            public function id(): string { return 'test'; }
            public function get(string $key): mixed { return $key === 'scan2borrow.identity' ? new \App\Domain\Auth\SessionIdentity(7, Role::STUDENT, 'test') : null; }
            public function set(string $key, mixed $value): void {}
            public function remove(string $key): void {}
        };
        $repository = new class implements BookRepositoryInterface {
            public function search(\App\Domain\Book\BookSearchCriteria $criteria): BookSearchResult
            {
                return new BookSearchResult([], 0);
            }

            public function lookupCopyByBarcode(string $barcode): ?array
            {
                return ['copy_id' => 4, 'title_id' => 12, 'barcode' => $barcode, 'title' => 'Clean Code', 'author' => 'Robert Martin', 'available_quantity' => 2];
            }
        };
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/student/borrow/lookup?barcode=COPY-4';

        $response = (new BookController(new SessionService($store), new BookQueryService($repository)))
            ->borrowLookup(ServerRequest::fromGlobals());

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('"title_id":12', $response->toString());
        self::assertStringContainsString('"available_quantity":2', $response->toString());
    }
}
