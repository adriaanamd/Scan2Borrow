<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\DTO\BookCopyMutationRequest;
use App\Application\Validators\BookCopyMutationValidator;
use App\Application\Services\CsrfService;
use App\Application\Services\SessionService;
use App\Domain\Auth\Role;
use App\Domain\Book\BookStatus;
use App\Http\Requests\ServerRequest;
use App\Http\Responses\JsonResponse;
use App\Infrastructure\Persistence\BookAdministrationRepositoryInterface;
use InvalidArgumentException;

final readonly class BookCopyController
{
    public function __construct(
        private SessionService $sessions,
        private BookAdministrationRepositoryInterface $books,
        private BookCopyMutationValidator $validator,
        private CsrfService $csrf,
    ) {
    }

    public function index(ServerRequest $request): JsonResponse
    {
        if (!$this->isStaff()) {
            return $this->unauthorized();
        }
        $titleId = $this->positiveInt($request->query()['title_id'] ?? null);
        if ($titleId < 1) {
            return new JsonResponse(422, ['ok' => false, 'message' => 'A valid title is required.']);
        }

        try {
            return new JsonResponse(200, ['ok' => true, 'data' => $this->books->copies($titleId)]);
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(422, ['ok' => false, 'message' => $exception->getMessage()]);
        }
    }

    public function mutate(ServerRequest $request): JsonResponse
    {
        if (!$this->isStaff()) {
            return $this->unauthorized();
        }
        try {
            $this->csrf->assertValid($this->string($request->body(), 'csrf'));
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(419, ['ok' => false, 'message' => $exception->getMessage()]);
        }

        $body = $request->body();
        $action = $this->string($body, 'action');
        try {
            if ($action === 'update') {
                $copyRequest = $this->copyRequest($body, $this->sessions->current()?->userId() ?? 0);
                $error = $this->validator->firstError($copyRequest);
                if ($error !== null) {
                    return new JsonResponse(422, ['ok' => false, 'message' => $error]);
                }
                $this->books->updateCopy($copyRequest);

                return new JsonResponse(200, ['ok' => true, 'message' => 'Book copy updated.', 'data' => ['copy_id' => $copyRequest->copyId]]);
            }

            $ids = $this->ids($body['ids'] ?? ($body['copy_id'] ?? []));
            $actorId = $this->sessions->current()?->userId() ?? 0;
            $count = match ($action) {
                'archive' => $this->books->archiveCopies($ids, $actorId),
                'restore' => $this->books->restoreCopies($ids, $actorId),
                'delete' => $this->books->deleteCopies($ids, $actorId),
                default => throw new InvalidArgumentException('Unknown copy action.'),
            };

            return new JsonResponse(200, ['ok' => true, 'message' => $count . ' book copy/copies updated.']);
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(422, ['ok' => false, 'message' => $exception->getMessage()]);
        }
    }

    private function isStaff(): bool
    {
        $identity = $this->sessions->current();

        return $identity !== null && in_array($identity->role(), [Role::ADMIN, Role::LIBRARIAN], true);
    }

    private function unauthorized(): JsonResponse
    {
        return new JsonResponse(401, ['ok' => false, 'errors' => ['Staff authentication required.']]);
    }

    /** @param array<string, mixed> $body */
    private function copyRequest(array $body, int $actorId): BookCopyMutationRequest
    {
        $statusValue = BookStatus::tryFrom($this->string($body, 'status'));
        $status = $statusValue === null ? BookStatus::AVAILABLE->value : $statusValue->value;

        return new BookCopyMutationRequest(
            $this->positiveInt($body['copy_id'] ?? $body['id'] ?? null),
            $this->string($body, 'barcode'),
            $this->string($body, 'accession_no'),
            $this->string($body, 'floor_no'),
            $this->string($body, 'section_name'),
            $this->string($body, 'shelf_no'),
            $this->string($body, 'row_no'),
            $this->string($body, 'due_date'),
            $this->string($body, 'return_date'),
            $status,
            $this->string($body, 'reason'),
            $actorId,
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
        $values = is_array($value) ? $value : [$value];
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
}
