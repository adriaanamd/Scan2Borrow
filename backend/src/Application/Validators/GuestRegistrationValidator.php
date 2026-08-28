<?php

declare(strict_types=1);

namespace App\Application\Validators;

use App\Application\DTO\GuestRegistrationRequest;
use App\Application\Services\ClockInterface;
use DateTimeImmutable;

final class GuestRegistrationValidator
{
    /**
     * @var list<string>
     */
    private const GENDERS = ['Male', 'Female', 'Prefer not to say'];

    /**
     * @var list<string>
     */
    private const PURPOSES = ['Research', 'Reading', 'Thesis', 'Review', 'Others'];

    /**
     * @var list<string>
     */
    private const ID_TYPES = [
        'National ID', 'Driver\'s License', 'Passport', 'UMID', 'PRC ID', 'Postal ID',
        'PhilHealth ID', 'Voter\'s ID', 'Senior Citizen ID', 'Other Government-Issued ID',
    ];

    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    public function firstError(GuestRegistrationRequest $request): ?string
    {
        foreach ($this->requiredFields($request) as $value) {
            if ($value === '') {
                return 'Please complete all required fields.';
            }
        }

        if (!in_array($request->gender, self::GENDERS, true)) {
            return 'Please select a valid gender.';
        }

        if (!in_array($request->purpose, self::PURPOSES, true)) {
            return 'Please select a valid purpose of visit.';
        }

        if ($request->purpose === 'Others' && $request->purposeOther === '') {
            return 'Please specify the other purpose of visit.';
        }

        if (!in_array($request->idType, self::ID_TYPES, true)) {
            return 'Please select a valid government-issued ID type.';
        }

        if (preg_match('/^[0-9+\-\s()]{7,15}$/', $request->contactNo) !== 1) {
            return 'Please enter a valid mobile number.';
        }

        if ($request->email !== '' && filter_var($request->email, FILTER_VALIDATE_EMAIL) === false) {
            return 'Please enter a valid email address.';
        }

        $birthdate = DateTimeImmutable::createFromFormat('!Y-m-d', $request->birthdate);
        $today = $this->clock->now()->setTime(0, 0);
        if ($birthdate === false || $birthdate->format('Y-m-d') !== $request->birthdate || $birthdate > $today) {
            return 'Please enter a valid birthdate in the past.';
        }

        if ($request->photoData === '') {
            return 'A live visitor photo is required. Start the camera and capture your photo.';
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function requiredFields(GuestRegistrationRequest $request): array
    {
        return [
            $request->firstname,
            $request->lastname,
            $request->gender,
            $request->birthdate,
            $request->contactNo,
            $request->houseNo,
            $request->street,
            $request->barangay,
            $request->municipality,
            $request->province,
            $request->purpose,
            $request->idType,
            $request->idBarcode,
        ];
    }
}
