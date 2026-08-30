<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\DTO\RenewalDecisionRequest;
use App\Application\Services\CsrfService;
use App\Application\Services\RenewalApprovalService;
use App\Application\Services\SessionService;
use App\Domain\Auth\Role;
use App\Http\Requests\ServerRequest;
use App\Http\Responses\JsonResponse;
use App\Infrastructure\Persistence\RenewalRepositoryInterface;
use InvalidArgumentException;

final readonly class StaffRenewalController
{
    public function __construct(private SessionService $sessions, private CsrfService $csrf, private RenewalRepositoryInterface $repository, private RenewalApprovalService $approval) {}
    public function index(ServerRequest $request): JsonResponse
    {
        if (!$this->isStaff()) return $this->unauthorized();
        $records = $this->repository->listPending();
        return new JsonResponse(200, ['ok' => true, 'data' => ['renewals' => array_map(static fn (\App\Domain\Renewal\RenewalRecord $record): array => $record->toArray(), $records), 'summary' => ['pending' => count($records)]]]);
    }
    public function action(ServerRequest $request): JsonResponse
    {
        if (!$this->isStaff()) return $this->unauthorized();
        try { $this->csrf->assertValid($this->bodyString($request, 'csrf')); } catch (InvalidArgumentException $exception) { return new JsonResponse(419, ['ok' => false, 'errors' => [$exception->getMessage()]]); }
        $identity = $this->sessions->current();
        if ($identity === null) return $this->unauthorized();
        try { $decision = new RenewalDecisionRequest($this->positiveInt($request->body()['renewal_id'] ?? null), $identity->userId(), $this->bodyString($request, 'action'), $this->bodyString($request, 'note')); } catch (InvalidArgumentException $exception) { return new JsonResponse(422, ['ok' => false, 'errors' => [$exception->getMessage()]]); }
        $result = $this->approval->decide($decision);
        return $result->successful() ? new JsonResponse(200, ['ok' => true, 'data' => ['message' => $result->message()]]) : new JsonResponse(422, ['ok' => false, 'errors' => [$result->message()]]);
    }
    private function isStaff(): bool { $identity = $this->sessions->current(); return $identity !== null && in_array($identity->role(), [Role::ADMIN, Role::LIBRARIAN], true); }
    private function unauthorized(): JsonResponse { return new JsonResponse(401, ['ok' => false, 'errors' => ['Staff authentication required.']]); }
    private function bodyString(ServerRequest $request, string $key): string { $value = $request->body()[$key] ?? ''; return is_string($value) ? trim($value) : ''; }
    private function positiveInt(mixed $value): int { return is_numeric($value) ? max(0, (int) $value) : 0; }
}
