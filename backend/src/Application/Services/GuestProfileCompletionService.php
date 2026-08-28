<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\GuestProfileUpdateRequest;
use App\Infrastructure\Persistence\VisitorProfileRepositoryInterface;

final class GuestProfileCompletionService
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly VisitorProfileRepositoryInterface $profiles,
    ) {
    }

    public function complete(string $token, string $code): bool
    {
        $payload = $this->otp->verify(trim($token), trim($code));
        if ($payload === null || ($payload['flow'] ?? '') !== 'guest_profile_update') {
            return false;
        }

        $visitorId = $payload['visitor_id'] ?? '';
        if (!is_numeric($visitorId)) {
            return false;
        }

        $this->profiles->updateProfile((int) $visitorId, new GuestProfileUpdateRequest(
            $payload['contact_no'] ?? '',
            $payload['email'] ?? '',
            $payload['house_no'] ?? '',
            $payload['street'] ?? '',
            $payload['barangay'] ?? '',
            $payload['municipality'] ?? '',
            $payload['province'] ?? '',
            $payload['purpose'] ?? '',
            $payload['purpose_other'] ?? '',
        ));

        return true;
    }
}
