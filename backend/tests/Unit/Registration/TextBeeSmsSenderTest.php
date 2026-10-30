<?php

declare(strict_types=1);

namespace Tests\Unit\Registration;

use App\Application\Services\OtpDeliveryException;
use App\Application\Services\TextBeeSmsSender;
use PHPUnit\Framework\TestCase;

final class TextBeeSmsSenderTest extends TestCase
{
    /**
     * @var array{url: string, apiKey: string, payload: array<string, mixed>}|null
     */
    private ?array $request = null;

    protected function tearDown(): void
    {
        $this->restoreEnvironment('SMS_API_KEY');
        $this->restoreEnvironment('SMS_DEVICE_ID');
        $this->restoreEnvironment('SMS_SIM_SUBSCRIPTION_ID');
        $this->restoreEnvironment('SMS_COUNTRY_CODE');
        parent::tearDown();
    }

    public function testSendsE164RecipientAndConfiguredDeviceToTextBee(): void
    {
        $this->setEnvironment('SMS_API_KEY', 'test-api-key');
        $this->setEnvironment('SMS_DEVICE_ID', 'device-123');
        $this->setEnvironment('SMS_SIM_SUBSCRIPTION_ID', '3');
        $this->setEnvironment('SMS_COUNTRY_CODE', '63');

        $sender = new TextBeeSmsSender(function (string $url, string $apiKey, array $payload): array {
            $this->request = [
                'url' => $url,
                'apiKey' => $apiKey,
                'payload' => $payload,
            ];

            return [
                'status' => 200,
                'body' => '{"data":{"success":true,"smsBatchId":"batch-123"}}',
            ];
        });

        $sender->send('0917 000 0004', 'Your OTP code is: 123456');

        self::assertNotNull($this->request);
        self::assertSame('https://api.textbee.dev/api/v1/gateway/send-sms', $this->request['url']);
        self::assertSame('test-api-key', $this->request['apiKey']);
        self::assertSame([
            'message' => 'Your OTP code is: 123456',
            'recipients' => ['+639170000004'],
            'deviceId' => 'device-123',
            'simSubscriptionId' => 3,
        ], $this->request['payload']);
    }

    public function testInvalidSimSubscriptionIdFailsBeforeMakingRequest(): void
    {
        $this->setEnvironment('SMS_API_KEY', 'test-api-key');
        $this->setEnvironment('SMS_SIM_SUBSCRIPTION_ID', 'not-a-number');
        $called = false;
        $sender = new TextBeeSmsSender(static function (string $url, string $apiKey, array $payload) use (&$called): array {
            $called = true;

            return ['status' => 200, 'body' => '{"data":{"success":true}}'];
        });

        $this->expectException(OtpDeliveryException::class);
        $sender->send('09170000004', 'Your OTP code is: 123456');
        self::assertFalse($called);
    }

    public function testAcceptsImmediateTextBeeSuccessResponse(): void
    {
        $this->setEnvironment('SMS_API_KEY', 'test-api-key');
        $called = 0;

        $sender = new TextBeeSmsSender(static function (string $url, string $apiKey, array $payload) use (&$called): array {
            $called++;

            return [
                'status' => 200,
                'body' => '{"data":{"successCount":1,"failureCount":0}}',
            ];
        });

        $sender->send('+639170000004', 'Your OTP code is: 123456');

        self::assertSame(1, $called);
    }

    public function testMapsTextBeeFailureToSafeOtpDeliveryException(): void
    {
        $this->setEnvironment('SMS_API_KEY', 'test-api-key');

        $sender = new TextBeeSmsSender(static fn (string $url, string $apiKey, array $payload): array => [
            'status' => 401,
            'body' => '{"message":"invalid api key"}',
        ]);

        try {
            $sender->send('09170000004', 'Your OTP code is: 123456');
            self::fail('Expected TextBee delivery to fail.');
        } catch (OtpDeliveryException $exception) {
            self::assertSame('Unable to send the SMS verification code through TextBee.', $exception->getMessage());
            self::assertStringNotContainsString('test-api-key', $exception->getMessage());
            self::assertStringNotContainsString('09170000004', $exception->getMessage());
        }
    }

    public function testMissingApiKeyFailsBeforeMakingRequest(): void
    {
        $this->setEnvironment('SMS_API_KEY', '');
        $called = false;
        $sender = new TextBeeSmsSender(static function (string $url, string $apiKey, array $payload) use (&$called): array {
            $called = true;

            return ['status' => 200, 'body' => '{"data":{"success":true}}'];
        });

        $this->expectException(OtpDeliveryException::class);
        $this->expectExceptionMessage('Unable to send the SMS verification code through TextBee.');
        $sender->send('09170000004', 'Your OTP code is: 123456');
        self::assertFalse($called);
    }

    private function setEnvironment(string $key, string $value): void
    {
        putenv($key . '=' . $value);
    }

    private function restoreEnvironment(string $key): void
    {
        putenv($key);
    }
}
