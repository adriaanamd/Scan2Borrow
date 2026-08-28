<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Registration\OtpRecord;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class PdoOtpRepository implements OtpRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function deleteExpired(DateTimeImmutable $now, string $barcode): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM otp_codes WHERE barcode = :barcode AND expires_at < :now'
        );
        $statement->execute([
            'barcode' => $barcode,
            'now' => $now->format('Y-m-d H:i:s'),
        ]);
    }

    public function create(OtpRecord $record): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO otp_codes (barcode, otp_code, phone_number, user_data, is_verified, is_used, expires_at, created_at) '
            . 'VALUES (:barcode, :otp_code, :phone_number, :user_data, 0, 0, :expires_at, :created_at)'
        );
        $statement->execute([
            'barcode' => $record->barcode(),
            'otp_code' => $record->otpCode(),
            'phone_number' => $record->phoneNumber(),
            'user_data' => json_encode($record->payload(), JSON_THROW_ON_ERROR),
            'expires_at' => $record->expiresAt()->format('Y-m-d H:i:s'),
            'created_at' => $record->createdAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function latestUnused(string $barcode): ?OtpRecord
    {
        $statement = $this->pdo->prepare(
            'SELECT id, barcode, otp_code, phone_number, user_data, expires_at, created_at, is_used '
            . 'FROM otp_codes WHERE barcode = :barcode AND is_used = 0 ORDER BY id DESC LIMIT 1'
        );
        $statement->execute(['barcode' => $barcode]);
        $fetched = $statement->fetch(PDO::FETCH_ASSOC);
        /** @var array<string, mixed>|false $row */
        $row = $fetched;
        if ($row === false) {
            return null;
        }

        $payload = json_decode($this->stringValue($row['user_data'] ?? null), true);
        if (!is_array($payload)) {
            throw new RuntimeException('OTP payload is invalid JSON.');
        }

        /** @var array<string, string> $payload */
        return OtpRecord::pending(
            $this->intValue($row['id'] ?? null),
            $this->stringValue($row['barcode'] ?? null),
            $this->stringValue($row['otp_code'] ?? null),
            $this->stringValue($row['phone_number'] ?? null),
            $payload,
            new DateTimeImmutable($this->stringValue($row['expires_at'] ?? null)),
            new DateTimeImmutable($this->stringValue($row['created_at'] ?? null)),
        );
    }

    public function markUsed(int $id): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE otp_codes SET is_used = 1, is_verified = 1 WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
    }

    public function updateCode(int $id, string $code, DateTimeImmutable $expiresAt): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE otp_codes SET otp_code = :otp_code, expires_at = :expires_at WHERE id = :id'
        );
        $statement->execute([
            'otp_code' => $code,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'id' => $id,
        ]);
    }

    private function intValue(mixed $value): int
    {
        return is_int($value) ? $value : (is_string($value) && is_numeric($value) ? (int) $value : 0);
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
