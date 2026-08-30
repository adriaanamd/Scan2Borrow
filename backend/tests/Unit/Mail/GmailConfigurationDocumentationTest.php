<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use PHPUnit\Framework\TestCase;

final class GmailConfigurationDocumentationTest extends TestCase
{
    public function testExampleContainsSafeGmailMailerConfiguration(): void
    {
        $path = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . '.env.example';
        self::assertFileExists($path);

        $source = file_get_contents($path);
        self::assertIsString($source);
        foreach (['MAIL_HOST=', 'MAIL_PORT=587', 'MAIL_USERNAME=', 'MAIL_PASSWORD=', 'MAIL_FROM=', 'MAIL_FROM_NAME='] as $marker) {
            self::assertStringContainsString($marker, $source);
        }

        self::assertStringNotContainsString('jenmargvargas', $source);
        self::assertStringNotContainsString('uhpp', $source);
    }

    public function testReadmeExplainsGmailAppPasswordAndRegistrationVerification(): void
    {
        $source = file_get_contents(dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'README.md');
        self::assertIsString($source);
        self::assertStringContainsString('Gmail', $source);
        self::assertStringContainsString('App Password', $source);
        self::assertStringContainsString('verify-otp', $source);
    }
}
