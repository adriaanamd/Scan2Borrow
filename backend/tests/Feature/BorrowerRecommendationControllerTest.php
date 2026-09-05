<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\DTO\RecommendationResult;
use App\Application\Services\CsrfService;
use App\Application\Services\RecommendationService;
use App\Application\Services\SearchHistoryService;
use App\Application\Services\SessionService;
use App\Domain\Auth\Role;
use App\Domain\Auth\SessionIdentity;
use App\Http\Controllers\BorrowerRecommendationController;
use App\Http\Requests\ServerRequest;
use App\Http\Routing\RecommendationRouteTable;
use App\Http\Routing\Router;
use App\Infrastructure\Persistence\RecommendationRepositoryInterface;
use App\Infrastructure\Persistence\SearchHistoryRepositoryInterface;
use App\Infrastructure\Session\SessionStoreInterface;
use PHPUnit\Framework\TestCase;

final class BorrowerRecommendationControllerTest extends TestCase
{
    public const CSRF = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $GLOBALS['_POST']);
        parent::tearDown();
    }

    public function testStudentSearchIsRecordedForAuthenticatedBorrower(): void
    {
        $store = new RecommendationSessionStore(new SessionIdentity(7, Role::STUDENT, 'student'));
        $history = new RecommendationHistoryRepository();
        $controller = $this->controller($store, $history, new RecommendationCatalogRepository());

        $response = $controller->recordSearch($this->request('POST', '/api/student/search-history', [
            'csrf' => self::CSRF,
            'search' => '  Clean   Code  ',
        ]));

        self::assertSame(200, $response->statusCode());
        self::assertSame([[7, 'Clean Code']], $history->recorded);
        self::assertStringContainsString('"recorded":true', $response->toString());
    }

    public function testSearchRejectsUnauthenticatedStaffAndInvalidInput(): void
    {
        $history = new RecommendationHistoryRepository();
        $catalog = new RecommendationCatalogRepository();
        $guestController = $this->controller(new RecommendationSessionStore(null), $history, $catalog);
        self::assertSame(401, $guestController->recordSearch($this->request('POST', '/api/student/search-history', [
            'csrf' => self::CSRF,
            'search' => 'Clean Code',
        ]))->statusCode());

        $staffController = $this->controller(
            new RecommendationSessionStore(new SessionIdentity(9, Role::LIBRARIAN, 'staff')),
            $history,
            $catalog,
        );
        self::assertSame(401, $staffController->recordSearch($this->request('POST', '/api/teacher/search-history', [
            'csrf' => self::CSRF,
            'search' => 'Clean Code',
        ]))->statusCode());

        $borrowerController = $this->controller(
            new RecommendationSessionStore(new SessionIdentity(7, Role::TEACHER, 'teacher')),
            $history,
            $catalog,
        );
        self::assertSame(419, $borrowerController->recordSearch($this->request('POST', '/api/teacher/search-history', [
            'csrf' => 'wrong',
            'search' => 'Clean Code',
        ]))->statusCode());
        self::assertSame(422, $borrowerController->recordSearch($this->request('POST', '/api/teacher/search-history', [
            'csrf' => self::CSRF,
            'search' => '   ',
        ]))->statusCode());
    }

    public function testRecommendationEndpointReturnsPersonalizedBooksForTeacher(): void
    {
        $store = new RecommendationSessionStore(new SessionIdentity(12, Role::TEACHER, 'teacher'));
        $history = new RecommendationHistoryRepository();
        $history->queries = ['clean code'];
        $catalog = new RecommendationCatalogRepository();
        $catalog->ranked = [['title_id' => 4, 'title' => 'Clean Code', 'status' => 'Available', 'score' => 99]];
        $controller = $this->controller($store, $history, $catalog);

        $response = $controller->index($this->request('GET', '/api/teacher/recommendations'));

        self::assertSame(200, $response->statusCode());
        self::assertStringContainsString('"personalized":true', $response->toString());
        self::assertStringContainsString('Clean Code', $response->toString());
        self::assertStringNotContainsString('score', $response->toString());
        self::assertSame(12, $catalog->lastUserId);
    }

    public function testRecommendationEndpointRequiresBorrowerAuthentication(): void
    {
        $controller = $this->controller(new RecommendationSessionStore(null), new RecommendationHistoryRepository(), new RecommendationCatalogRepository());

        self::assertSame(401, $controller->index($this->request('GET', '/api/student/recommendations'))->statusCode());
    }

    public function testStudentAndTeacherAliasesAreRegisteredForBothEndpoints(): void
    {
        $store = new RecommendationSessionStore(new SessionIdentity(7, Role::STUDENT, 'student'));
        $history = new RecommendationHistoryRepository();
        $controller = $this->controller($store, $history, new RecommendationCatalogRepository());
        $router = new Router((new RecommendationRouteTable())->routes($controller));

        foreach (['/api/student/recommendations', '/api/teacher/recommendations'] as $path) {
            self::assertSame(200, $router->dispatch($this->request('GET', $path))->statusCode());
        }
        foreach (['/api/student/search-history', '/api/teacher/search-history'] as $path) {
            self::assertSame(200, $router->dispatch($this->request('POST', $path, [
                'csrf' => self::CSRF,
                'search' => 'Clean Code',
            ]))->statusCode());
        }
    }

    private function controller(
        RecommendationSessionStore $store,
        RecommendationHistoryRepository $history,
        RecommendationCatalogRepository $catalog,
    ): BorrowerRecommendationController {
        return new BorrowerRecommendationController(
            new SessionService($store),
            new CsrfService($store),
            new SearchHistoryService($history),
            new RecommendationService($history, $catalog),
        );
    }

    /** @param array<string, mixed> $body */
    private function request(string $method, string $uri, array $body = []): ServerRequest
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $GLOBALS['_POST'] = $body;

        return ServerRequest::fromGlobals();
    }
}

final class RecommendationSessionStore implements SessionStoreInterface
{
    public function __construct(private readonly ?SessionIdentity $identity) {}
    public function start(): void {}
    public function regenerate(): void {}
    public function id(): string { return 'recommendation-test'; }
    public function get(string $key): mixed
    {
        return match ($key) {
            'scan2borrow.identity' => $this->identity,
            'scan2borrow.csrf' => BorrowerRecommendationControllerTest::CSRF,
            default => null,
        };
    }
    public function set(string $key, mixed $value): void {}
    public function remove(string $key): void {}
}

final class RecommendationHistoryRepository implements SearchHistoryRepositoryInterface
{
    /** @var list<array{int, string}> */
    public array $recorded = [];
    /** @var list<string> */
    public array $queries = [];

    public function record(int $userId, string $query): void { $this->recorded[] = [$userId, $query]; }
    /** @return list<string> */
    public function recentQueries(int $userId, int $limit): array { return $this->queries; }
}

final class RecommendationCatalogRepository implements RecommendationRepositoryInterface
{
    /** @var list<array<string, mixed>> */
    public array $ranked = [];
    /** @var list<array<string, mixed>> */
    public array $fallback = [];
    public ?int $lastUserId = null;

    public function recommend(\App\Application\DTO\SearchProfile $profile, int $userId, int $limit): array
    {
        $this->lastUserId = $userId;

        return array_slice($this->ranked, 0, $limit);
    }

    public function newestEligible(int $userId, int $limit): array
    {
        $this->lastUserId = $userId;

        return array_slice($this->fallback, 0, $limit);
    }
}
