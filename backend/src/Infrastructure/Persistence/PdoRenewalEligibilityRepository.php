<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Renewal\RenewalLoanSnapshot;
use DateTimeImmutable;
use PDO;

final readonly class PdoRenewalEligibilityRepository implements RenewalEligibilityRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function loanForRenewal(int $loanId, int $userId): ?RenewalLoanSnapshot
    {
        $statement = $this->pdo->prepare(
            "SELECT bi.id AS loan_id, bt.user_id, bc.title_id, t.title, bt.due_date
             FROM borrowing_items bi
             JOIN borrowing_transactions bt ON bt.id = bi.transaction_id
             JOIN book_copies bc ON bc.id = bi.copy_id
             JOIN book_titles t ON t.id = bc.title_id
             WHERE bi.id = :loan_id AND bt.user_id = :user_id AND bi.return_date IS NULL
               AND bt.approval_status = 'approved' AND bt.status = 'Borrowed' AND bi.status = 'Borrowed'
             LIMIT 1",
        );
        $statement->execute(['loan_id' => $loanId, 'user_id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false || !is_string($row['due_date'] ?? null)) return null;

        return new RenewalLoanSnapshot((int) $row['loan_id'], (int) $row['user_id'], (int) $row['title_id'], (string) $row['title'], new DateTimeImmutable($row['due_date']));
    }

    public function activeHoldCountForTitle(int $titleId): int
    {
        $statement = $this->pdo->prepare("SELECT COUNT(*) FROM reservations WHERE title_id = :title_id AND status IN ('queued', 'offered', 'claimed')");
        $statement->execute(['title_id' => $titleId]);
        return (int) $statement->fetchColumn();
    }

    public function accountInGoodStanding(int $userId): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM users u
             WHERE u.id = :user_id AND u.status = 'active'
               AND NOT EXISTS (
                   SELECT 1 FROM borrowing_transactions bt
                   WHERE bt.user_id = u.id AND bt.return_date IS NULL
                     AND (bt.status = 'Overdue' OR bt.fine_amount > 0)
               )",
        );
        $statement->execute(['user_id' => $userId]);
        return (int) $statement->fetchColumn() === 1;
    }
}
