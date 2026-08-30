<?php

declare(strict_types=1);

namespace Tests\Unit\Registration;

use App\Application\DTO\RegistrationRequest;
use App\Application\Services\RegistrationService;
use App\Application\Services\RegistrationOtpInterface;
use App\Application\Validators\RegistrationValidator;
use App\Infrastructure\Persistence\RegistrationUserRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class RegistrationServiceTest extends TestCase
{
    public function testValidRegistrationStartsOtpWorkflow(): void
    {
        $otp = new FakeRegistrationOtp();
        $service = new RegistrationService(
            new RegistrationValidator(),
            new FakeRegistrationUserRepository(),
            $otp,
        );

        $result = $service->begin(new RegistrationRequest(
            '2024004', 'Lia', '', 'Santos', 'student', otpChannel: 'phone', course: 'BSIT', yearLevel: '1', contactNo: '09170000004',
        ));

        self::assertTrue($result->successful());
        self::assertSame('2024004', $result->barcode());
        self::assertSame('2024004', $otp->barcode);
        self::assertSame('phone', $otp->payload['otp_channel']);
    }

    public function testDuplicateRegistrationKeepsCurrentMessage(): void
    {
        $repository = new FakeRegistrationUserRepository();
        $repository->existing = true;
        $service = new RegistrationService(new RegistrationValidator(), $repository, new FakeRegistrationOtp());

        $result = $service->begin(new RegistrationRequest(
            '2024001', 'Juan', '', 'Cruz', 'student', otpChannel: 'phone', course: 'BSIT', yearLevel: '3', contactNo: '09170000001',
        ));

        self::assertFalse($result->successful());
        self::assertSame('This Barcode ID is already registered.', $result->message());
    }
}

final class FakeRegistrationUserRepository implements RegistrationUserRepositoryInterface
{
    public bool $existing = false;

    public function existsByBarcode(string $barcode): bool
    {
        return $this->existing;
    }
}

final class FakeRegistrationOtp implements RegistrationOtpInterface
{
    public string $barcode = '';

    /** @var array<string, string> */
    public array $payload = [];

    /**
     * @param array<string, string> $payload
     */
    public function start(string $barcode, array $payload, string $phoneNumber): string
    {
        $this->barcode = $barcode;
        $this->payload = $payload;

        return '123456';
    }
}
