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
        private readonly EmailSenderInterface $email,
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
        $this->deliver($payload, $phoneNumber, $code, false);

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
        $this->deliver($record->payload(), $record->phoneNumber(), $code, true);

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

    /**
     * @param array<string, string> $payload
     */
    private function deliver(array $payload, string $phoneNumber, string $code, bool $resend): void
    {
        $email = trim($payload['email'] ?? '');
        if ($email !== '') {
            if (!$this->email->isConfigured() || !$this->email->send(
                $email,
                $this->recipientName($payload),
                'Scan2Borrow Registration Verification Code',
                $this->messageHtml($payload, $code, $resend),
            )) {
                throw new OtpDeliveryException('Unable to send the verification email. Please check the mail configuration and try again.');
            }

            return;
        }

        $this->sms->send($phoneNumber, $this->message($code, $resend));
    }

    /**
     * @param array<string, string> $payload
     */
    private function recipientName(array $payload): string
    {
        $name = trim(($payload['firstname'] ?? '') . ' ' . ($payload['lastname'] ?? ''));

        return $name !== '' ? $name : 'Scan2Borrow User';
    }

    /**
     * @param array<string, string> $payload
     */
    private function messageHtml(array $payload, string $code, bool $resend): string
    {
        $name = $this->escape($this->recipientName($payload));
        $label = $resend ? 'Your new OTP code is:' : 'Your OTP code is:';

        return '<!doctype html><html lang="en"><body style="margin:0;background:#f3f6fa;color:#23384a;font-family:Arial,Helvetica,sans-serif;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;background:#f3f6fa;width:100%;">'
            . '<tr><td align="center" style="padding:32px 16px;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;background:#ffffff;border:1px solid #d4e0e8;max-width:600px;width:100%;">'
            . '<tr><td align="center" style="background:#102f52;border-bottom:5px solid #d4a72c;padding:28px 24px;">'
            . '<img src="cid:scan2borrow-school-seal" width="88" height="88" alt="Binalbagan Catholic College seal" style="display:block;border:0;border-radius:50%;height:88px;margin:0 auto 16px;width:88px;">'
            . '<div style="color:#ffffff;font-size:16px;font-weight:bold;letter-spacing:.04em;">BINALBAGAN CATHOLIC COLLEGE</div>'
            . '<div style="color:#dce8f1;font-size:12px;letter-spacing:.08em;margin-top:7px;text-transform:uppercase;">Scan2Borrow Library Services</div>'
            . '</td></tr>'
            . '<tr><td style="padding:34px 32px 30px;">'
            . '<div style="color:#075985;font-size:11px;font-weight:bold;letter-spacing:.12em;text-transform:uppercase;">Registration verification</div>'
            . '<h1 style="color:#102f52;font-size:25px;line-height:1.25;margin:9px 0 16px;">Verify your library account</h1>'
            . '<p style="font-size:15px;line-height:1.65;margin:0 0 14px;">Dear <strong>' . $name . '</strong>,</p>'
            . '<p style="font-size:15px;line-height:1.65;margin:0 0 22px;">Use the one-time password below to complete your Scan2Borrow registration.</p>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;margin:0 0 22px;width:100%;">'
            . '<tr><td align="center" style="background:#e8f4fa;border:1px solid #b8d9e7;padding:22px 16px;">'
            . '<div style="color:#63798b;font-size:11px;font-weight:bold;letter-spacing:.1em;margin-bottom:9px;text-transform:uppercase;">' . $label . '</div>'
            . '<div style="color:#102f52;font-size:34px;font-weight:bold;letter-spacing:.22em;line-height:1.1;margin-left:.22em;">' . $this->escape($code) . '</div>'
            . '</td></tr></table>'
            . '<p style="color:#63798b;font-size:13px;line-height:1.6;margin:0 0 18px;"><strong style="color:#23384a;">This code expires in 5 minutes.</strong> For your security, do not share it with anyone.</p>'
            . '<p style="font-size:14px;line-height:1.6;margin:0;">If you did not request this registration, you may safely ignore this email.</p>'
            . '</td></tr>'
            . '<tr><td style="background:#f8fafc;border-top:1px solid #d4e0e8;padding:20px 32px;">'
            . '<div style="color:#102f52;font-size:13px;font-weight:bold;">Library Management Office</div>'
            . '<div style="color:#63798b;font-size:12px;line-height:1.5;margin-top:5px;">Binalbagan Catholic College<br>Scan2Borrow Library Services</div>'
            . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
