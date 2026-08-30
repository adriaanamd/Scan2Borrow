<?php

declare(strict_types=1);

namespace Tests\Unit\Registration;

use App\Application\Services\ClockInterface;
use App\Application\Services\EmailSenderInterface;
use App\Application\Services\OtpService;
use App\Application\Services\OtpDeliveryException;
use App\Application\Services\SmsSenderInterface;
use App\Domain\Registration\OtpRecord;
use App\Infrastructure\Persistence\OtpRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class OtpServiceTest extends TestCase
{
    public function testStartStoresSixDigitCodeWithFiveMinuteExpiry(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-08-28 10:00:00'));
        $repository = new FakeOtpRepository();
        $service = new OtpService($repository, $clock, new FakeSmsSender(), new FakeEmailSender());

        $code = $service->start('2024004', ['role' => 'student'], '09170000004');
        $record = $repository->record;
        self::assertInstanceOf(OtpRecord::class, $record);

        self::assertMatchesRegularExpression('/^[0-9]{6}$/', $code);
        self::assertSame('2024004', $record->barcode());
        self::assertSame('09170000004', $record->phoneNumber());
        self::assertSame(['role' => 'student'], $record->payload());
        self::assertSame('2026-08-28 10:05:00', $record->expiresAt()->format('Y-m-d H:i:s'));
    }

    public function testVerifyMarksLatestValidCodeUsedAndReturnsPayload(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-08-28 10:00:00'));
        $repository = new FakeOtpRepository();
        $repository->record = OtpRecord::pending(
            7,
            '2024004',
            '123456',
            '09170000004',
            ['role' => 'student'],
            $clock->now()->modify('+5 minutes'),
            $clock->now(),
        );
        $service = new OtpService($repository, $clock, new FakeSmsSender(), new FakeEmailSender());

        self::assertSame(['role' => 'student'], $service->verify('2024004', '123456'));
        self::assertTrue($repository->record->used());
        self::assertNull($service->verify('2024004', '123456'));
    }

    public function testExpiredCodeCannotBeVerified(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-08-28 10:06:00'));
        $repository = new FakeOtpRepository();
        $repository->record = OtpRecord::pending(
            8,
            '2024004',
            '123456',
            '09170000004',
            ['role' => 'student'],
            new DateTimeImmutable('2026-08-28 10:05:00'),
            new DateTimeImmutable('2026-08-28 10:00:00'),
        );
        $service = new OtpService($repository, $clock, new FakeSmsSender(), new FakeEmailSender());

        self::assertNull($service->verify('2024004', '123456'));
    }

    public function testResendIsBlockedForSixtySecondsThenUpdatesExistingRecord(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-08-28 10:00:30'));
        $repository = new FakeOtpRepository();
        $repository->record = OtpRecord::pending(
            9,
            '2024004',
            '123456',
            '09170000004',
            ['role' => 'student'],
            $clock->now()->modify('+5 minutes'),
            new DateTimeImmutable('2026-08-28 10:00:00'),
        );
        $service = new OtpService($repository, $clock, new FakeSmsSender(), new FakeEmailSender());

        self::assertNull($service->resend('2024004'));

        $clock->advance('+30 seconds');
        $newCode = $service->resend('2024004');

        self::assertMatchesRegularExpression('/^[0-9]{6}$/', (string) $newCode);
        self::assertNotSame('123456', $newCode);
        self::assertSame(9, $repository->updatedId);
    }

    public function testStartSendsOtpToPayloadEmail(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-08-28 10:00:00'));
        $repository = new FakeOtpRepository();
        $sms = new FakeSmsSender();
        $email = new FakeEmailSender();
        $service = new OtpService($repository, $clock, $sms, $email);

        $service->start('2024004', [
            'firstname' => 'Lia',
            'lastname' => 'Santos',
            'email' => 'lia@example.test',
        ], '09170000004');

        self::assertSame('lia@example.test', $email->to);
        self::assertSame('Lia Santos', $email->name);
        self::assertSame('', $sms->phoneNumber);
        self::assertStringContainsString('Your OTP code is:', $email->html);
        self::assertStringContainsString('BINALBAGAN CATHOLIC COLLEGE', $email->html);
        self::assertStringContainsString('Scan2Borrow Library Services', $email->html);
        self::assertStringContainsString('cid:scan2borrow-school-seal', $email->html);
    }

    public function testExplicitPhoneChannelWinsWhenEmailIsAlsoPresent(): void
    {
        $sms = new FakeSmsSender();
        $email = new FakeEmailSender();
        $service = new OtpService(
            new FakeOtpRepository(),
            new FixedClock(new DateTimeImmutable('2026-08-28 10:00:00')),
            $sms,
            $email,
        );

        $service->start('2024004', [
            'otp_channel' => 'phone',
            'email' => 'lia@example.test',
        ], '09170000004');

        self::assertSame('09170000004', $sms->phoneNumber);
        self::assertSame('', $email->to);
    }

    public function testExplicitEmailChannelWinsWhenPhoneIsAlsoPresent(): void
    {
        $sms = new FakeSmsSender();
        $email = new FakeEmailSender();
        $service = new OtpService(
            new FakeOtpRepository(),
            new FixedClock(new DateTimeImmutable('2026-08-28 10:00:00')),
            $sms,
            $email,
        );

        $service->start('2024004', [
            'otp_channel' => 'email',
            'contact_no' => '09170000004',
            'email' => 'lia@example.test',
        ], '09170000004');

        self::assertSame('lia@example.test', $email->to);
        self::assertSame('', $sms->phoneNumber);
    }

    public function testResendSendsUpdatedOtpToPayloadEmail(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-08-28 10:01:00'));
        $repository = new FakeOtpRepository();
        $repository->record = OtpRecord::pending(
            9,
            '2024004',
            '123456',
            '09170000004',
            ['firstname' => 'Lia', 'lastname' => 'Santos', 'email' => 'lia@example.test'],
            $clock->now()->modify('+5 minutes'),
            new DateTimeImmutable('2026-08-28 10:00:00'),
        );
        $email = new FakeEmailSender();
        $clock->advance('+60 seconds');
        $service = new OtpService($repository, $clock, new FakeSmsSender(), $email);

        $code = $service->resend('2024004');

        self::assertMatchesRegularExpression('/^[0-9]{6}$/', (string) $code);
        self::assertSame('lia@example.test', $email->to);
        self::assertStringContainsString('Your new OTP code is:', $email->html);
    }

    public function testUnconfiguredEmailDeliveryRaisesSafeException(): void
    {
        $this->expectException(OtpDeliveryException::class);

        $service = new OtpService(
            new FakeOtpRepository(),
            new FixedClock(new DateTimeImmutable('2026-08-28 10:00:00')),
            new FakeSmsSender(),
            new FakeEmailSender(false),
        );

        $service->start('2024004', ['email' => 'lia@example.test'], '09170000004');
    }

    public function testEmailSendFailureRaisesSafeException(): void
    {
        $this->expectException(OtpDeliveryException::class);

        $service = new OtpService(
            new FakeOtpRepository(),
            new FixedClock(new DateTimeImmutable('2026-08-28 10:00:00')),
            new FakeSmsSender(),
            new FakeEmailSender(true, false),
        );

        $service->start('2024004', ['email' => 'lia@example.test'], '09170000004');
    }

    public function testStartUsesSmsWhenPayloadEmailIsAbsent(): void
    {
        $sms = new FakeSmsSender();
        $service = new OtpService(
            new FakeOtpRepository(),
            new FixedClock(new DateTimeImmutable('2026-08-28 10:00:00')),
            $sms,
            new FakeEmailSender(false),
        );

        $service->start('2024004', ['role' => 'student'], '09170000004');

        self::assertSame('09170000004', $sms->phoneNumber);
        self::assertStringContainsString('Your OTP code is:', $sms->message);
    }
}

