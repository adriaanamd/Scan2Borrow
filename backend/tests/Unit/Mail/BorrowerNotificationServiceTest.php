<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Application\Services\BorrowerNotificationService;
use App\Application\Services\EmailSenderInterface;
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
