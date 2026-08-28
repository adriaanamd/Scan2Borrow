<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\GuestRegistrationRequest;
use App\Infrastructure\Persistence\VisitorRegistrationRepositoryInterface;

final class GuestRegistrationCompletionService
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly VisitorRegistrationRepositoryInterface $visitors,
        private readonly SessionService $sessions,
    ) {
    }

    public function complete(string $token, string $code): bool
    {
        $payload = $this->otp->verify(trim($token), trim($code));
        if ($payload === null || ($payload['registration_type'] ?? '') !== 'guest') {
            return false;
        }

        $request = new GuestRegistrationRequest(
            $payload['firstname'] ?? '',
            $payload['middlename'] ?? '',
            $payload['lastname'] ?? '',
            $payload['suffix'] ?? '',
            $payload['gender'] ?? '',
            $payload['birthdate'] ?? '',
            $payload['contact_no'] ?? '',
            $payload['email'] ?? '',
            $payload['house_no'] ?? '',
            $payload['street'] ?? '',
            $payload['barangay'] ?? '',
            $payload['municipality'] ?? '',
            $payload['province'] ?? '',
            $payload['purpose'] ?? '',
            $payload['purpose_other'] ?? '',
            $payload['id_type'] ?? '',
            $payload['id_barcode'] ?? '',
            $payload['photo_data'] ?? '',
        );
        $visitorId = $this->visitors->create($request);
        $this->sessions->loginGuest($visitorId);

        return true;
    }
}
