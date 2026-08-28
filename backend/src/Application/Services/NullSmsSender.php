<?php

declare(strict_types=1);

namespace App\Application\Services;

final class NullSmsSender implements SmsSenderInterface
{
    public function send(string $phoneNumber, string $message): void
    {
        // Delivery is intentionally delegated to the configured SMS adapter.
        // The local XAMPP default remains side-effect free when no adapter is configured.
    }
}
