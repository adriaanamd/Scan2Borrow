<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Application\DTO\BorrowerContactNotification;
use App\Application\Services\BorrowerContactNotificationInterface;
use App\Application\Services\BorrowerContactNotificationService;
use App\Application\Services\EmailSenderInterface;
use App\Application\Services\SmsSenderInterface;
use App\Infrastructure\Persistence\ContactNotificationDeliveryRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class BorrowerContactNotificationServiceTest extends TestCase
{
    public function testDispatchesBothChannelsAndMarksThemSent(): void
    {
        $repository = new InMemoryContactDeliveryRepository([
            'name' => 'Juan Cruz',
            'email' => 'juan@example.com',
            'phone' => '09170000001',
        ]);
        $email = new SpyEmailSender(true);
        $sms = new SpySmsSender();
        $service = new BorrowerContactNotificationService($repository, $email, $sms);

        $service->dispatch(new BorrowerContactNotification(
            'user',
            7,
            'borrow_requested',
            'S2B-20261110-ABC123',
            [['title' => 'Clean Code', 'quantity' => 2]],
        ));

        self::assertCount(1, $email->messages);
        self::assertCount(1, $sms->messages);
        self::assertSame(['sent', 'sent'], $repository->statuses());
        self::assertSame('Scan2Borrow - Borrow Request Received', $email->messages[0]['subject']);
        self::assertStringContainsString('Clean Code (2 copies)', $email->messages[0]['html']);
        self::assertStringContainsString('S2B-20261110-ABC123', $sms->messages[0]['message']);
    }

    public function testMissingEmailIsSkippedWhileSmsStillSends(): void
    {
        $repository = new InMemoryContactDeliveryRepository([
            'name' => 'Guest Borrower',
            'email' => null,
            'phone' => '09170000009',
        ]);
        $sms = new SpySmsSender();
        $service = new BorrowerContactNotificationService($repository, new SpyEmailSender(true), $sms);

        $service->dispatch(new BorrowerContactNotification(
            'visitor',
            9,
            'borrow_approved',
            '9',
            [['title' => 'Clean Code', 'quantity' => 1]],
            '2026-11-17',
        ));

        self::assertCount(1, $sms->messages);
        self::assertSame(['skipped', 'sent'], $repository->statuses());
        self::assertStringContainsString('Due Nov 17, 2026', $sms->messages[0]['message']);
    }

    public function testProviderFailuresAreRecordedWithoutThrowing(): void
    {
        $repository = new InMemoryContactDeliveryRepository([
            'name' => 'Juan Cruz',
            'email' => 'juan@example.com',
            'phone' => '09170000001',
        ]);
        $email = new SpyEmailSender(false);
        $sms = new SpySmsSender(true);
        $service = new BorrowerContactNotificationService($repository, $email, $sms);

        $service->dispatch(new BorrowerContactNotification(
            'user',
            7,
            'borrow_approved',
            'REF-1',
            [['title' => 'Algorithms', 'quantity' => 1]],
        ));

        self::assertSame(['failed', 'failed'], $repository->statuses());
        self::assertCount(2, $repository->failures);
        self::assertSame('Could not send email.', $repository->failures[0]['error']);
        self::assertSame('SMS provider failed.', $repository->failures[1]['error']);
    }

    public function testUnconfiguredProvidersAreRecordedAsFailed(): void
    {
        $repository = new InMemoryContactDeliveryRepository([
            'name' => 'Juan Cruz',
            'email' => 'juan@example.com',
            'phone' => '09170000001',
        ]);
        $service = new BorrowerContactNotificationService($repository, new SpyEmailSender(true), null);

        $service->dispatch(new BorrowerContactNotification(
            'user',
            7,
            'borrow_requested',
            'REF-2',
            [['title' => 'Algorithms', 'quantity' => 1]],
        ));

        self::assertSame(['sent', 'failed'], $repository->statuses());
        self::assertSame('SMS is not configured.', $repository->rows[1]['last_error']);
    }

    public function testExistingDeliveryRowsAreNotSentAgain(): void
    {
        $repository = new InMemoryContactDeliveryRepository([
            'name' => 'Juan Cruz',
            'email' => 'juan@example.com',
            'phone' => '09170000001',
        ]);
        $email = new SpyEmailSender(true);
        $sms = new SpySmsSender();
        $service = new BorrowerContactNotificationService($repository, $email, $sms);
        $event = new BorrowerContactNotification(
            'user',
            7,
            'borrow_requested',
            'REF-3',
            [['title' => 'Algorithms', 'quantity' => 1]],
        );

        $service->dispatch($event);
        $service->dispatch($event);

        self::assertCount(1, $email->messages);
        self::assertCount(1, $sms->messages);
        self::assertCount(2, $repository->rows);
    }
}

