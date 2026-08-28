<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\RegistrationRequest;
use App\Application\DTO\RegistrationResult;
use App\Application\Validators\RegistrationValidator;
use App\Infrastructure\Persistence\RegistrationUserRepositoryInterface;

final class RegistrationService
{
    public function __construct(
        private readonly RegistrationValidator $validator,
        private readonly RegistrationUserRepositoryInterface $users,
        private readonly RegistrationOtpInterface $otp,
    ) {
    }

    public function begin(RegistrationRequest $request): RegistrationResult
    {
        $validationError = $this->validator->firstError($request);
        if ($validationError !== null) {
            return RegistrationResult::failure($validationError);
        }

        if ($this->users->existsByBarcode($request->barcode)) {
            return RegistrationResult::failure('This Barcode ID is already registered.');
        }

        $this->otp->start($request->barcode, $this->payload($request), $request->contactNo);

        return RegistrationResult::success($request->barcode);
    }

    /**
     * @return array<string, string>
     */
    private function payload(RegistrationRequest $request): array
    {
        return [
            'barcode' => $request->barcode,
            'firstname' => $request->firstname,
            'middlename' => $request->middlename,
            'lastname' => $request->lastname,
            'department' => $request->department,
            'position' => $request->position,
            'course' => $request->course,
            'year_level' => $request->yearLevel,
            'email' => $request->email,
            'contact_no' => $request->contactNo,
            'role' => $request->role,
            'photo_data' => $request->photoData,
        ];
    }
}
