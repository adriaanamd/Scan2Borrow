<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\CopyHistoryService;
use App\Application\Services\SessionService;
use App\Domain\Auth\Role;
use App\Http\Requests\ServerRequest;
use App\Http\Responses\JsonResponse;
use InvalidArgumentException;

final readonly class CopyHistoryController
{
    public function __construct(
        private SessionService $sessions,
        private CopyHistoryService $service,
    ) {
    }

    public function index(ServerRequest $request): JsonResponse
    {
        if (!$this->isStaff()) {
            return new JsonResponse(401, ['ok' => false, 'errors' => ['Staff authentication required.']]);
        }

        try {
            $result = $this->service->findByBarcode($this->string($request->query(), 'barcode'));
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(422, ['ok' => false, 'message' => $exception->getMessage()]);
        }
        if ($result === null) {
            return new JsonResponse(404, ['ok' => false, 'message' => 'Copy history not found.']);
        }

        return new JsonResponse(200, ['ok' => true, 'data' => $result->toArray()]);
    }

    private function isStaff(): bool
    {
        $identity = $this->sessions->current();

        return $identity !== null && in_array($identity->role(), [Role::ADMIN, Role::LIBRARIAN], true);
    }

    /** @param array<string, mixed> $input */
    private function string(array $input, string $key): string
    {
        $value = $input[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }
}
