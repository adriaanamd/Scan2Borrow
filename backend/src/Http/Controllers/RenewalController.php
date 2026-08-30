<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\DTO\RenewalRequest;
use App\Application\Services\CsrfService;
use App\Application\Services\RenewalService;
use App\Application\Services\SessionService;
use App\Domain\Auth\Role;
use App\Http\Requests\ServerRequest;
use App\Http\Responses\JsonResponse;
use InvalidArgumentException;

final readonly class RenewalController
{
    public function __construct(private SessionService $sessions, private CsrfService $csrf, private RenewalService $renewals) {}

    public function list(ServerRequest $request): JsonResponse
    {
        $identity = $this->borrower();
        if ($identity === null) return $this->unauthorized();
        $records = $this->renewals->list($identity->userId());
        $summary = ['total' => count($records), 'pending' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($records as $record) {
            if (isset($summary[$record->status()->value])) $summary[$record->status()->value]++;
        }
        return new JsonResponse(200, ['ok' => true, 'data' => ['renewals' => array_map(static fn (\App\Domain\Renewal\RenewalRecord $record): array => $record->toArray(), $records), 'summary' => $summary]]);
    }

    public function create(ServerRequest $request): JsonResponse
    {
        $identity = $this->borrower();
        if ($identity === null) return $this->unauthorized();
        try { $this->csrf->assertValid($this->value($request, 'csrf')); } catch (InvalidArgumentException $exception) { return new JsonResponse(419, ['ok' => false, 'errors' => [$exception->getMessage()]]); }
        try {
            $result = $this->renewals->request(new RenewalRequest($identity->userId(), $this->positiveInt($request->body()['loan_id'] ?? null), $this->value($request, 'reason')));
        } catch (InvalidArgumentException $exception) { return new JsonResponse(422, ['ok' => false, 'errors' => [$exception->getMessage()]]); }
        return $result->successful() ? new JsonResponse(200, ['ok' => true, 'data' => ['message' => $result->message(), 'renewal' => $result->record()?->toArray()]]) : new JsonResponse(422, ['ok' => false, 'errors' => [$result->message()]]);
    }

    private function borrower(): ?\App\Domain\Auth\SessionIdentity
    {
        $identity = $this->sessions->current();
        return $identity !== null && in_array($identity->role(), [Role::STUDENT, Role::TEACHER], true) ? $identity : null;
    }
    private function unauthorized(): JsonResponse { return new JsonResponse(401, ['ok' => false, 'errors' => ['Borrower authentication required.']]); }
    private function value(ServerRequest $request, string $key): string { $value = $request->body()[$key] ?? ''; return is_string($value) ? trim($value) : ''; }
    private function positiveInt(mixed $value): int { return is_numeric($value) ? max(0, (int) $value) : 0; }
}
