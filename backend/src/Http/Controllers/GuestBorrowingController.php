<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\DTO\GuestBorrowRequest;
use App\Application\DTO\GuestReturnVerificationRequest;
use App\Application\Services\CsrfService;
use App\Application\Services\GuestBorrowingService;
use App\Application\Services\GuestPortalService;
use App\Http\Requests\ServerRequest;
use App\Http\Responses\JsonResponse;
use App\Infrastructure\Session\GuestIdentityProviderInterface;
use InvalidArgumentException;

final readonly class GuestBorrowingController
{
    public function __construct(
        private GuestIdentityProviderInterface $identity,
        private GuestPortalService $portal,
        private GuestBorrowingService $borrowings,
        private CsrfService $csrf,
    ) {
    }

    public function dashboard(ServerRequest $request): JsonResponse
    {
        $visitor = $this->visitor();
        if ($visitor === null) {
            return $this->unauthorized();
        }

        return new JsonResponse(200, ['ok' => true, 'data' => $this->portal->dashboard($visitor->id())]);
    }

    public function browse(ServerRequest $request): JsonResponse
    {
        if ($this->visitor() === null) {
            return $this->unauthorized();
        }

        return new JsonResponse(200, ['ok' => true, 'data' => $this->portal->browse($request->query())]);
    }

    public function history(ServerRequest $request): JsonResponse
    {
        $visitor = $this->visitor();
        if ($visitor === null) {
            return $this->unauthorized();
        }

        return new JsonResponse(200, ['ok' => true, 'data' => [
            'history' => $this->portal->history(
                $visitor->id(),
                $this->queryString($request, 'status'),
                $this->queryString($request, 'from'),
                $this->queryString($request, 'to'),
            ),
        ]]);
    }

    public function receipt(ServerRequest $request): JsonResponse
    {
        $visitor = $this->visitor();
        if ($visitor === null) {
            return $this->unauthorized();
        }

        $receipt = $this->portal->receipt($visitor->id(), $this->queryInt($request, 'id'));
        if ($receipt === null) {
            return new JsonResponse(404, ['ok' => false, 'errors' => ['Receipt not found. Invalid transaction code.']]);
        }

        return new JsonResponse(200, ['ok' => true, 'data' => $receipt]);
    }

    public function borrow(ServerRequest $request): JsonResponse
    {
        $visitor = $this->visitor();
        if ($visitor === null) {
            return $this->unauthorized();
        }

        $csrfFailure = $this->assertCsrf($request);
        if ($csrfFailure !== null) {
            return $csrfFailure;
        }

        $result = $this->borrowings->submitRequest($visitor, new GuestBorrowRequest(
            $this->bodyInt($request, 'book_id'),
            $this->bodyString($request, 'government_id_barcode'),
            $this->bodyString($request, 'verification_photo'),
        ));

        if (!$result->isSuccessful()) {
            return new JsonResponse(422, ['ok' => false, 'errors' => [$result->message()]]);
        }

        return new JsonResponse(200, ['ok' => true, 'data' => [
            'borrowing_id' => $result->borrowingId(),
            'status' => $result->status()?->value,
        ]]);
    }

    public function returnBook(ServerRequest $request): JsonResponse
    {
        $visitor = $this->visitor();
        if ($visitor === null) {
            return $this->unauthorized();
        }

        $csrfFailure = $this->assertCsrf($request);
        if ($csrfFailure !== null) {
            return $csrfFailure;
        }

        $result = $this->borrowings->submitReturnVerification($visitor, new GuestReturnVerificationRequest(
            $this->bodyString($request, 'book_barcode'),
            $this->bodyString($request, 'return_photo'),
        ));

        if (!$result->isSuccessful()) {
            return new JsonResponse(422, ['ok' => false, 'errors' => [$result->message()]]);
        }

        return new JsonResponse(200, ['ok' => true, 'data' => [
            'borrowing_id' => $result->borrowingId(),
            'status' => $result->status()?->value,
            'message' => $result->message(),
        ]]);
    }

    private function visitor(): ?\App\Domain\Guest\VisitorAccount
    {
        return $this->identity->current();
    }

    private function unauthorized(): JsonResponse
    {
        return new JsonResponse(401, ['ok' => false, 'errors' => ['Guest authentication required.']]);
    }

    private function assertCsrf(ServerRequest $request): ?JsonResponse
    {
        try {
            $this->csrf->assertValid($this->bodyString($request, 'csrf'));
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(419, ['ok' => false, 'errors' => [$exception->getMessage()]]);
        }

        return null;
    }

    private function bodyString(ServerRequest $request, string $key): string
    {
        $value = $request->body()[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    private function bodyInt(ServerRequest $request, string $key): int
    {
        $value = $request->body()[$key] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }

    private function queryString(ServerRequest $request, string $key): string
    {
        $value = $request->query()[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    private function queryInt(ServerRequest $request, string $key): int
    {
        $value = $request->query()[$key] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }
}
