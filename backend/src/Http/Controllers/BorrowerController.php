<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\DTO\BorrowRequest;
use App\Application\DTO\BulkBorrowItem;
use App\Application\DTO\BulkBorrowRequest;
use App\Application\Services\BorrowingService;
use App\Application\Services\CsrfService;
use App\Application\Services\ReturnService;
use App\Application\Services\SessionService;
use App\Domain\Auth\Role;
use App\Http\Requests\ServerRequest;
use App\Http\Responses\JsonResponse;
use App\Infrastructure\Persistence\BorrowerPortalRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

final readonly class BorrowerController
{
    public function __construct(
        private SessionService $sessions,
        private CsrfService $csrf,
        private BorrowingService $borrowing,
        private ReturnService $returns,
        private BorrowerPortalRepositoryInterface $portal,
    ) {
    }

    public function dashboard(ServerRequest $request): JsonResponse
    {
        $identity = $this->borrower();
        if ($identity === null) {
            return $this->unauthorized();
        }

        return new JsonResponse(200, ['ok' => true, 'data' => $this->portal->dashboard($identity->userId())]);
    }

    public function activity(ServerRequest $request): JsonResponse
    {
        $identity = $this->borrower();
        if ($identity === null) {
            return $this->unauthorized();
        }

        return new JsonResponse(200, [
            'ok' => true,
            'data' => ['activity' => $this->portal->activity($identity->userId())],
        ]);
    }

    public function history(ServerRequest $request): JsonResponse
    {
        $identity = $this->borrower();
        if ($identity === null) {
            return $this->unauthorized();
        }

        return new JsonResponse(200, ['ok' => true, 'data' => ['history' => $this->portal->history($identity->userId())]]);
    }

    public function receipt(ServerRequest $request): JsonResponse
    {
        $identity = $this->borrower();
        if ($identity === null) {
            return $this->unauthorized();
        }
        $code = $this->value($request->query(), 'code');
        $receipt = $this->portal->receipt($identity->userId(), $code);
        if ($receipt === null) {
            return new JsonResponse(404, ['ok' => false, 'errors' => ['Receipt not found. Invalid transaction code.']]);
        }

        return new JsonResponse(200, ['ok' => true, 'data' => $receipt]);
    }

    public function change(ServerRequest $request): JsonResponse
    {
        $identity = $this->borrower();
        if ($identity === null) {
            return $this->unauthorized();
        }
        try {
            $this->csrf->assertValid($this->value($request->body(), 'csrf'));
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(419, ['ok' => false, 'errors' => [$exception->getMessage()]]);
        }

        $action = $this->value($request->body(), 'action');
        if ($action === 'borrow') {
            $items = $this->bulkItems($request->body()['items'] ?? null);
            if ($items !== []) {
                try {
                    $result = $this->borrowing->bulkBorrow(new BulkBorrowRequest(
                        $identity->userId(),
                        $identity->role(),
                        $items,
                        $this->nullableValue($request->body(), 'due_date'),
                    ));
                } catch (RuntimeException $exception) {
                    return new JsonResponse(422, ['ok' => false, 'errors' => [$exception->getMessage()]]);
                }

                return $result->successful()
                    ? new JsonResponse(200, ['ok' => true, 'data' => [
                        'message' => $result->message(),
                        'transaction_code' => $result->transactionCode(),
                        'book_count' => $result->copyCount(),
                        'title_count' => $result->titleCount(),
                    ]])
                    : new JsonResponse(422, ['ok' => false, 'errors' => [$result->message()]]);
            }

            $result = $this->borrowing->borrow(new BorrowRequest(
                $identity->userId(),
                $identity->role(),
                $this->value($request->body(), 'book_barcode'),
                $this->nullableValue($request->body(), 'due_date'),
            ));
            return $result->successful()
                ? new JsonResponse(200, ['ok' => true, 'data' => ['message' => $result->message(), 'transaction_code' => $result->transactionCode(), 'book_count' => 1]])
                : new JsonResponse(422, ['ok' => false, 'errors' => [$result->message()]]);
        }

        if ($action === 'return_unified') {
            $result = $this->returns->request($identity->userId(), $this->value($request->body(), 'return_input'));
            return $result->successful()
                ? new JsonResponse(200, ['ok' => true, 'data' => ['message' => $result->message()]])
                : new JsonResponse(422, ['ok' => false, 'errors' => [$result->message()]]);
        }

        return new JsonResponse(422, ['ok' => false, 'errors' => ['Unsupported borrowing action.']]);
    }

    /** @return list<BulkBorrowItem> */
    private function bulkItems(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }

        $items = [];
        foreach ($input as $value) {
            if (!is_array($value)) {
                continue;
            }
            $titleId = $this->positiveInt($value['title_id'] ?? null);
            $quantity = $this->positiveInt($value['quantity'] ?? null);
            $barcodes = [];
            if (is_array($value['barcodes'] ?? null)) {
                foreach ($value['barcodes'] as $barcode) {
                    if (is_string($barcode) && trim($barcode) !== '') {
                        $barcodes[] = trim($barcode);
                    }
                }
            }
            $items[] = new BulkBorrowItem($titleId, $quantity, array_values(array_unique($barcodes)));
        }

        return $items;
    }

    private function borrower(): ?\App\Domain\Auth\SessionIdentity
    {
        $identity = $this->sessions->current();

        return $identity !== null && in_array($identity->role(), [Role::STUDENT, Role::TEACHER], true) ? $identity : null;
    }

    private function unauthorized(): JsonResponse
    {
        return new JsonResponse(401, ['ok' => false, 'errors' => ['Borrower authentication required.']]);
    }

    /** @param array<string, mixed> $input */
    private function value(array $input, string $key): string
    {
        return is_string($input[$key] ?? null) ? trim($input[$key]) : '';
    }

    /** @param array<string, mixed> $input */
    private function nullableValue(array $input, string $key): ?string
    {
        $value = $this->value($input, $key);

        return $value === '' ? null : $value;
    }

    private function positiveInt(mixed $value): int
    {
        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : 0);
    }
}
