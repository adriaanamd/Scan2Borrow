<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\GuestRegistrationRequest;
use App\Application\DTO\RegistrationResult;
use App\Application\Validators\GuestRegistrationValidator;
use App\Infrastructure\Persistence\VisitorRegistrationRepositoryInterface;

final class GuestRegistrationService
{
    public function __construct(
        private readonly GuestRegistrationValidator $validator,
        private readonly VisitorRegistrationRepositoryInterface $visitors,
        private readonly RegistrationOtpInterface $otp,
    ) {
    }

    public function begin(GuestRegistrationRequest $request): RegistrationResult
    {
        $validationError = $this->validator->firstError($request);
        if ($validationError !== null) {
            return RegistrationResult::failure($validationError);
        }

        if ($this->visitors->existsByIdBarcode($request->idBarcode)) {
            return RegistrationResult::failure('This government ID barcode has already been registered.');
        }

        $token = 'GUEST-' . strtoupper(bin2hex(random_bytes(12)));
        $this->otp->start($token, $this->payload($request), $request->contactNo);

        return RegistrationResult::success($token);
    }

    /** @return array<string, string> */
    private function payload(GuestRegistrationRequest $request): array
    {
        return [
            'registration_type' => 'guest',
            'firstname' => $request->firstname,
            'middlename' => $request->middlename,
            'lastname' => $request->lastname,
            'suffix' => $request->suffix,
            'gender' => $request->gender,
            'birthdate' => $request->birthdate,
            'contact_no' => $request->contactNo,
            'email' => $request->email,
            'house_no' => $request->houseNo,
            'street' => $request->street,
            'barangay' => $request->barangay,
            'municipality' => $request->municipality,
            'province' => $request->province,
            'purpose' => $request->purpose,
            'purpose_other' => $request->purposeOther,
            'id_type' => $request->idType,
            'id_barcode' => $request->idBarcode,
            'photo_data' => $request->photoData,
        ];
    }
}
