<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use DateTimeImmutable;
use Closure;
use PDO;
use RuntimeException;

final class PdoReturnApprovalRepository implements ReturnApprovalRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?AuditEventRepositoryInterface $audit = null,
    ) {
    }

    public function pending(): array
    {
        $rows = [];
        if ($this->hasTable('borrowing_items') && $this->hasTable('borrowing_transactions') && $this->hasTable('book_copies') && $this->hasTable('book_titles')) {
            $statement = $this->pdo->query(
                "SELECT bi.id, bi.copy_id, c.title_id, bt.id AS transaction_id, bt.user_id, bt.due_date,
                        bi.return_requested_at, t.title, c.barcode
                 FROM borrowing_items bi
                 JOIN borrowing_transactions bt ON bt.id = bi.transaction_id
                 JOIN book_copies c ON c.id = bi.copy_id
                 JOIN book_titles t ON t.id = c.title_id
                 WHERE bi.return_status = 'pending' AND bi.return_date IS NULL
                   AND bt.approval_status = 'approved'
                 ORDER BY bi.return_requested_at ASC, bi.id ASC"
            );
            if ($statement !== false) {
                /** @var list<array<string, mixed>> $sourceRows */
                $sourceRows = $statement->fetchAll(PDO::FETCH_ASSOC);
                foreach ($sourceRows as $row) {
                    $rows[] = $this->normalizedRow($row);
                }
            }
        }

        if ($this->hasTable('borrowing')) {
            $statement = $this->pdo->query(
                "SELECT b.id, b.book_id AS copy_id, b.user_id, b.due_date, b.return_requested_at,
                        old_book.title, old_book.barcode
                 FROM borrowing b JOIN books old_book ON old_book.id = b.book_id
                 WHERE b.return_status = 'pending' AND b.return_date IS NULL
                   AND b.approval_status = 'approved'
                 ORDER BY b.return_requested_at ASC, b.id ASC"
            );
            if ($statement !== false) {
                /** @var list<array<string, mixed>> $sourceRows */
                $sourceRows = $statement->fetchAll(PDO::FETCH_ASSOC);
                foreach ($sourceRows as $row) {
                    $rows[] = $this->legacyRow($row);
                }
            }
        }

        if ($this->hasTable('visitor_borrowing') && $this->hasTable('books')) {
            $statement = $this->pdo->query(
                "SELECT vb.id, vb.visitor_id, vb.book_id, vb.due_date, vb.return_requested_at,
                        vb.return_verification_photo, b.title, b.barcode
                 FROM visitor_borrowing vb JOIN books b ON b.id = vb.book_id
                 WHERE vb.request_status = 'Return Verification Pending' AND vb.return_date IS NULL
                 ORDER BY vb.return_requested_at ASC, vb.id ASC"
            );
            if ($statement !== false) {
                /** @var list<array<string, mixed>> $sourceRows */
                $sourceRows = $statement->fetchAll(PDO::FETCH_ASSOC);
                foreach ($sourceRows as $row) {
                    $rows[] = $this->guestRow($row);
                }
            }
        }

        usort($rows, static function (array $left, array $right): int {
            $leftRequestedAt = $left['requested_at'] ?? '';
            $rightRequestedAt = $right['requested_at'] ?? '';

            return strcmp(
                is_string($leftRequestedAt) ? $leftRequestedAt : '',
                is_string($rightRequestedAt) ? $rightRequestedAt : '',
            );
        });

        return $rows;
    }

    public function findPending(string $type, int $id): ?array
    {
        foreach ($this->pending() as $row) {
            if ($row['type'] === $type && $this->integer($row['id'] ?? null) === $id) {
                return $row;
            }
        }

        return null;
    }

    public function decide(string $type, int $id, string $action, int $staffId, float $fine, string $note, ?Closure $afterApprove = null): bool
    {
        $this->pdo->beginTransaction();
        try {
            $changed = match ($type) {
                'borrower_item' => $this->decideNormalized($id, $action, $staffId, $fine, $note),
                'legacy_borrowing' => $this->decideLegacy($id, $action, $staffId, $fine, $note),
                'guest' => $this->decideGuest($id, $action, $staffId, $note),
                default => false,
            };
            if ($changed && $action === 'approve' && $afterApprove !== null) {
                $afterApprove();
            }
            $this->pdo->commit();

            return $changed;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException('Return decision could not be saved.', 0, $exception);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizedRow(array $row): array
    {
        return [
            'type' => 'borrower_item',
            'id' => $this->integer($row['id'] ?? null),
            'copy_id' => $this->integer($row['copy_id'] ?? null),
            'title_id' => $this->integer($row['title_id'] ?? null),
            'borrower' => 'Borrower #' . $this->integer($row['user_id'] ?? null),
            'title' => $this->string($row['title'] ?? null),
            'book_barcode' => $this->string($row['barcode'] ?? null),
            'due_date' => $this->string($row['due_date'] ?? null),
            'requested_at' => $this->string($row['return_requested_at'] ?? null),
            'evidence_photo' => null,
            'return_status' => 'pending',
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function legacyRow(array $row): array
    {
        return [
            'type' => 'legacy_borrowing',
            'id' => $this->integer($row['id'] ?? null),
            'copy_id' => $this->integer($row['copy_id'] ?? null),
            'title_id' => null,
            'borrower' => 'Borrower #' . $this->integer($row['user_id'] ?? null),
            'title' => $this->string($row['title'] ?? null),
            'book_barcode' => $this->string($row['barcode'] ?? null),
            'due_date' => $this->string($row['due_date'] ?? null),
            'requested_at' => $this->string($row['return_requested_at'] ?? null),
            'evidence_photo' => null,
            'return_status' => 'pending',
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function guestRow(array $row): array
    {
        return [
            'type' => 'guest',
            'id' => $this->integer($row['id'] ?? null),
            'copy_id' => null,
            'title_id' => null,
            'borrower' => 'Guest #' . $this->integer($row['visitor_id'] ?? null),
            'title' => $this->string($row['title'] ?? null),
            'book_barcode' => $this->string($row['barcode'] ?? null),
            'due_date' => $this->string($row['due_date'] ?? null),
            'requested_at' => $this->string($row['return_requested_at'] ?? null),
            'evidence_photo' => $this->string($row['return_verification_photo'] ?? null),
            'return_status' => 'pending',
        ];
    }

    private function decideNormalized(int $id, string $action, int $staffId, float $fine, string $note): bool
    {
        $context = $this->normalizedById($id, true);
        if ($context === null) {
            return false;
        }
        $approved = $action === 'approve';
        $statement = $this->pdo->prepare(
            'UPDATE borrowing_items SET return_date = ' . ($approved ? 'CURRENT_TIMESTAMP' : 'return_date') . ",
                status = " . ($approved ? "'Returned'" : 'status') . ",
                fine_amount = :fine, return_status = '" . ($approved ? "none" : "rejected") . "',
                return_decided_at = CURRENT_TIMESTAMP, return_decided_by = :staff_id, return_decision_note = :note
             WHERE id = :id AND return_status = 'pending' AND return_date IS NULL"
        );
        $statement->execute(['fine' => $approved ? $fine : 0.0, 'staff_id' => $staffId, 'note' => $this->nullable($note), 'id' => $id]);
        if ($statement->rowCount() !== 1) {
            return false;
        }
        if ($approved) {
            $this->pdo->prepare("UPDATE book_copies SET status = 'Available', due_date = NULL, return_date = CURRENT_DATE WHERE id = :id")
                ->execute(['id' => $this->integer($context['copy_id'] ?? null)]);
            $this->updateNormalizedTransaction($this->integer($context['transaction_id'] ?? null), $staffId, $note);
            $this->recordAudit($context, $staffId);
        }

        return true;
    }

    private function decideLegacy(int $id, string $action, int $staffId, float $fine, string $note): bool
    {
        $context = $this->legacyById($id, true);
        if ($context === null) {
            return false;
        }
        $approved = $action === 'approve';
        $statement = $this->pdo->prepare(
            'UPDATE borrowing SET return_date = ' . ($approved ? 'CURRENT_TIMESTAMP' : 'return_date') . ",
                status = " . ($approved ? "'Returned'" : 'status') . ",
                fine_amount = :fine, return_status = '" . ($approved ? "none" : "rejected") . "',
                return_decided_at = CURRENT_TIMESTAMP, return_decided_by = :staff_id, return_decision_note = :note
             WHERE id = :id AND return_status = 'pending' AND return_date IS NULL"
        );
        $statement->execute(['fine' => $approved ? $fine : 0.0, 'staff_id' => $staffId, 'note' => $this->nullable($note), 'id' => $id]);
        if ($statement->rowCount() !== 1) {
            return false;
        }
        if ($approved) {
            $context['audit_source'] = 'legacy_borrowing';
            $this->pdo->prepare("UPDATE books SET status = 'Available', due_date = NULL, return_date = CURRENT_DATE WHERE id = :id")
                ->execute(['id' => $this->integer($context['copy_id'] ?? null)]);
            $this->recordAudit($context, $staffId);
        }

        return true;
    }

    private function decideGuest(int $id, string $action, int $staffId, string $note): bool
    {
        $context = $this->guestById($id, true);
        if ($context === null) {
            return false;
        }
        $approved = $action === 'approve';
        $statement = $this->pdo->prepare(
            "UPDATE visitor_borrowing SET request_status = :status, return_date = " . ($approved ? 'CURRENT_DATE' : 'return_date') . ",
                return_decided_at = CURRENT_TIMESTAMP, return_decided_by = :staff_id, return_decision_note = :note
             WHERE id = :id AND request_status = 'Return Verification Pending' AND return_date IS NULL"
        );
        $statement->execute([
            'status' => $approved ? 'Returned' : 'Released',
            'staff_id' => $staffId,
            'note' => $this->nullable($note),
            'id' => $id,
        ]);
        if ($statement->rowCount() !== 1) {
            return false;
        }
        if ($approved) {
            $context['audit_source'] = 'guest_borrowing';
            $this->pdo->prepare("UPDATE books SET status = 'Available', due_date = NULL, return_date = CURRENT_DATE WHERE id = :id")
                ->execute(['id' => $this->integer($context['copy_id'] ?? null)]);
            $this->recordAudit($context, $staffId);
        }

        return true;
    }

    /** @return array<string, mixed>|null */
    private function normalizedById(int $id, bool $forUpdate): ?array
    {
        $query = "SELECT bi.id, bi.copy_id, bi.transaction_id, c.title_id, c.barcode, c.status AS copy_status,
                         bt.user_id, bt.due_date, t.title
                  FROM borrowing_items bi JOIN borrowing_transactions bt ON bt.id = bi.transaction_id
                  JOIN book_copies c ON c.id = bi.copy_id JOIN book_titles t ON t.id = c.title_id
                  WHERE bi.id = :id AND bi.return_status = 'pending' AND bi.return_date IS NULL
                    AND bt.approval_status = 'approved' LIMIT 1";
        $statement = $this->pdo->prepare($this->forUpdate($query, $forUpdate));
        $statement->execute(['id' => $id]);
        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    private function legacyById(int $id, bool $forUpdate): ?array
    {
        $statement = $this->pdo->prepare($this->forUpdate(
            "SELECT b.id, b.book_id AS copy_id, b.user_id, b.due_date, old_book.title, old_book.barcode,
                    old_book.status AS copy_status
             FROM borrowing b JOIN books old_book ON old_book.id = b.book_id
             WHERE b.id = :id AND b.return_status = 'pending' AND b.return_date IS NULL
               AND b.approval_status = 'approved' LIMIT 1",
            $forUpdate,
        ));
        $statement->execute(['id' => $id]);
        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    private function guestById(int $id, bool $forUpdate): ?array
    {
        $statement = $this->pdo->prepare($this->forUpdate(
            "SELECT vb.id, vb.book_id AS copy_id, vb.visitor_id, vb.due_date, b.title, b.barcode,
                    b.status AS copy_status
             FROM visitor_borrowing vb JOIN books b ON b.id = vb.book_id
             WHERE vb.id = :id AND vb.request_status = 'Return Verification Pending' AND vb.return_date IS NULL LIMIT 1",
            $forUpdate,
        ));
        $statement->execute(['id' => $id]);
        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private function updateNormalizedTransaction(int $transactionId, int $staffId, string $note): void
    {
            $statement = $this->pdo->prepare(
            "UPDATE borrowing_transactions SET return_date = CURRENT_TIMESTAMP, status = 'Returned',
                return_status = 'none', return_decided_at = CURRENT_TIMESTAMP,
                return_decided_by = :staff_id, return_decision_note = :note
             WHERE id = :id AND NOT EXISTS (
                SELECT 1 FROM borrowing_items WHERE transaction_id = :transaction_id AND return_date IS NULL
             )"
        );
        $statement->execute([
            'id' => $transactionId,
            'transaction_id' => $transactionId,
            'staff_id' => $staffId,
            'note' => $this->nullable($note),
        ]);
    }

    /** @param array<string, mixed> $context */
    private function recordAudit(array $context, int $staffId): void
    {
        if ($this->audit === null || !is_numeric($context['copy_id'] ?? null)) {
            return;
        }

        $auditSource = $this->string($context['audit_source'] ?? null);
        $copyId = (int) $context['copy_id'];
        $transactionId = is_numeric($context['transaction_id'] ?? null) ? (int) $context['transaction_id'] : null;
        $borrowingItemId = is_numeric($context['id'] ?? null) ? (int) $context['id'] : null;
        $legacySource = null;
        if ($auditSource !== '') {
            if (!$this->hasTable('book_copies')) {
                return;
            }
            $statement = $this->pdo->prepare('SELECT id FROM book_copies WHERE barcode = :barcode LIMIT 1');
            $statement->execute(['barcode' => $this->string($context['barcode'] ?? null)]);
            $resolvedCopyId = $statement->fetchColumn();
            if (!is_numeric($resolvedCopyId)) {
                return;
            }
            $copyId = (int) $resolvedCopyId;
            $transactionId = null;
            $borrowingItemId = null;
            $legacySource .= $auditSource . ':' . $this->integer($context['id'] ?? null) . ':return-approved';
        }

        $this->audit->record(new AuditEvent(
            $copyId,
            $staffId,
            AuditEventType::RETURNED,
            $this->string($context['copy_status'] ?? null) !== '' ? $this->string($context['copy_status'] ?? null) : 'Borrowed',
            'Available',
            'Return approved by staff.',
            $transactionId,
            $borrowingItemId,
            null,
            ['barcode' => $this->string($context['barcode'] ?? null), 'title' => $this->string($context['title'] ?? null)],
            new DateTimeImmutable(),
            $legacySource,
        ));
    }

    private function forUpdate(string $query, bool $enabled): string
    {
        return $enabled && $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? $query . ' FOR UPDATE' : $query;
    }

    private function hasTable(string $table): bool
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $statement = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :table LIMIT 1");
        } else {
            $statement = $this->pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1');
        }
        $statement->execute(['table' => $table]);

        return $statement->fetchColumn() !== false;
    }

    private function integer(mixed $value): int
    {
        return is_int($value) ? $value : (is_string($value) && is_numeric($value) ? (int) $value : 0);
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function nullable(string $value): ?string
    {
        return trim($value) === '' ? null : $value;
    }
}
