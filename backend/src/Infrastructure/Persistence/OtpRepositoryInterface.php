<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Registration\OtpRecord;
use DateTimeImmutable;

interface OtpRepositoryInterface
{
    public function deleteExpired(DateTimeImmutable $now, string $barcode): void;

    public function create(OtpRecord $record): void;

    public function latestUnused(string $barcode): ?OtpRecord;

    public function markUsed(int $id): void;

    public function updateCode(int $id, string $code, DateTimeImmutable $expiresAt): void;
}
