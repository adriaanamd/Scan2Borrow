<?php

declare(strict_types=1);

namespace App\Infrastructure\Session;

use App\Application\Services\SessionService;
use App\Domain\Guest\VisitorAccount;
use PDO;

final class PdoGuestIdentityProvider implements GuestIdentityProviderInterface
{
    public function __construct(
        private readonly SessionService $sessions,
        private readonly PDO $pdo,
    ) {
    }

    public function current(): ?VisitorAccount
    {
        $visitorId = $this->sessions->currentGuestId();
        if ($visitorId === null) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT id, id_barcode, account_status, firstname, lastname FROM visitors WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $visitorId]);
        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
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
