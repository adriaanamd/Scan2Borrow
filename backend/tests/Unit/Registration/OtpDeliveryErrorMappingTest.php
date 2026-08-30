<?php

declare(strict_types=1);

namespace Tests\Unit\Registration;

use App\Application\DTO\RegistrationRequest;
use App\Application\Services\CsrfService;
use App\Application\Services\EmailSenderInterface;
use App\Application\Services\OtpService;
use App\Application\Services\PhotoStorageInterface;
use App\Application\Services\RegistrationCompletionService;
use App\Application\Services\RegistrationService;
use App\Application\Services\SmsSenderInterface;
use App\Application\Services\ClockInterface;
use App\Application\Validators\RegistrationValidator;
use App\Domain\Registration\OtpRecord;
use App\Http\Controllers\RegistrationController;
use App\Http\Requests\ServerRequest;
use App\Infrastructure\Persistence\OtpRepositoryInterface;
use App\Infrastructure\Persistence\RegistrationAccountRepositoryInterface;
use App\Infrastructure\Session\SessionStoreInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class OtpDeliveryErrorMappingTest extends TestCase
{
    private const CSRF = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $GLOBALS['_POST']);
        parent::tearDown();
    }

    public function testRegistrationBeginReturnsServiceUnavailableForEmailDeliveryFailure(): void
    {
        $controller = $this->controller();
        $response = $controller->begin($this->request([
            'csrf' => self::CSRF,
            'barcode' => '2024004',
            'firstname' => 'Lia',
            'lastname' => 'Santos',
            'role' => 'student',
            'course' => 'BSIT',
            'year_level' => '1',
            'email' => 'lia@example.test',
            'contact_no' => '09170000004',
        ]));

        self::assertSame(503, $response->statusCode());
        self::assertSame(
            ['ok' => false, 'errors' => ['Unable to send the verification email. Please check the mail configuration and try again.']],
            json_decode($response->toString(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testRegistrationResendReturnsServiceUnavailableForEmailDeliveryFailure(): void
    {
        $store = new ErrorMappingSessionStore(self::CSRF);
        $store->set('scan2borrow.registration_barcode', '2024004');
        $repository = new ErrorMappingOtpRepository();
        $clock = new ErrorMappingClock(new DateTimeImmutable('2026-08-28 10:02:00'));
        $repository->record = OtpRecord::pending(
            9,
            '2024004',
            '123456',
            '09170000004',
            ['email' => 'lia@example.test'],
            $clock->now()->modify('+5 minutes'),
            new DateTimeImmutable('2026-08-28 10:00:00'),
        );
        $otp = new OtpService($repository, $clock, new ErrorMappingSmsSender(), new ErrorMappingEmailSender(false));
        $controller = new RegistrationController(
            new RegistrationService(new RegistrationValidator(), new ErrorMappingAccountRepository(), $otp),
            new RegistrationCompletionService($otp, new ErrorMappingAccountRepository(), new ErrorMappingPhotoStorage()),
            $otp,
            new \App\Application\Services\SessionService($store),
            new CsrfService($store),
        );

        $response = $controller->resend($this->request(['csrf' => self::CSRF]));

        self::assertSame(503, $response->statusCode());
        self::assertStringContainsString('Unable to send the verification email.', $response->toString());
    }

    private function controller(): RegistrationController
    {
        $store = new ErrorMappingSessionStore(self::CSRF);
        $clock = new ErrorMappingClock(new DateTimeImmutable('2026-08-28 10:00:00'));
        $otp = new OtpService(
            new ErrorMappingOtpRepository(),
            $clock,
            new ErrorMappingSmsSender(),
            new ErrorMappingEmailSender(false),
        );
        $accounts = new ErrorMappingAccountRepository();

        return new RegistrationController(
            new RegistrationService(new RegistrationValidator(), $accounts, $otp),
            new RegistrationCompletionService($otp, $accounts, new ErrorMappingPhotoStorage()),
            $otp,
            new \App\Application\Services\SessionService($store),
            new CsrfService($store),
        );
    }

    /** @param array<string, string> $body */
    private function request(array $body): ServerRequest
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/auth/register';
        $GLOBALS['_POST'] = $body;

        return ServerRequest::fromGlobals();
    }
}

final class ErrorMappingSessionStore implements SessionStoreInterface
{
    /** @var array<string, mixed> */
    private array $values;

    public function __construct(string $csrf)
    {
        $this->values = ['scan2borrow.csrf' => $csrf];
    }

    public function start(): void {}

    public function regenerate(): void {}

    public function id(): string
    {
        return 'error-mapping-session';
    }

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->values[$key]);
    }
}

final class ErrorMappingAccountRepository implements RegistrationAccountRepositoryInterface
{
    public function existsByBarcode(string $barcode): bool
    {
        return false;
    }

    /** @param array<string, string> $payload */
    public function createAccount(array $payload, ?string $photoPath): int
    {
        return 1;
    }
}

final class ErrorMappingPhotoStorage implements PhotoStorageInterface
{
    public function store(string $data, string $filenameSeed): ?string
    {
        return null;
    }
}

final class ErrorMappingOtpRepository implements OtpRepositoryInterface
{
    public ?OtpRecord $record = null;

    public function deleteExpired(DateTimeImmutable $now, string $barcode): void {}

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

    public function markUsed(int $id): void {}

    public function updateCode(int $id, string $code, DateTimeImmutable $expiresAt): void
    {
        if ($this->record !== null && $this->record->id() === $id) {
            $this->record = $this->record->withCode($code, $expiresAt);
        }
    }
}

final class ErrorMappingClock implements ClockInterface
{
    public function __construct(private readonly DateTimeImmutable $current) {}

    public function now(): DateTimeImmutable
    {
        return $this->current;
    }
}

final class ErrorMappingSmsSender implements SmsSenderInterface
{
    public function send(string $phoneNumber, string $message): void {}
}

final class ErrorMappingEmailSender implements EmailSenderInterface
{
    public function __construct(private readonly bool $configured) {}

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function send(string $to, string $name, string $subject, string $html): bool
    {
        return false;
    }
}
