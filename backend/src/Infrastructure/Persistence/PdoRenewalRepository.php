<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Renewal\RenewalRecord;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final readonly class PdoRenewalRepository implements RenewalRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function find(int $renewalId): ?RenewalRecord
    {
        $statement = $this->pdo->prepare($this->selectSql('rr.id = :renewal_id'));
        $statement->execute(['renewal_id' => $renewalId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : RenewalRecord::fromRow($row);
    }

    public function listForUser(int $userId): array
    {
        $statement = $this->pdo->prepare($this->selectSql('rr.user_id = :user_id') . ' ORDER BY rr.requested_at DESC, rr.id DESC');
        $statement->execute(['user_id' => $userId]);

        return $this->records($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function listPending(): array
    {
        $statement = $this->pdo->query($this->selectSql("rr.status = 'pending'") . ' ORDER BY rr.requested_at ASC, rr.id ASC');
        if ($statement === false) {
            return [];
        }

        return $this->records($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function hasPendingForLoan(int $loanId, int $userId): bool
    {
        $statement = $this->pdo->prepare("SELECT 1 FROM renewal_requests WHERE loan_id = :loan_id AND user_id = :user_id AND status = 'pending' LIMIT 1");
        $statement->execute(['loan_id' => $loanId, 'user_id' => $userId]);

        return $statement->fetchColumn() !== false;
    }

    public function hasApprovedForLoan(int $loanId): bool
    {
        $statement = $this->pdo->prepare("SELECT 1 FROM renewal_requests WHERE loan_id = :loan_id AND status = 'approved' LIMIT 1");
        $statement->execute(['loan_id' => $loanId]);

        return $statement->fetchColumn() !== false;
    }

    public function create(
        int $loanId,
        int $userId,
        DateTimeImmutable $originalDueDate,
        DateTimeImmutable $requestedDueDate,
        string $reason,
    ): RenewalRecord {
        $statement = $this->pdo->prepare(
            "INSERT INTO renewal_requests (loan_id, user_id, original_due_date, requested_due_date, status, reason, requested_at, created_at, updated_at)
             VALUES (:loan_id, :user_id, :original_due_date, :requested_due_date, 'pending', :reason, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
        );
        $statement->execute([
            'loan_id' => $loanId,
            'user_id' => $userId,
            'original_due_date' => $originalDueDate->format('Y-m-d'),
            'requested_due_date' => $requestedDueDate->format('Y-m-d'),
            'reason' => $reason === '' ? null : $reason,
        ]);
        $record = $this->find((int) $this->pdo->lastInsertId());
        if ($record === null) {
            throw new RuntimeException('Renewal request was created but could not be loaded.');
        }

        return $record;
    }

    public function approve(int $renewalId, int $staffId, string $note, DateTimeImmutable $decidedAt): ?RenewalRecord
    {
        return $this->decide($renewalId, $staffId, 'approved', $note, $decidedAt, true);
    }

    public function reject(int $renewalId, int $staffId, string $note, DateTimeImmutable $decidedAt): ?RenewalRecord
    {
        return $this->decide($renewalId, $staffId, 'rejected', $note, $decidedAt, false);
    }

    public function cancel(int $renewalId, int $userId, DateTimeImmutable $cancelledAt): bool
    {
        $statement = $this->pdo->prepare(
            "UPDATE renewal_requests SET status = 'cancelled', decided_at = :cancelled_at, updated_at = :cancelled_at
             WHERE id = :renewal_id AND user_id = :user_id AND status = 'pending'",
        );
        $statement->execute([
            'renewal_id' => $renewalId,
            'user_id' => $userId,
            'cancelled_at' => $cancelledAt->format('Y-m-d H:i:s'),
        ]);

        return $statement->rowCount() === 1;
    }

    private function decide(int $renewalId, int $staffId, string $status, string $note, DateTimeImmutable $decidedAt, bool $applyDueDate): ?RenewalRecord
    {
        $this->pdo->beginTransaction();
        try {
            $lookup = $this->pdo->prepare(
                "SELECT loan_id, requested_due_date FROM renewal_requests WHERE id = :renewal_id AND status = 'pending'",
            );
            $lookup->execute(['renewal_id' => $renewalId]);
            $row = $lookup->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                $this->pdo->rollBack();
                return null;
            }

            $loanId = (int) ($row['loan_id'] ?? 0);
            if ($applyDueDate && $this->hasApprovedForLoan($loanId)) {
                $this->pdo->rollBack();
                return null;
            }
            if ($applyDueDate) {
                $dueDate = is_string($row['requested_due_date'] ?? null) ? $row['requested_due_date'] : '';
                if ($this->driver() === 'mysql') {
                    $updateLoan = $this->pdo->prepare(
                        'UPDATE borrowing_transactions bt JOIN borrowing_items bi ON bi.transaction_id = bt.id SET bt.due_date = :due_date WHERE bi.id = :loan_id',
                    );
                } else {
                    $updateLoan = $this->pdo->prepare(
                        'UPDATE borrowing_transactions SET due_date = :due_date WHERE id = (SELECT transaction_id FROM borrowing_items WHERE id = :loan_id)',
                    );
                }
                $updateLoan->execute(['due_date' => $dueDate, 'loan_id' => $loanId]);
                if ($updateLoan->rowCount() !== 1) {
                    $this->pdo->rollBack();
                    return null;
                }
            }

            $update = $this->pdo->prepare(
                'UPDATE renewal_requests SET status = :status, decision_note = :note, decided_at = :decided_at, approved_by = :approved_by, updated_at = :decided_at WHERE id = :renewal_id AND status = \'pending\'',
            );
            $update->execute([
                'status' => $status,
                'note' => $note === '' ? null : $note,
                'decided_at' => $decidedAt->format('Y-m-d H:i:s'),
                'approved_by' => $staffId,
                'renewal_id' => $renewalId,
            ]);
            if ($update->rowCount() !== 1) {
                $this->pdo->rollBack();
                return null;
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }

        return $this->find($renewalId);
    }

    private function selectSql(string $where): string
    {
        return "SELECT rr.id, rr.loan_id, rr.user_id, rr.original_due_date, rr.requested_due_date, rr.status,
                       rr.reason, rr.decision_note, bt.transaction_code, t.title, t.author
                FROM renewal_requests rr
                JOIN borrowing_items bi ON bi.id = rr.loan_id
                JOIN borrowing_transactions bt ON bt.id = bi.transaction_id
                JOIN book_copies bc ON bc.id = bi.copy_id
                JOIN book_titles t ON t.id = bc.title_id
                WHERE {$where}";
    }

    /** @param list<array<string, mixed>> $rows @return list<RenewalRecord> */
    private function records(array $rows): array
    {
        return array_map(static fn (array $row): RenewalRecord => RenewalRecord::fromRow($row), $rows);
    }

    private function driver(): string
    {
        return (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }
}
