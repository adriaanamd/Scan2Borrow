<?php

declare(strict_types=1);

namespace App\Application\Services;

use Closure;
use JsonException;
use RuntimeException;
use Throwable;

final class TextBeeSmsSender implements SmsSenderInterface
{
    private const ENDPOINT = 'https://api.textbee.dev/api/v1/gateway/send-sms';

    /**
     * @var Closure(string, string, array<string, mixed>): array{status: int, body: string}
     */
    private readonly Closure $request;

    /**
     * @param null|Closure(string, string, array<string, mixed>): array{status: int, body: string} $request
     */
    public function __construct(?Closure $request = null)
    {
        $this->request = $request ?? $this->nativeRequest(...);
    }

    public function send(string $phoneNumber, string $message): void
    {
        $apiKey = $this->environment('SMS_API_KEY');
        if ($apiKey === '') {
            throw $this->deliveryException();
        }

        $payload = [
            'message' => $message,
            'recipients' => [$this->toE164($phoneNumber)],
        ];
        $deviceId = $this->environment('SMS_DEVICE_ID');
        if ($deviceId !== '') {
            $payload['deviceId'] = $deviceId;
        }

        try {
            $response = ($this->request)(self::ENDPOINT, $apiKey, $payload);
        } catch (Throwable $exception) {
            throw $this->deliveryException($exception);
        }

        if ($response['status'] < 200 || $response['status'] >= 300 || !$this->accepted($response['body'])) {
            throw $this->deliveryException();
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, body: string}
     */
    private function nativeRequest(string $url, string $apiKey, array $payload): array
    {
        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode the SMS request.', 0, $exception);
        }

        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Unable to initialize the SMS request.');
        }

        try {
            if (!curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'x-api-key: ' . $apiKey,
                ],
                CURLOPT_POSTFIELDS => $body,
            ])) {
                throw new RuntimeException('Unable to configure the SMS request.');
            }

            $responseBody = curl_exec($handle);
            if (!is_string($responseBody)) {
                throw new RuntimeException('The SMS request could not be completed.');
            }

            return [
                'status' => curl_getinfo($handle, CURLINFO_RESPONSE_CODE),
                'body' => $responseBody,
            ];
        } finally {
            curl_close($handle);
        }
    }

    private function accepted(string $body): bool
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        if (!is_array($decoded) || !is_array($decoded['data'] ?? null)) {
            return false;
        }

        $data = $decoded['data'];
        if (($data['success'] ?? null) === false) {
            return false;
        }
        if (($data['success'] ?? null) === true) {
            return true;
        }

        $batchId = $data['smsBatchId'] ?? null;
        if (is_string($batchId) && $batchId !== '') {
            return true;
        }

        $successCount = $data['successCount'] ?? null;

        return is_int($successCount) && $successCount > 0;
    }

    private function toE164(string $phoneNumber): string
    {
        $raw = trim($phoneNumber);
        $digits = preg_replace('/\D+/', '', $raw);
        $countryCode = preg_replace('/\D+/', '', $this->environment('SMS_COUNTRY_CODE', '63'));
        if (!is_string($digits) || $digits === '' || !is_string($countryCode) || $countryCode === '') {
            throw $this->deliveryException();
        }

        if (str_starts_with($raw, '+')) {
            $normalized = '+' . $digits;
        } elseif (str_starts_with($digits, '00')) {
            $normalized = '+' . substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $normalized = '+' . $countryCode . substr($digits, 1);
        } elseif (str_starts_with($digits, $countryCode)) {
            $normalized = '+' . $digits;
        } else {
            $normalized = '+' . $countryCode . $digits;
        }

        if (preg_match('/^\+[1-9][0-9]{7,14}$/', $normalized) !== 1) {
            throw $this->deliveryException();
        }

        return $normalized;
    }

    private function deliveryException(?Throwable $previous = null): OtpDeliveryException
    {
        return new OtpDeliveryException(
            'Unable to send the SMS verification code through TextBee.',
            0,
            $previous,
        );
    }

    private function environment(string $name, string $default = ''): string
    {
        $value = getenv($name);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
