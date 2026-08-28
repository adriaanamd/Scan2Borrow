<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Infrastructure\Persistence\RegistrationAccountRepositoryInterface;

final class RegistrationCompletionService
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly RegistrationAccountRepositoryInterface $accounts,
        private readonly PhotoStorageInterface $photos,
    ) {
    }

    public function complete(string $barcode, string $code): bool
    {
        $payload = $this->otp->verify(trim($barcode), trim($code));
        if ($payload === null || ($payload['role'] ?? '') === '') {
            return false;
        }

        $photoPath = $this->photos->store($payload['photo_data'] ?? '', $payload['barcode'] ?? $barcode);
        $this->accounts->createAccount($payload, $photoPath);

        return true;
    }
}
