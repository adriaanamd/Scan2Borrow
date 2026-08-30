<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\DTO\RegistrationRequest;
use App\Application\Services\CsrfService;
use App\Application\Services\OtpDeliveryException;
use App\Application\Services\OtpService;
use App\Application\Services\RegistrationCompletionService;
use App\Application\Services\RegistrationService;
use App\Application\Services\SessionService;
use App\Http\Requests\ServerRequest;
use App\Http\Responses\JsonResponse;
use InvalidArgumentException;

final readonly class RegistrationController
{
    public function __construct(
        private RegistrationService $registrations,
        private RegistrationCompletionService $completion,
        private OtpService $otp,
        private SessionService $sessions,
        private CsrfService $csrf,
    ) {
    }

    public function begin(ServerRequest $request): JsonResponse
    {
        $csrf = $this->csrfFailure($request);
        if ($csrf !== null) {
            return $csrf;
        }

        $body = $request->body();
        try {
            $result = $this->registrations->begin(new RegistrationRequest(
                $this->string($body, 'barcode'),
                $this->string($body, 'firstname'),
                $this->string($body, 'middlename'),
                $this->string($body, 'lastname'),
                $this->string($body, 'role'),
                $this->string($body, 'department'),
                $this->string($body, 'position'),
                $this->string($body, 'course'),
                $this->string($body, 'year_level'),
                otpChannel: $this->string($body, 'otp_channel'),
                email: $this->string($body, 'email'),
                contactNo: $this->string($body, 'contact_no'),
                photoData: $this->string($body, 'photo_data'),
            ));
        } catch (OtpDeliveryException $exception) {
            return new JsonResponse(503, ['ok' => false, 'errors' => [$exception->getMessage()]]);
        }
        if (!$result->successful()) {
            return new JsonResponse(422, ['ok' => false, 'errors' => [$result->message()]]);
        }

        $this->sessions->setRegistrationBarcode((string) $result->barcode());

        return new JsonResponse(200, [
            'ok' => true,
            'data' => [
                'redirect' => $request->applicationPath('/verify-otp'),
                'barcode' => $result->barcode(),
                'channel' => $this->string($body, 'otp_channel'),
            ],
        ]);
    }

    public function verify(ServerRequest $request): JsonResponse
    {
        $csrf = $this->csrfFailure($request);
        if ($csrf !== null) {
            return $csrf;
        }

        $barcode = $this->sessions->registrationBarcode();
        if ($barcode === '' || !$this->completion->complete($barcode, $this->string($request->body(), 'otp'))) {
            return new JsonResponse(422, ['ok' => false, 'errors' => ['Invalid or expired OTP code. Please try again.']]);
        }

        $this->sessions->clearRegistrationState();

        return new JsonResponse(200, [
            'ok' => true,
            'message' => 'Registration successful! You can now use your Barcode ID to log in.',
            'data' => ['redirect' => $request->applicationPath('/login')],
        ]);
    }

    public function resend(ServerRequest $request): JsonResponse
    {
        $csrf = $this->csrfFailure($request);
        if ($csrf !== null) {
            return $csrf;
        }

        try {
            $code = $this->otp->resend($this->sessions->registrationBarcode());
        } catch (OtpDeliveryException $exception) {
            return new JsonResponse(503, ['ok' => false, 'errors' => [$exception->getMessage()]]);
        }
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
