<?php

declare(strict_types=1);

namespace App\Application\Services;

interface RegistrationOtpInterface
{
    /**
     * @param array<string, string> $payload
     */
    public function start(string $barcode, array $payload, string $phoneNumber): string;
}