final class InMemoryContactDeliveryRepository implements ContactNotificationDeliveryRepositoryInterface
{
    /** @var array{name: string, email: ?string, phone: ?string} */
    private array $recipient;

    /** @var list<array<string, mixed>> */
    public array $rows = [];

    /** @var list<array{id: int, error: string}> */
    public array $failures = [];

    /** @param array{name: string, email: ?string, phone: ?string} $recipient */
    public function __construct(array $recipient)
    {
        $this->recipient = $recipient;
    }

    public function findRecipient(string $recipientType, int $recipientId): array
    {
        return $this->recipient;
    }

    public function createDelivery(
        string $recipientType,
        int $recipientId,
        string $eventType,
        string $borrowingReference,
        string $channel,
        ?string $destination,
        ?string $subject,
        string $message,
        string $status,
        ?string $lastError = null,
    ): ?int {
        foreach ($this->rows as $row) {
            if ($row['recipient_type'] === $recipientType
                && $row['recipient_id'] === $recipientId
                && $row['event_type'] === $eventType
                && $row['borrowing_reference'] === $borrowingReference
                && $row['channel'] === $channel) {
                return null;
            }
        }
        $id = count($this->rows) + 1;
        $this->rows[] = [
            'id' => $id,
            'recipient_type' => $recipientType,
            'recipient_id' => $recipientId,
            'event_type' => $eventType,
            'borrowing_reference' => $borrowingReference,
            'channel' => $channel,
            'destination' => $destination,
            'subject' => $subject,
            'message' => $message,
            'status' => $status,
            'last_error' => $lastError,
        ];

        return $id;
    }

    public function markSent(int $deliveryId, DateTimeImmutable $sentAt): void
    {
        $this->rows[$deliveryId - 1]['status'] = 'sent';
    }

    public function markFailed(int $deliveryId, string $error): void
    {
        $this->rows[$deliveryId - 1]['status'] = 'failed';
        $this->rows[$deliveryId - 1]['last_error'] = $error;
        $this->failures[] = ['id' => $deliveryId, 'error' => $error];
    }

    /** @return list<string> */
    public function statuses(): array
    {
        return array_map(static fn (array $row): string => is_string($row['status'] ?? null) ? $row['status'] : '', $this->rows);
    }
}

final class SpyEmailSender implements EmailSenderInterface
{
    /** @var list<array{to: string, name: string, subject: string, html: string}> */
    public array $messages = [];

    public function __construct(private readonly bool $result)
    {
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function send(string $to, string $name, string $subject, string $html): bool
    {
        $this->messages[] = ['to' => $to, 'name' => $name, 'subject' => $subject, 'html' => $html];

        return $this->result;
    }
}

final class SpySmsSender implements SmsSenderInterface
{
    /** @var list<array{phone: string, message: string}> */
    public array $messages = [];

    public function __construct(private readonly bool $shouldFail = false)
    {
    }

    public function send(string $phoneNumber, string $message): void
    {
        if ($this->shouldFail) {
            throw new \RuntimeException('SMS provider failed.');
        }

        $this->messages[] = ['phone' => $phoneNumber, 'message' => $message];
    }
}
