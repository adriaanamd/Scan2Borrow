<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Reservation\HoldRecord;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class PdoHoldRepository implements HoldRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findActiveForUserTitle(int $userId, int $titleId): ?HoldRecord
    {
        $statement = $this->pdo->prepare($this->selectSql(
            'r.user_id = :user_id AND r.title_id = :title_id AND r.status IN (\'queued\', \'offered\', \'claimed\')',
        ));
        $statement->execute(['user_id' => $userId, 'title_id' => $titleId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : HoldRecord::fromRow($row);
    }

    public function listForUser(int $userId): array
    {
        $statement = $this->pdo->prepare($this->selectSql('r.user_id = :user_id'));
        $statement->execute(['user_id' => $userId]);

        return $this->records($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function join(int $userId, int $titleId): HoldRecord
    {
        $this->pdo->beginTransaction();
        try {
            $sequenceStatement = $this->pdo->query($this->forUpdate(
                'SELECT COALESCE(MAX(queue_sequence), 0) + 1 FROM reservations',
            ));
            if ($sequenceStatement === false) {
                throw new RuntimeException('Unable to allocate a reservation queue position.');
            }
            $sequence = (int) $sequenceStatement->fetchColumn();
            $insert = $this->pdo->prepare(
                'INSERT INTO reservations (user_id, title_id, queue_sequence, status, created_at, updated_at) '
                . 'VALUES (:user_id, :title_id, :queue_sequence, \'queued\', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
            );
            $insert->execute([
                'user_id' => $userId,
                'title_id' => $titleId,
                'queue_sequence' => $sequence,
            ]);
            $id = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();

            $record = $this->find($id);
            if ($record === null) {
                throw new RuntimeException('Reservation was created but could not be loaded.');
            }

            return $record;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function cancel(int $holdId, int $userId): bool
    {
        $this->pdo->beginTransaction();
        try {
            $lookup = $this->pdo->prepare(
                "SELECT offered_copy_id FROM reservations WHERE id = :id AND user_id = :user_id AND status IN ('queued', 'offered', 'claimed')",
            );
            $lookup->execute(['id' => $holdId, 'user_id' => $userId]);
            $copyId = $lookup->fetchColumn();
            if ($copyId === false) {
                $this->pdo->rollBack();
                return false;
            }
            $statement = $this->pdo->prepare(
                "UPDATE reservations SET status = 'cancelled', cancelled_at = CURRENT_TIMESTAMP, offered_copy_id = NULL,
                 offered_at = NULL, hold_expires_at = NULL, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND user_id = :user_id AND status IN ('queued', 'offered', 'claimed')",
            );
            $statement->execute(['id' => $holdId, 'user_id' => $userId]);
            if ($statement->rowCount() !== 1) {
                $this->pdo->rollBack();
                return false;
            }
            if ($copyId !== null && $copyId !== '') {
                $release = $this->pdo->prepare("UPDATE book_copies SET status = 'Available', due_date = NULL WHERE id = :copy_id AND status = 'Reserved'");
                $release->execute(['copy_id' => (int) $copyId]);
            }
            $this->pdo->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function claim(int $holdId, int $userId): ?HoldRecord
    {
        $statement = $this->pdo->prepare(
            "UPDATE reservations SET status = 'claimed', claimed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND user_id = :user_id AND status = 'offered' AND hold_expires_at > CURRENT_TIMESTAMP",
        );
        $statement->execute(['id' => $holdId, 'user_id' => $userId]);

        return $statement->rowCount() === 1 ? $this->find($holdId) : null;
    }

    public function fulfil(int $holdId, int $staffId): bool
    {
        unset($staffId);
        $statement = $this->pdo->prepare(
            "UPDATE reservations SET status = 'fulfilled', fulfilled_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND status = 'claimed'",
        );
        $statement->execute(['id' => $holdId]);

        return $statement->rowCount() === 1;
    }

    public function nextEligibleQueued(int $titleId): ?HoldRecord
    {
        $statement = $this->pdo->prepare($this->selectSql(
            "r.title_id = :title_id AND r.status = 'queued' AND u.status = 'active'",
        ) . ' ORDER BY r.queue_sequence ASC LIMIT 1');
        $statement->execute(['title_id' => $titleId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : HoldRecord::fromRow($row);
    }

    public function offer(int $holdId, int $copyId, DateTimeImmutable $offeredAt, DateTimeImmutable $expiresAt): bool
    {
        $this->pdo->beginTransaction();
        try {
            $copy = $this->pdo->prepare(
                "UPDATE book_copies SET status = 'Reserved' WHERE id = :copy_id AND status = 'Available' AND deleted_at IS NULL",
            );
            $copy->execute(['copy_id' => $copyId]);
            if ($copy->rowCount() !== 1) {
                $this->pdo->rollBack();
                return false;
            }

            $statement = $this->pdo->prepare(
                "UPDATE reservations SET status = 'offered', offered_copy_id = :copy_id, offered_at = :offered_at,
                 hold_expires_at = :expires_at, updated_at = :offered_at
                 WHERE id = :id AND status = 'queued'",
            );
            $statement->execute([
                'id' => $holdId,
                'copy_id' => $copyId,
                'offered_at' => $offeredAt->format('Y-m-d H:i:s'),
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            ]);
            if ($statement->rowCount() !== 1) {
                $this->pdo->rollBack();
                return false;
            }

            $this->pdo->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function expire(int $holdId, DateTimeImmutable $expiredAt): bool
    {
        $this->pdo->beginTransaction();
        try {
            $copyLookup = $this->pdo->prepare(
                "SELECT offered_copy_id FROM reservations WHERE id = :id AND status = 'offered' AND hold_expires_at <= :expired_at",
            );
            $copyLookup->execute(['id' => $holdId, 'expired_at' => $expiredAt->format('Y-m-d H:i:s')]);
            $copyId = $copyLookup->fetchColumn();
            if ($copyId === false) {
                $this->pdo->rollBack();
                return false;
            }

            $statement = $this->pdo->prepare(
                "UPDATE reservations SET status = 'expired', expired_at = :expired_at, offered_copy_id = NULL,
                 offered_at = NULL, hold_expires_at = NULL, updated_at = :expired_at
                 WHERE id = :id AND status = 'offered' AND hold_expires_at <= :expired_at",
            );
            $statement->execute([
                'id' => $holdId,
                'expired_at' => $expiredAt->format('Y-m-d H:i:s'),
            ]);
            if ($statement->rowCount() !== 1) {
                $this->pdo->rollBack();
                return false;
            }

            if ($copyId !== null && $copyId !== '') {
                $release = $this->pdo->prepare("UPDATE book_copies SET status = 'Available', due_date = NULL WHERE id = :copy_id AND status = 'Reserved'");
                $release->execute(['copy_id' => (int) $copyId]);
            }
            $this->pdo->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function listStaff(string $status): array
    {
        $where = $status === '' ? '1 = 1' : 'r.status = :status';
        $statement = $this->pdo->prepare($this->selectSql($where) . ' ORDER BY r.queue_sequence ASC');
        $statement->execute($status === '' ? [] : ['status' => $status]);

        return $this->records($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function expireOffers(DateTimeImmutable $now): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, title_id FROM reservations WHERE status = 'offered' AND hold_expires_at <= :now ORDER BY title_id, queue_sequence",
        );
        $statement->execute(['now' => $now->format('Y-m-d H:i:s')]);
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $titles = [];
        foreach ($rows as $row) {
            $holdId = (int) ($row['id'] ?? 0);
            if ($holdId > 0 && $this->expire($holdId, $now)) {
                $titles[] = (int) ($row['title_id'] ?? 0);
            }
        }

        return array_values(array_unique(array_filter($titles, static fn (int $titleId): bool => $titleId > 0)));
    }

    public function find(int $holdId): ?HoldRecord
    {
        $statement = $this->pdo->prepare($this->selectSql('r.id = :id'));
        $statement->execute(['id' => $holdId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : HoldRecord::fromRow($row);
    }

    /** @param list<array<string, mixed>> $rows @return list<HoldRecord> */
    private function records(array $rows): array
    {
        return array_map(static fn (array $row): HoldRecord => HoldRecord::fromRow($row), $rows);
    }

    private function selectSql(string $where): string
    {
        return "SELECT r.id, r.user_id, r.title_id, t.title, t.author, u.firstname AS user_firstname,
                       u.lastname AS user_lastname, r.status, r.hold_expires_at,
                       CASE WHEN r.status IN ('queued', 'offered', 'claimed') THEN
                           (SELECT COUNT(*) + 1 FROM reservations earlier
                            WHERE earlier.title_id = r.title_id
                              AND earlier.status IN ('queued', 'offered', 'claimed')
                              AND earlier.queue_sequence < r.queue_sequence)
                       ELSE NULL END AS queue_position
                FROM reservations r
                JOIN book_titles t ON t.id = r.title_id
                JOIN users u ON u.id = r.user_id
                WHERE {$where}";
    }

    private function forUpdate(string $query): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? $query . ' FOR UPDATE' : $query;
    }
}
