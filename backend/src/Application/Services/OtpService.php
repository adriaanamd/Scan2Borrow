<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Registration\OtpRecord;
use App\Infrastructure\Persistence\OtpRepositoryInterface;
use DateTimeImmutable;

final class OtpService implements RegistrationOtpInterface
{
    public function __construct(
        private readonly OtpRepositoryInterface $repository,
        private readonly ClockInterface $clock,
        private readonly SmsSenderInterface $sms,
    ) {
    }

    /**
     * @param array<string, string> $payload
     */
    public function start(string $barcode, array $payload, string $phoneNumber): string
    {
        $now = $this->clock->now();
        $this->repository->deleteExpired($now, $barcode);
        $code = $this->newCode();
        $this->repository->create(OtpRecord::pending(
            0,
            $barcode,
            $code,
            $phoneNumber,
            $payload,
            $now->modify('+5 minutes'),
            $now,
        ));
        $this->sms->send($phoneNumber, $this->message($code, false));

        return $code;
    }

    /**
     * @return array<string, string>|null
     */
    public function verify(string $barcode, string $code): ?array
    {
        $record = $this->repository->latestUnused(trim($barcode));
        if ($record === null || $record->otpCode() !== trim($code) || $record->expiresAt() <= $this->clock->now()) {
            return null;
        }

        $this->repository->markUsed($record->id());

        return $record->payload();
    }

    public function canResend(string $barcode): bool
    {
        $record = $this->repository->latestUnused(trim($barcode));
        if ($record === null) {
            return true;
        }

        return $record->createdAt()->modify('+60 seconds') <= $this->clock->now();
    }

    public function resend(string $barcode): ?string
    {
        $barcode = trim($barcode);
        if (!$this->canResend($barcode)) {
            return null;
        }

        $record = $this->repository->latestUnused($barcode);
        if ($record === null) {
            return null;
        }

        $code = $this->newCode();
        $expiresAt = $this->clock->now()->modify('+5 minutes');
        $this->repository->updateCode($record->id(), $code, $expiresAt);
        $this->sms->send($record->phoneNumber(), $this->message($code, true));

        return $code;
    }

    private function newCode(): string
    {
        return (string) random_int(100000, 999999);
    }

    private function message(string $code, bool $resend): string
    {
        $prefix = $resend ? 'Your new OTP code is: ' : 'Your OTP code is: ';

        return "Scan2Borrow Registration\n\n" . $prefix . $code
            . "\n\nThis code will expire in 5 minutes.\n\nDo not share this code with anyone.";
    }
}
