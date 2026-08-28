<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Auth\Role;
use App\Domain\Auth\UserAccount;
use PDO;

final class PdoUserRepository implements UserRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByBarcode(string $barcode): ?UserAccount
    {
        $statement = $this->pdo->prepare('SELECT id, barcode, role, status, password_hash, failed_attempts, locked_until FROM users WHERE barcode = :barcode LIMIT 1');
        $statement->execute(['barcode' => $barcode]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            return null;
        }

        $role = is_string($row['role'] ?? null) ? Role::tryFrom($row['role']) : null;
        if ($role === null) {
            return null;
        }

        return new UserAccount(
            $this->intValue($row['id'] ?? 0),
            $this->stringValue($row['barcode'] ?? ''),
            $role,
            $this->stringValue($row['status'] ?? 'inactive'),
            is_string($row['password_hash'] ?? null) ? $row['password_hash'] : null,
            $this->intValue($row['failed_attempts'] ?? 0),
            is_string($row['locked_until'] ?? null) && $row['locked_until'] > date('Y-m-d H:i:s'),
        );
    }

    public function isLocked(string $barcode): bool
    {
        $statement = $this->pdo->prepare('SELECT locked_until FROM users WHERE barcode = :barcode LIMIT 1');
        $statement->execute(['barcode' => $barcode]);
        $lockedUntil = $statement->fetchColumn();

        return is_string($lockedUntil) && $lockedUntil > date('Y-m-d H:i:s');
    }

    public function recordLoginFailure(?int $userId, string $barcode): void
    {
        if ($userId === null) {
            return;
        }

        $statement = $this->pdo->prepare('UPDATE users SET failed_attempts = failed_attempts + 1 WHERE id = :id AND barcode = :barcode');
        $statement->execute(['id' => $userId, 'barcode' => $barcode]);
    }

    public function lock(string $barcode, int $minutes): void
    {
        $statement = $this->pdo->prepare('UPDATE users SET locked_until = DATE_ADD(NOW(), INTERVAL :minutes MINUTE) WHERE barcode = :barcode');
        $statement->bindValue('minutes', $minutes, PDO::PARAM_INT);
        $statement->bindValue('barcode', $barcode);
        $statement->execute();
    }

    public function recordLoginSuccess(int $userId, string $barcode): void
    {
        $statement = $this->pdo->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = :id AND barcode = :barcode');
        $statement->execute(['id' => $userId, 'barcode' => $barcode]);
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
