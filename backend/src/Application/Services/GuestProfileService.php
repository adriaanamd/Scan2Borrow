<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\GuestProfileUpdateRequest;
use App\Application\DTO\GuestProfileUpdateResult;
use App\Domain\Guest\VisitorProfile;
use App\Infrastructure\Persistence\VisitorProfileRepositoryInterface;

final class GuestProfileService
{
    public function __construct(
        private readonly VisitorProfileRepositoryInterface $profiles,
        private readonly RegistrationOtpInterface $otp,
    ) {
    }

    public function update(
        VisitorProfile $visitor,
        GuestProfileUpdateRequest $request,
    ): GuestProfileUpdateResult {
        if ($request->contactNo !== $visitor->contactNo()) {
            $barcode = 'GUEST-UPD-' . strtoupper(bin2hex(random_bytes(10)));
            $this->otp->start($barcode, $this->otpPayload($visitor, $request), $request->contactNo);

            return GuestProfileUpdateResult::verificationRequiredFor($barcode);
        }

        $this->profiles->updateProfile($visitor->id(), $request);

        return GuestProfileUpdateResult::success();
    }

    /**
     * @return array<string, string>
     */
    private function otpPayload(VisitorProfile $visitor, GuestProfileUpdateRequest $request): array
    {
        return [
            'flow' => 'guest_profile_update',
            'visitor_id' => (string) $visitor->id(),
            'contact_no' => $request->contactNo,
            'email' => $request->email,
            'house_no' => $request->houseNo,
            'street' => $request->street,
            'barangay' => $request->barangay,
            'municipality' => $request->municipality,
            'province' => $request->province,
            'purpose' => $request->purpose,
            'purpose_other' => $request->purposeOther,
        ];
    }
}