final class FixedClock implements ClockInterface
{
    private DateTimeImmutable $current;

    public function __construct(DateTimeImmutable $current)
    {
        $this->current = $current;
    }

    public function now(): DateTimeImmutable
    {
        return $this->current;
    }

    public function advance(string $interval): void
    {
        $this->current = $this->current->modify($interval);
    }
}

final class FakeOtpRepository implements OtpRepositoryInterface
{
    public ?OtpRecord $record = null;

    public ?int $updatedId = null;

    public function deleteExpired(DateTimeImmutable $now, string $barcode): void
    {
    }

    public function create(OtpRecord $record): void
    {
        $this->record = $record;
    }

    public function latestUnused(string $barcode): ?OtpRecord
    {
        if ($this->record === null || $this->record->barcode() !== $barcode || $this->record->used()) {
            return null;
        }

        return $this->record;
    }

    public function markUsed(int $id): void
    {
        if ($this->record !== null && $this->record->id() === $id) {
            $this->record = $this->record->usedCopy();
        }
    }

    public function updateCode(int $id, string $code, DateTimeImmutable $expiresAt): void
    {
        $this->updatedId = $id;
        if ($this->record !== null && $this->record->id() === $id) {
            $this->record = $this->record->withCode($code, $expiresAt);
        }
    }
}

final class FakeSmsSender implements SmsSenderInterface
{
    public string $phoneNumber = '';

    public string $message = '';

    public function send(string $phoneNumber, string $message): void
    {
        $this->phoneNumber = $phoneNumber;
        $this->message = $message;
    }
}

final class FakeEmailSender implements EmailSenderInterface
{
    public string $to = '';

    public string $name = '';

    public string $subject = '';

    public string $html = '';

    public function __construct(
        private readonly bool $configured = true,
        private readonly bool $successful = true,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function send(string $to, string $name, string $subject, string $html): bool
    {
        $this->to = $to;
        $this->name = $name;
        $this->subject = $subject;
        $this->html = $html;

        return $this->successful;
    }
}
