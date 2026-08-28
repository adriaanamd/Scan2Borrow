<?php

declare(strict_types=1);

namespace Tests\Unit\Guest;

use App\Application\DTO\GuestRegistrationRequest;
use App\Application\Services\ClockInterface;
use App\Application\Validators\GuestRegistrationValidator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GuestRegistrationValidatorTest extends TestCase
{
    public function testRequiresAllCurrentRequiredFields(): void
    {
        $request = new GuestRegistrationRequest();

        self::assertSame('Please complete all required fields.', $this->validator()->firstError($request));
    }

    public function testPreservesSelectionAndConditionalPurposeMessages(): void
    {
        $request = $this->validRequest()->withGender('Unknown');
        self::assertSame('Please select a valid gender.', $this->validator()->firstError($request));

        $request = $this->validRequest()->withPurpose('Other');
        self::assertSame('Please select a valid purpose of visit.', $this->validator()->firstError($request));

        $request = $this->validRequest()->withPurpose('Others')->withPurposeOther('');
        self::assertSame('Please specify the other purpose of visit.', $this->validator()->firstError($request));
    }

    public function testPreservesContactEmailBirthdateAndPhotoMessages(): void
    {
        $validator = $this->validator();
        self::assertSame('Please enter a valid mobile number.', $validator->firstError($this->validRequest()->withContactNo('bad')));
        self::assertSame('Please enter a valid email address.', $validator->firstError($this->validRequest()->withEmail('bad')));
        self::assertSame('Please enter a valid birthdate in the past.', $validator->firstError($this->validRequest()->withBirthdate('2030-01-01')));
        self::assertSame('A live visitor photo is required. Start the camera and capture your photo.', $validator->firstError($this->validRequest()->withPhotoData('')));
    }

    public function testAcceptsCompleteVisitorPayload(): void
    {
        self::assertNull($this->validator()->firstError($this->validRequest()));
    }

    private function validator(): GuestRegistrationValidator
    {
        return new GuestRegistrationValidator(new GuestFixedClock());
    }

    private function validRequest(): GuestRegistrationRequest
    {
        return new GuestRegistrationRequest(
            firstname: 'Ana', lastname: 'Reyes', gender: 'Female', birthdate: '2000-01-01',
            contactNo: '09170000005', houseNo: '1', street: 'Main', barangay: 'Central',
            municipality: 'Manila', province: 'Metro Manila', purpose: 'Research', idType: 'National ID',
            idBarcode: 'ID-0005', photoData: 'data:image/jpeg;base64,photo',
        );
    }
}

final class GuestFixedClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-28 10:00:00');
    }
}
