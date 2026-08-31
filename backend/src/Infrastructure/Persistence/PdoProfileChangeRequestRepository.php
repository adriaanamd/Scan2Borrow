<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;
use RuntimeException;

final class PdoProfileChangeRequestRepository implements ProfileChangeRequestRepositoryInterface
{
    /** @var array<string, string> */
    private const PROFILE_COLUMNS = [
        'firstname' => 'firstname',
        'middlename' => 'middlename',
        'lastname' => 'lastname',
        'email' => 'email',
        'contact_no' => 'contact_no',
        'course' => 'course',
        'year_level' => 'year_level',
        'department' => 'department',
        'position' => 'position',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function profile(int $userId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, barcode, firstname, middlename, lastname, email, contact_no, course, year_level, department, position, photo, role, status '
            . 'FROM users WHERE id = :id LIMIT 1',
        );
        $statement->execute(['id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        $profile = [
            'id' => $this->intValue($row['id'] ?? 0),
            'barcode' => $this->stringValue($row['barcode'] ?? null),
            'photo' => $this->nullableString($row['photo'] ?? null),
            'role' => $this->stringValue($row['role'] ?? null),
            'status' => $this->stringValue($row['status'] ?? null),
        ];
        foreach (array_keys(self::PROFILE_COLUMNS) as $field) {
            $profile[$field] = $this->stringValue($row[$field] ?? null);
        }

        return $profile;
    }

    /** @return array<string, mixed>|null */
    public function pendingForUser(int $userId): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, user_id, status, original_values, requested_values, original_photo, requested_photo,
                    review_note, requested_at, reviewed_at, reviewed_by
             FROM profile_change_requests
             WHERE user_id = :user_id AND status = 'pending'
             ORDER BY id DESC LIMIT 1",
        );
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->requestRow($row) : null;
    }

    /** @return array<string, mixed>|null */
    public function decide(int $requestId, int $reviewerId, string $decision, string $reviewNote): ?array
    {
        throw new RuntimeException('Profile change decisions are not available yet.');
    }

    /** @return list<array<string, mixed>> */
    public function pendingRequests(): array
    {
        throw new RuntimeException('Profile change request listing is not available yet.');
    }

    /** @param array<string, string> $originalValues @param array<string, string> $requestedValues */
    public function create(int $userId, array $originalValues, array $requestedValues, ?string $originalPhoto, ?string $requestedPhoto): int
    {
        $this->pdo->beginTransaction();
        try {
            $pending = $this->pdo->prepare(
                "SELECT id FROM profile_change_requests WHERE user_id = :user_id AND status = 'pending' LIMIT 1",
            );
            $pending->execute(['user_id' => $userId]);
            if ($pending->fetchColumn() !== false) {
                $this->pdo->rollBack();
                throw new RuntimeException('A profile change request is already pending.');
            }

            $statement = $this->pdo->prepare(
                'INSERT INTO profile_change_requests (user_id, status, original_values, requested_values, original_photo, requested_photo) '
                . "VALUES (:user_id, 'pending', :original_values, :requested_values, :original_photo, :requested_photo)",
            );
            $statement->execute([
                'user_id' => $userId,
                'original_values' => json_encode($originalValues, JSON_THROW_ON_ERROR),
                'requested_values' => json_encode($requestedValues, JSON_THROW_ON_ERROR),
                'original_photo' => $originalPhoto,
                'requested_photo' => $requestedPhoto,
            ]);
            $id = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();

            return $id;
        } catch (RuntimeException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException('Profile change request could not be saved.', 0, $exception);
        }
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function requestRow(array $row): array
    {
        return [
            'id' => $this->intValue($row['id'] ?? 0),
            'user_id' => $this->intValue($row['user_id'] ?? 0),
            'status' => $this->stringValue($row['status'] ?? null),
            'original_values' => $this->decodeMap($row['original_values'] ?? null),
            'requested_values' => $this->decodeMap($row['requested_values'] ?? null),
            'original_photo' => $this->nullableString($row['original_photo'] ?? null),
            'requested_photo' => $this->nullableString($row['requested_photo'] ?? null),
            'review_note' => $this->nullableString($row['review_note'] ?? null),
            'requested_at' => $this->nullableString($row['requested_at'] ?? null),
            'reviewed_at' => $this->nullableString($row['reviewed_at'] ?? null),
            'reviewed_by' => $this->nullableInt($row['reviewed_by'] ?? null),
        ];
    }

    /** @return array<string, string> */
    private function decodeMap(mixed $value): array
    {
        if (!is_string($value) || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return [];
        }

        $map = [];
        foreach ($decoded as $key => $item) {
            if (is_string($key) && is_string($item)) {
                $map[$key] = $item;
            }
        }

        return $map;
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
