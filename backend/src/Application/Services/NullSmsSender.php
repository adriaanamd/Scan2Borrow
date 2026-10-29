<?php

declare(strict_types=1);

namespace App\Application\Services;

final class NullSmsSender implements SmsSenderInterface
{
    public function send(string $phoneNumber, string $message): void
    {
        throw new OtpDeliveryException('Unable to send the SMS verification code because no SMS provider is configured.');
    }
}
