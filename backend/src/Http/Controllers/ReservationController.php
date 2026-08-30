<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\DTO\HoldActionRequest;
use App\Application\DTO\JoinHoldRequest;
use App\Application\Services\CsrfService;
use App\Application\Services\ReservationService;
use App\Application\Services\SessionService;
use App\Domain\Auth\Role;
use App\Http\Requests\ServerRequest;
use App\Http\Responses\JsonResponse;
use InvalidArgumentException;

final readonly class ReservationController
{
    public function __construct(
        private SessionService $sessions,
        private CsrfService $csrf,
        private ReservationService $reservations,
    ) {
    }

    public function list(ServerRequest $request): JsonResponse
    {
        $identity = $this->borrower();
        if ($identity === null) {
            return $this->unauthorized();
        }

        return new JsonResponse(200, [
            'ok' => true,
            'data' => ['holds' => array_map(
                static fn (\App\Domain\Reservation\HoldRecord $hold): array => $hold->toArray(),
                $this->reservations->list($identity->userId()),
            )],
        ]);
    }

    public function create(ServerRequest $request): JsonResponse
    {
        $identity = $this->borrower();
        if ($identity === null) {
            return $this->unauthorized();
        }

        $csrfResponse = $this->validateCsrf($request);
        if ($csrfResponse !== null) {
            return $csrfResponse;
        }

        try {
            $result = $this->reservations->join(new JoinHoldRequest(
                $identity->userId(),
                $this->positiveInt($request->body()['title_id'] ?? null),
            ));
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(422, ['ok' => false, 'errors' => [$exception->getMessage()]]);
        }

        return $this->result($result);
    }

    public function action(ServerRequest $request): JsonResponse
    {
        $identity = $this->borrower();
        if ($identity === null) {
            return $this->unauthorized();
        }

        $csrfResponse = $this->validateCsrf($request);
        if ($csrfResponse !== null) {
            return $csrfResponse;
        }

        try {
            $action = new HoldActionRequest(
                $identity->userId(),
                $this->positiveInt($request->body()['hold_id'] ?? null),
                $this->value($request->body(), 'action'),
            );
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(422, ['ok' => false, 'errors' => [$exception->getMessage()]]);
        }

        return $this->result($action->action === 'claim'
            ? $this->reservations->claim($action)
            : $this->reservations->cancel($action));
    }

    private function result(\App\Application\DTO\ReservationResult $result): JsonResponse
    {
        $data = ['message' => $result->message()];
        if ($result->record() !== null) {
            $data['hold'] = $result->record()->toArray();
        }

        return $result->successful()
            ? new JsonResponse(200, ['ok' => true, 'data' => $data])
            : new JsonResponse(422, ['ok' => false, 'errors' => [$result->message()]]);
    }

    private function validateCsrf(ServerRequest $request): ?JsonResponse
    {
        try {
            $this->csrf->assertValid($this->value($request->body(), 'csrf'));
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(419, ['ok' => false, 'errors' => [$exception->getMessage()]]);
        }

        return null;
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

    private function positiveInt(mixed $value): int
    {
        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : 0);
    }
}
