<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Application\Services\BorrowerNotificationService;
use App\Application\Services\EmailSenderInterface;
use App\Application\Services\OtpDeliveryException;
use App\Application\Services\SmsSenderInterface;
use App\Infrastructure\Persistence\StaffRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class BorrowerNotificationServiceTest extends TestCase
{
    public function testBorrowerNotificationUsesTheSchoolBrandedEmailShell(): void
    {
        $staff = $this->createMock(StaffRepositoryInterface::class);
        $staff->expects(self::once())->method('borrowerDetails')->with(7)->willReturn([
            'borrower' => ['name' => 'Grace Hopper', 'email' => 'grace@example.test'],
            'summary' => ['active' => 1],
            'history' => [
                ['title' => 'Clean Code', 'borrow_date' => '2026-08-28', 'due_date' => '2026-09-04', 'status' => 'Borrowed', 'return_date' => null],
            ],
        ]);
        $email = new BorrowerNotificationEmailSender();

        $result = (new BorrowerNotificationService($staff, $email))->send(7, 'email');

        self::assertTrue($result['ok']);
        self::assertStringContainsString('BINALBAGAN CATHOLIC COLLEGE', $email->html);
        self::assertStringContainsString('Scan2Borrow Library Services', $email->html);
        self::assertStringContainsString('cid:scan2borrow-school-seal', $email->html);
        self::assertStringContainsString('Clean Code', $email->html);
    }

    public function testBorrowerNotificationSendsAnSmsWithTheBorrowerLoanSummary(): void
    {
        $staff = $this->createMock(StaffRepositoryInterface::class);
        $staff->expects(self::once())->method('borrowerDetails')->with(7)->willReturn([
            'borrower' => ['name' => 'Grace Hopper', 'contact_no' => '09171234567'],
            'summary' => ['active' => 1],
            'history' => [
                ['title' => 'Clean Code', 'borrow_date' => '2026-08-28', 'due_date' => '2026-09-04', 'status' => 'Borrowed', 'return_date' => null],
            ],
        ]);
        $sms = new BorrowerNotificationSmsSender();

        $result = (new BorrowerNotificationService($staff, new BorrowerNotificationEmailSender(), $sms))->send(7, 'sms');

        self::assertTrue($result['ok']);
        self::assertSame('09171234567', $sms->phoneNumber);
        self::assertStringContainsString('Grace Hopper', $sms->message);
        self::assertStringContainsString('Clean Code', $sms->message);
        self::assertStringContainsString('2026-09-04', $sms->message);
    }

    public function testBorrowerNotificationRejectsSmsWithoutAContactNumber(): void
    {
        $staff = $this->createMock(StaffRepositoryInterface::class);
        $staff->method('borrowerDetails')->willReturn([
            'borrower' => ['name' => 'Grace Hopper', 'contact_no' => ''],
            'summary' => ['active' => 0],
            'history' => [],
        ]);
        $sms = $this->createMock(SmsSenderInterface::class);
        $sms->expects(self::never())->method('send');

        $result = (new BorrowerNotificationService($staff, new BorrowerNotificationEmailSender(), $sms))->send(7, 'sms');

        self::assertFalse($result['ok']);
        self::assertSame('This borrower has no contact number on file.', $result['message']);
    }

    public function testBorrowerNotificationConvertsSmsProviderFailuresToASafeResult(): void
    {
        $staff = $this->createMock(StaffRepositoryInterface::class);
        $staff->method('borrowerDetails')->willReturn([
            'borrower' => ['name' => 'Grace Hopper', 'contact_no' => '09171234567'],
            'summary' => ['active' => 0],
            'history' => [],
        ]);
        $sms = new BorrowerNotificationSmsSender(new OtpDeliveryException('provider secret should not leak'));

        $result = (new BorrowerNotificationService($staff, new BorrowerNotificationEmailSender(), $sms))->send(7, 'sms');

        self::assertFalse($result['ok']);
        self::assertSame('Could not send SMS notification. Please try again.', $result['message']);
    }
}

final class BorrowerNotificationEmailSender implements EmailSenderInterface
{
    public string $html = '';

    public function isConfigured(): bool
    {
        return true;
    }

    public function send(string $to, string $name, string $subject, string $html): bool
    {
        $this->html = $html;

        return true;
    }
}

final class BorrowerNotificationSmsSender implements SmsSenderInterface
{
    public string $phoneNumber = '';
    public string $message = '';

    public function __construct(private ?OtpDeliveryException $failure = null)
    {
    }

    public function send(string $phoneNumber, string $message): void
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $this->phoneNumber = $phoneNumber;
        $this->message = $message;
    }
}
