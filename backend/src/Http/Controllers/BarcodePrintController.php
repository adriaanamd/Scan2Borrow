<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\BarcodePrintService;
use App\Application\Services\CsrfService;
use App\Application\Services\SessionService;
use App\Domain\Auth\Role;
use App\Http\Requests\ServerRequest;
use App\Http\Responses\JsonResponse;
use InvalidArgumentException;

final readonly class BarcodePrintController
{
    public function __construct(
        private SessionService $sessions,
        private BarcodePrintService $service,
        private CsrfService $csrf,
    ) {
    }

    public function index(ServerRequest $request): JsonResponse
    {
        if (!$this->isStaff()) {
            return $this->unauthorized();
        }

        $query = $request->query();
        $token = $this->string($query, 'batch_token');
        try {
            if ($token !== '') {
                $batch = $this->service->find($token);
                if ($batch === null) {
                    return new JsonResponse(404, ['ok' => false, 'message' => 'Print batch not found.']);
                }

                return new JsonResponse(200, ['ok' => true, 'data' => $batch->toArray()]);
            }

            return new JsonResponse(200, [
                'ok' => true,
                'data' => ['history' => $this->service->history($this->positiveInt($query['title_id'] ?? null))],
            ]);
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(422, ['ok' => false, 'message' => $exception->getMessage()]);
        }
    }

    public function create(ServerRequest $request): JsonResponse
    {
        if (!$this->isStaff()) {
            return $this->unauthorized();
        }

        try {
            $this->csrf->assertValid($this->string($request->body(), 'csrf'));
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(419, ['ok' => false, 'message' => $exception->getMessage()]);
        }

        try {
            $identity = $this->sessions->current();
            $result = $this->service->create(
                $this->positiveInt($request->body()['title_id'] ?? null),
                $identity?->userId() ?? 0,
            );
            $message = $result->status === 'created'
                ? 'Barcode export batch created.'
                : 'All active barcodes for this title were already exported.';

            return new JsonResponse(200, ['ok' => true, 'message' => $message, 'data' => $result->toArray()]);
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

    /** @param array<string, mixed> $input */
    private function string(array $input, string $key): string
    {
        $value = $input[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    private function positiveInt(mixed $value): int
    {
        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : 0);
    }
}
