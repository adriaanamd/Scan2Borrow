<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\DTO\GuestRegistrationRequest;
use App\Application\Services\CsrfService;
use App\Application\Services\GuestRegistrationCompletionService;
use App\Application\Services\GuestRegistrationService;
use App\Application\Services\GuestProfileCompletionService;
use App\Application\Services\OtpService;
use App\Application\Services\SessionService;
use App\Http\Requests\ServerRequest;
use App\Http\Responses\JsonResponse;
use InvalidArgumentException;

final readonly class GuestAuthController
{
    public function __construct(
        private GuestRegistrationService $registrations,
        private GuestRegistrationCompletionService $completion,
        private OtpService $otp,
        private SessionService $sessions,
        private CsrfService $csrf,
        private ?GuestProfileCompletionService $profileCompletion = null,
    ) {
    }

    public function register(ServerRequest $request): JsonResponse
    {
        $csrf = $this->csrfFailure($request);
        if ($csrf !== null) {
            return $csrf;
        }

        $body = $request->body();
        $result = $this->registrations->begin(new GuestRegistrationRequest(
            $this->string($body, 'firstname'),
            $this->string($body, 'middlename'),
            $this->string($body, 'lastname'),
            $this->string($body, 'suffix'),
            $this->string($body, 'gender'),
            $this->string($body, 'birthdate'),
            $this->string($body, 'contact_no'),
            $this->string($body, 'email'),
            $this->string($body, 'house_no'),
            $this->string($body, 'street'),
            $this->string($body, 'barangay'),
            $this->string($body, 'municipality'),
            $this->string($body, 'province'),
            $this->string($body, 'purpose'),
            $this->string($body, 'purpose_other'),
            $this->string($body, 'id_type'),
            $this->string($body, 'id_barcode'),
            $this->string($body, 'photo_data'),
        ));
        if (!$result->successful()) {
            return new JsonResponse(422, ['ok' => false, 'errors' => [$result->message()]]);
        }

        $this->sessions->setGuestOtpToken((string) $result->barcode());

        return new JsonResponse(200, ['ok' => true, 'data' => ['redirect' => '/guest/verify-otp']]);
    }

    public function verify(ServerRequest $request): JsonResponse
    {
        $csrf = $this->csrfFailure($request);
        if ($csrf !== null) {
            return $csrf;
        }

        $token = $this->sessions->guestOtpToken();
        $verified = str_starts_with($token, 'GUEST-UPD-')
            ? ($this->profileCompletion?->complete($token, $this->string($request->body(), 'otp')) ?? false)
            : $this->completion->complete($token, $this->string($request->body(), 'otp'));
        if (!$verified) {
            return new JsonResponse(422, ['ok' => false, 'errors' => ['Invalid or expired OTP code. Please try again.']]);
        }

        if (str_starts_with($token, 'GUEST-UPD-')) {
            $this->sessions->clearRegistrationState();

            return new JsonResponse(200, ['ok' => true, 'message' => 'Profile updated.', 'data' => ['redirect' => '/guest/profile']]);
        }

        $this->sessions->clearRegistrationState();

        return new JsonResponse(200, ['ok' => true, 'data' => ['redirect' => '/guest/dashboard']]);
    }

    public function resend(ServerRequest $request): JsonResponse
    {
        $csrf = $this->csrfFailure($request);
        if ($csrf !== null) {
            return $csrf;
        }

        $code = $this->otp->resend($this->sessions->guestOtpToken());
        if ($code === null) {
            return new JsonResponse(422, ['ok' => false, 'errors' => ['Unable to resend OTP. Please try again later.']]);
        }

        return new JsonResponse(200, ['ok' => true, 'message' => 'New OTP code sent successfully!', 'data' => []]);
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
}
