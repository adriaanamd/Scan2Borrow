<?php

declare(strict_types=1);

namespace Tests\Unit\Guest;

use App\Application\DTO\GuestProfileUpdateRequest;
use App\Application\Services\GuestProfileService;
use App\Application\Services\RegistrationOtpInterface;
use App\Domain\Guest\VisitorProfile;
use App\Infrastructure\Persistence\VisitorProfileRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class GuestProfileServiceTest extends TestCase
{
    public function testChangedContactStartsOtpInsteadOfUpdatingImmediately(): void
    {
        $repository = new FakeVisitorProfileRepository();
        $otp = new GuestProfileOtp();
        $service = new GuestProfileService($repository, $otp);

        $result = $service->update(
            new VisitorProfile(5, '09170000005'),
            new GuestProfileUpdateRequest('09170000006', 'guest@example.com', '1', 'Main', 'Central', 'Manila', 'Metro Manila', 'Research', ''),
        );

        self::assertTrue($result->requiresVerification());
        self::assertSame('GUEST-PROFILE', $otp->barcode);
        self::assertSame('guest_profile_update', $otp->payload['flow']);
        self::assertSame('5', $otp->payload['visitor_id']);
        self::assertFalse($repository->updated);
    }

    public function testSameContactUpdatesProfileWithoutOtp(): void
    {
        $repository = new FakeVisitorProfileRepository();
        $service = new GuestProfileService($repository, new GuestProfileOtp());

        $result = $service->update(
            new VisitorProfile(6, '09170000006'),
            new GuestProfileUpdateRequest('09170000006', '', '2', 'Second', 'North', 'Quezon City', 'Metro Manila', 'Others', 'Review'),
        );

        self::assertTrue($result->updated());
        self::assertSame(6, $repository->updatedId);
    }
}

final class FakeVisitorProfileRepository implements VisitorProfileRepositoryInterface
{
    public bool $updated = false;

    public ?int $updatedId = null;

    public function updateProfile(int $visitorId, GuestProfileUpdateRequest $request): void
    {
        $this->updated = true;
        $this->updatedId = $visitorId;
    }
}

final class GuestProfileOtp implements RegistrationOtpInterface
{
    public string $barcode = '';

    /**
     * @var array<string, string>
     */
    public array $payload = [];

    /**
     * @param array<string, string> $payload
     */
    public function start(string $barcode, array $payload, string $phoneNumber): string
    {
        $this->barcode = 'GUEST-PROFILE';
        $this->payload = $payload;

        return '123456';
    }
}
