<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\DTO\GuestProfileUpdateRequest;
use App\Application\Services\CsrfService;
use App\Application\Services\GuestProfileService;
use App\Application\Services\SessionService;
use App\Http\Requests\ServerRequest;
use App\Http\Responses\JsonResponse;
use App\Infrastructure\Persistence\VisitorDetailsRepositoryInterface;
use App\Infrastructure\Session\GuestIdentityProviderInterface;
use InvalidArgumentException;

final readonly class GuestDetailsController
{
    public function __construct(
        private GuestIdentityProviderInterface $identity,
        private VisitorDetailsRepositoryInterface $details,
        private GuestProfileService $profiles,
        private SessionService $sessions,
        private CsrfService $csrf,
    ) {
    }

    public function profile(ServerRequest $request): JsonResponse
    {
        $visitor = $this->identity->current();
        if ($visitor === null) {
            return $this->unauthorized();
        }
        if ($request->method() === 'GET') {
            return new JsonResponse(200, ['ok' => true, 'data' => $this->details->find($visitor->id()) ?? []]);
        }
        $csrf = $this->csrfFailure($request);
        if ($csrf !== null) {
            return $csrf;
        }

        $body = $request->body();
        $current = $this->details->find($visitor->id()) ?? [];
        $currentContact = is_string($current['contact_no'] ?? null) ? $current['contact_no'] : '';
        $result = $this->profiles->update(new \App\Domain\Guest\VisitorProfile($visitor->id(), $currentContact), new GuestProfileUpdateRequest(
            $this->string($body, 'contact_no'),
            $this->string($body, 'email'),
            $this->string($body, 'house_no'),
            $this->string($body, 'street'),
            $this->string($body, 'barangay'),
            $this->string($body, 'municipality'),
            $this->string($body, 'province'),
            $this->string($body, 'purpose'),
            $this->string($body, 'purpose_other'),
        ));
        if ($result->requiresVerification()) {
            $this->sessions->setGuestOtpToken((string) $result->verificationToken());

            return new JsonResponse(200, ['ok' => true, 'data' => ['redirect' => $request->applicationPath('/guest/profile-verify-otp')]]);
        }

        return new JsonResponse(200, ['ok' => true, 'message' => 'Profile updated.', 'data' => []]);
    }

    public function pass(ServerRequest $request): JsonResponse
    {
        $visitor = $this->identity->current();
        if ($visitor === null) {
            return $this->unauthorized();
        }

        return new JsonResponse(200, ['ok' => true, 'data' => $this->details->pass($visitor->id()) ?? []]);
    }

    /** @param array<string, mixed> $body */
    private function string(array $body, string $key): string
    {
        $value = $body[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    private function csrfFailure(ServerRequest $request): ?JsonResponse
    {
        try {
            $this->csrf->assertValid($this->string($request->body(), 'csrf'));
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(419, ['ok' => false, 'errors' => [$exception->getMessage()]]);
        }

        return null;
    }

    private function unauthorized(): JsonResponse
    {
        return new JsonResponse(401, ['ok' => false, 'errors' => ['Guest authentication required.']]);
    }
}
