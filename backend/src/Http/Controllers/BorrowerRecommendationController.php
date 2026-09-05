<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\CsrfService;
use App\Application\Services\RecommendationService;
use App\Application\Services\SearchHistoryService;
use App\Application\Services\SessionService;
use App\Domain\Auth\Role;
use App\Domain\Auth\SessionIdentity;
use App\Http\Requests\ServerRequest;
use App\Http\Responses\JsonResponse;
use InvalidArgumentException;

final readonly class BorrowerRecommendationController
{
    public function __construct(
        private SessionService $sessions,
        private CsrfService $csrf,
        private SearchHistoryService $history,
        private RecommendationService $recommendations,
    ) {
    }

    public function recordSearch(ServerRequest $request): JsonResponse
    {
        $identity = $this->borrower();
        if ($identity === null) {
            return $this->unauthorized();
        }

        try {
            $this->csrf->assertValid($this->string($request->body(), 'csrf'));
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(419, ['ok' => false, 'errors' => [$exception->getMessage()]]);
        }

        try {
            $this->history->record($identity->userId(), $this->string($request->body(), 'search'));
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(422, ['ok' => false, 'errors' => [$exception->getMessage()]]);
        }

        return new JsonResponse(200, ['ok' => true, 'data' => ['recorded' => true]]);
    }

    public function index(ServerRequest $request): JsonResponse
    {
        $identity = $this->borrower();
        if ($identity === null) {
            return $this->unauthorized();
        }

        $result = $this->recommendations->forBorrower($identity->userId());

        return new JsonResponse(200, [
            'ok' => true,
            'data' => [
                'books' => $result->books(),
                'personalized' => $result->personalized(),
            ],
        ]);
    }

    private function borrower(): ?SessionIdentity
    {
        $identity = $this->sessions->current();

        return $identity !== null && in_array($identity->role(), [Role::STUDENT, Role::TEACHER], true)
            ? $identity
            : null;
    }

    private function unauthorized(): JsonResponse
    {
        return new JsonResponse(401, ['ok' => false, 'errors' => ['Borrower authentication required.']]);
    }

    /** @param array<string, mixed> $input */
    private function string(array $input, string $key): string
    {
        $value = $input[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }
}
