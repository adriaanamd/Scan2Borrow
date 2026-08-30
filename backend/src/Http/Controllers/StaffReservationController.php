<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\DTO\HoldActionRequest;
use App\Application\Services\CsrfService;
use App\Application\Services\ReservationService;
use App\Application\Services\SessionService;
use App\Domain\Auth\Role;
use App\Http\Requests\ServerRequest;
use App\Http\Responses\JsonResponse;
use InvalidArgumentException;

final readonly class StaffReservationController
{
    public function __construct(
        private SessionService $sessions,
        private CsrfService $csrf,
        private ReservationService $reservations,
    ) {
    }

    public function index(ServerRequest $request): JsonResponse
    {
        if (!$this->isStaff()) {
            return $this->unauthorized();
        }

        $status = $request->query()['status'] ?? '';
        $status = is_string($status) ? trim($status) : '';

        return new JsonResponse(200, [
            'ok' => true,
            'data' => ['reservations' => array_map(
                static fn (\App\Domain\Reservation\HoldRecord $hold): array => $hold->toArray(),
                $this->reservations->staffList($status),
            )],
        ]);
    }

    public function action(ServerRequest $request): JsonResponse
    {
        if (!$this->isStaff()) {
            return $this->unauthorized();
        }
        try {
            $this->csrf->assertValid($this->bodyString($request, 'csrf'));
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(419, ['ok' => false, 'errors' => [$exception->getMessage()]]);
        }
        $identity = $this->sessions->current();
        $action = $this->bodyString($request, 'action');
        $holdId = $this->positiveInt($request->body()['hold_id'] ?? null);
        if ($identity === null || $holdId < 1 || $action !== 'fulfil') {
            return new JsonResponse(422, ['ok' => false, 'errors' => ['Invalid reservation decision.']]);
        }

        $result = $this->reservations->fulfil($holdId, $identity->userId());

        return $result->successful()
            ? new JsonResponse(200, ['ok' => true, 'data' => ['message' => $result->message()]])
            : new JsonResponse(422, ['ok' => false, 'errors' => [$result->message()]]);
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

    private function bodyString(ServerRequest $request, string $key): string
    {
        $value = $request->body()[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    private function positiveInt(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) $value) : 0;
    }
}
