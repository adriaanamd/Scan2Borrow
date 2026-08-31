<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\CsrfService;
use App\Application\Services\ProfileChangeRequestService;
use App\Application\Services\SessionService;
use App\Domain\Auth\Role;
use App\Http\Requests\ServerRequest;
use App\Http\Responses\JsonResponse;
use InvalidArgumentException;
use RuntimeException;

final readonly class ProfileChangeRequestController
{
    public function __construct(
        private SessionService $sessions,
        private CsrfService $csrf,
        private ProfileChangeRequestService $service,
    ) {
    }

    public function show(ServerRequest $request): JsonResponse
    {
        $identity = $this->borrowerForPath($request);
        if ($identity === null) {
            return $this->unauthorized();
        }
        try {
            return new JsonResponse(200, ['ok' => true, 'data' => $this->service->show($identity->userId())]);
        } catch (RuntimeException $exception) {
            return new JsonResponse(404, ['ok' => false, 'errors' => [$exception->getMessage()]]);
        }
    }

    public function submit(ServerRequest $request): JsonResponse
    {
        $identity = $this->borrowerForPath($request);
        if ($identity === null) {
            return $this->unauthorized();
        }
        try {
            $this->csrf->assertValid($this->string($request->body()['csrf'] ?? null));
            $id = $this->service->submit($identity->userId(), $request->body());

            return new JsonResponse(200, ['ok' => true, 'data' => ['id' => $id, 'message' => 'Your profile change request was submitted for administrator approval.']]);
        } catch (InvalidArgumentException $exception) {
            $status = $exception->getMessage() === 'Invalid CSRF token.' ? 419 : 422;
            return new JsonResponse($status, ['ok' => false, 'errors' => [$exception->getMessage()]]);
        } catch (RuntimeException $exception) {
            return new JsonResponse(422, ['ok' => false, 'errors' => [$exception->getMessage()]]);
        }
    }

    private function borrowerForPath(ServerRequest $request): ?\App\Domain\Auth\SessionIdentity
    {
        $identity = $this->sessions->current();
        $expected = str_starts_with($request->path(), '/api/teacher/') ? Role::TEACHER : Role::STUDENT;

        return $identity !== null && $identity->role() === $expected ? $identity : null;
    }

    private function unauthorized(): JsonResponse
    {
        return new JsonResponse(401, ['ok' => false, 'errors' => ['Borrower authentication required.']]);
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
