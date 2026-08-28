<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Guest\VisitorAccount;
use PDO;

final class PdoGuestIdentityRepository implements GuestIdentityRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByGovernmentId(string $barcode): ?VisitorAccount
    {
        $statement = $this->pdo->prepare(
            'SELECT id, id_barcode, account_status, firstname, lastname, is_verified FROM visitors WHERE id_barcode = :barcode LIMIT 1'
        );
        $statement->execute(['barcode' => $barcode]);
        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false || $this->intValue($row['is_verified'] ?? null) !== 1) {
            return null;
        }

        return new VisitorAccount(
            $this->intValue($row['id'] ?? null),
            $this->stringValue($row['id_barcode'] ?? null),
            $this->stringValue($row['account_status'] ?? 'Active'),
            trim($this->stringValue($row['firstname'] ?? null) . ' ' . $this->stringValue($row['lastname'] ?? null)),
        );
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
