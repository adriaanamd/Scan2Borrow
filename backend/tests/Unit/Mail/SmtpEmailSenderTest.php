<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Application\Services\SmtpEmailSender;
use Closure;
use PHPUnit\Framework\TestCase;

final class SmtpEmailSenderTest extends TestCase
{
    public function testConfiguredRequiresGmailCredentials(): void
    {
        $this->withEnvironment([
            'MAIL_USERNAME' => 'mailer@example.test',
            'MAIL_PASSWORD' => 'app-password',
        ], function (): void {
            self::assertTrue((new SmtpEmailSender())->isConfigured());
        });
    }

    public function testMissingCredentialsAreNotConfiguredAndDoNotSend(): void
    {
        $this->withEnvironment([
            'MAIL_USERNAME' => '',
            'MAIL_PASSWORD' => '',
        ], function (): void {
            $sender = new SmtpEmailSender();

            self::assertFalse($sender->isConfigured());
            self::assertFalse($sender->send('recipient@example.test', 'Recipient', 'Subject', '<p>Body</p>'));
        });
    }

    /**
     * @param array<string, string> $values
     */
    private function withEnvironment(array $values, Closure $callback): void
    {
        $previous = [];
        foreach ($values as $name => $value) {
            $previous[$name] = getenv($name);
            putenv($name . '=' . $value);
        }

        try {
            $callback();
        } finally {
            foreach ($previous as $name => $value) {
                if ($value === false) {
                    putenv($name);
                    continue;
                }

                putenv($name . '=' . $value);
            }
        }
    }
}
