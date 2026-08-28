<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\BookQueryService;
use App\Application\Services\SessionService;
use App\Domain\Auth\Role;
use App\Http\Requests\ServerRequest;
use App\Http\Responses\JsonResponse;
use App\Domain\Book\BookSearchCriteria;

final readonly class BookController
{
    public function __construct(
        private SessionService $sessions,
        private BookQueryService $books,
    ) {
    }

    public function inventory(ServerRequest $request): JsonResponse
    {
        if (!$this->hasRole([Role::ADMIN, Role::LIBRARIAN])) {
            return $this->unauthorized();
        }

        $criteria = BookSearchCriteria::fromArray($request->query());
        $result = $this->books->search($criteria);

        return new JsonResponse(200, [
            'ok' => true,
            'data' => $result->books(),
            'total' => $result->total(),
            'page' => $criteria->page(),
            'pages' => max(1, (int) ceil($result->total() / $criteria->perPage())),
        ]);
    }

    public function studentSearch(ServerRequest $request): JsonResponse
    {
        if (!$this->hasRole([Role::STUDENT, Role::TEACHER])) {
            return $this->unauthorized();
        }

        $criteria = BookSearchCriteria::fromArray($request->query());
        $result = $this->books->search($criteria);

        return new JsonResponse(200, [
            'ok' => true,
            'data' => [
                'books' => $result->books(),
                'total' => $result->total(),
                'categories' => $this->uniqueValues($result->books(), 'category_name'),
                'floors' => $this->uniqueValues($result->books(), 'floor_no'),
            ],
        ]);
    }

    /** @param list<Role> $roles */
    private function hasRole(array $roles): bool
    {
        $identity = $this->sessions->current();

        return $identity !== null && in_array($identity->role(), $roles, true);
    }

    private function unauthorized(): JsonResponse
    {
        return new JsonResponse(401, ['ok' => false, 'errors' => ['Authentication required.']]);
    }

    /**
     * @param list<array<string, mixed>> $books
     * @return list<string>
     */
    private function uniqueValues(array $books, string $field): array
    {
        $values = [];
        foreach ($books as $book) {
            $value = $book[$field] ?? null;
            if (is_string($value) && $value !== '') {
                $values[] = $value;
            }
        }

        $result = [];
        foreach ($values as $value) {
            if (!in_array($value, $result, true)) {
                $result[] = $value;
            }
        }

        return $result;
    }
}
