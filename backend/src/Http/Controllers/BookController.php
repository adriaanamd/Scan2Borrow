<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\BookArchiveService;
use App\Application\Services\BookMutationService;
use App\Application\Services\BookQueryService;
use App\Application\Services\CsrfService;
use App\Application\Services\SessionService;
use App\Domain\Auth\Role;
use App\Http\Requests\ServerRequest;
use App\Http\Responses\JsonResponse;
use App\Domain\Book\BookSearchCriteria;
use InvalidArgumentException;

final readonly class BookController
{
    public function __construct(
        private SessionService $sessions,
        private BookQueryService $books,
        private ?BookMutationService $mutations = null,
        private ?BookArchiveService $archive = null,
        private ?CsrfService $csrf = null,
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
            'per_page' => $criteria->perPage(),
            'pages' => max(1, (int) ceil($result->total() / $criteria->perPage())),
        ]);
    }

    public function mutate(ServerRequest $request): JsonResponse
    {
        if (!$this->hasRole([Role::ADMIN, Role::LIBRARIAN]) || $this->mutations === null || $this->csrf === null) {
            return $this->unauthorized();
        }
        $csrfFailure = $this->csrfFailure($request);
        if ($csrfFailure !== null) {
            return $csrfFailure;
        }

        $body = $request->body();
        $action = $this->string($body, 'action');
        if (in_array($action, ['archive', 'restore', 'delete'], true)) {
            if ($this->archive === null) {
                return new JsonResponse(503, ['ok' => false, 'message' => 'Inventory archive service is not configured.']);
            }
            $ids = $this->ids($body['ids'] ?? ($body['id'] ?? []));
            try {
                $count = match ($action) {
                    'archive' => $this->archive->archive($ids),
                    'restore' => $this->archive->restore($ids),
                    'delete' => $this->archive->delete($ids),
                };
            } catch (InvalidArgumentException $exception) {
                return new JsonResponse(422, ['ok' => false, 'message' => $exception->getMessage()]);
            }
            $noun = $action === 'delete' ? 'deleted' : $action . 'd';

            return new JsonResponse(200, ['ok' => true, 'message' => $count . ' book(s) ' . $noun . '.']);
        }

        if ($action === 'update') {
            $result = $this->mutations->update($this->positiveInt($body['id'] ?? null), $this->bookRequest($body));
            $message = 'Book updated successfully.';
        } elseif ($action === 'create') {
            $result = $this->mutations->create($this->bookRequest($body));
            $message = 'Book added successfully.';
        } else {
            return new JsonResponse(400, ['ok' => false, 'message' => 'Unknown action.']);
        }
        if (!$result->successful()) {
            return new JsonResponse(422, ['ok' => false, 'message' => $result->message()]);
        }

        return new JsonResponse(200, ['ok' => true, 'message' => $message, 'data' => ['id' => $result->bookId()]]);
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

    /** @param array<string, mixed> $body */
    private function bookRequest(array $body): \App\Application\DTO\BookMutationRequest
    {
        return new \App\Application\DTO\BookMutationRequest(
            $this->string($body, 'barcode'),
            $this->string($body, 'title'),
            $this->string($body, 'accession_no'),
            $this->string($body, 'isbn'),
            $this->string($body, 'author'),
            $this->string($body, 'publisher'),
            $this->string($body, 'description'),
            $this->string($body, 'cover_file_path'),
            $this->string($body, 'category_name'),
            $this->string($body, 'floor_no'),
            $this->string($body, 'section_name'),
            $this->string($body, 'shelf_no'),
            $this->string($body, 'row_no'),
            $this->string($body, 'due_date'),
            $this->string($body, 'return_date'),
            $this->string($body, 'status') === '' ? 'Available' : $this->string($body, 'status'),
        );
    }

    /** @param array<string, mixed> $body */
    private function string(array $body, string $key): string
    {
        $value = $body[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    /** @return list<int> */
    private function ids(mixed $value): array
    {
        $values = is_array($value) ? $value : explode(',', is_string($value) ? $value : '');
        $ids = [];
        foreach ($values as $item) {
            if (is_int($item) || (is_string($item) && is_numeric($item))) {
                $id = (int) $item;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private function positiveInt(mixed $value): int
    {
        return is_int($value) ? $value : (is_string($value) && is_numeric($value) ? (int) $value : 0);
    }

    private function csrfFailure(ServerRequest $request): ?JsonResponse
    {
        try {
            $this->csrf?->assertValid($this->string($request->body(), 'csrf'));
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(419, ['ok' => false, 'message' => $exception->getMessage()]);
        }

        return null;
    }
}
