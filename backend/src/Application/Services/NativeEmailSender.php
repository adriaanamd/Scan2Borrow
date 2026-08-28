<?php

declare(strict_types=1);

namespace App\Application\Services;

final class NativeEmailSender implements EmailSenderInterface
{
    public function isConfigured(): bool
    {
        return $this->environment('MAIL_USERNAME') !== '' && $this->environment('MAIL_PASSWORD') !== '';
    }

    public function send(string $to, string $name, string $subject, string $html): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . ($this->environment('MAIL_FROM_NAME', 'Scan2Borrow Library')) . ' <' . $this->environment('MAIL_FROM', 'noreply@localhost') . '>',
        ];

        return @mail($to, $subject, $html, implode("\r\n", $headers));
    }

    private function environment(string $name, string $default = ''): string
    {
        $value = getenv($name);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
