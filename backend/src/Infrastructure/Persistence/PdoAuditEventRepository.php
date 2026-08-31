<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\DTO\CopyHistoryResult;
use App\Domain\Audit\AuditEvent;
use PDO;

final class PdoAuditEventRepository implements AuditEventRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function record(AuditEvent $event): void
    {
        if (!$this->hasTable('audit_events')) {
            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO audit_events '
            . '(copy_id, actor_user_id, event_type, from_status, to_status, reason, transaction_id, borrowing_item_id, print_batch_id, legacy_source, metadata, occurred_at) '
            . 'VALUES (:copy_id, :actor_user_id, :event_type, :from_status, :to_status, :reason, :transaction_id, :borrowing_item_id, :print_batch_id, :legacy_source, :metadata, :occurred_at)'
        );
        $metadata = json_encode($event->metadata, JSON_THROW_ON_ERROR);
        $statement->execute([
            'copy_id' => $event->copyId,
            'actor_user_id' => $event->actorUserId,
            'event_type' => $event->type->value,
            'from_status' => $event->fromStatus,
            'to_status' => $event->toStatus,
            'reason' => $event->reason === null ? null : trim($event->reason),
            'transaction_id' => $event->transactionId,
            'borrowing_item_id' => $event->borrowingItemId,
            'print_batch_id' => $event->printBatchId,
            'legacy_source' => $event->legacySource,
            'metadata' => $metadata,
            'occurred_at' => $event->occurredAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findCopyHistory(string $barcode): ?CopyHistoryResult
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return null;
        }
        if (!$this->hasTable('book_copies')) {
            return null;
        }

        $copyStatement = $this->pdo->prepare(
            'SELECT c.id AS copy_id, c.barcode, c.accession_no, c.floor_no, c.section_name, c.shelf_no, c.row_no,
                    c.status, c.deleted_at, t.title, t.author
             FROM book_copies c JOIN book_titles t ON t.id = c.title_id
             WHERE c.barcode = :barcode LIMIT 1'
        );
        $copyStatement->execute(['barcode' => $barcode]);
        /** @var array<string, mixed>|false $copyRow */
        $copyRow = $copyStatement->fetch(PDO::FETCH_ASSOC);
        if (!$this->hasTable('audit_events')) {
            return $copyRow === false ? null : new CopyHistoryResult($this->copyPayload($copyRow), []);
        }

        $jsonBarcode = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? "json_extract(a.metadata, '$.barcode')"
            : "JSON_UNQUOTE(JSON_EXTRACT(a.metadata, '$.barcode'))";
        $eventWhere = $copyRow === false
            ? $jsonBarcode . ' = :barcode'
            : '(a.copy_id = :copy_id OR ' . $jsonBarcode . ' = :barcode)';
        $parameters = $copyRow === false
            ? ['barcode' => $barcode]
            : ['copy_id' => (int) $copyRow['copy_id'], 'barcode' => $barcode];
        $eventStatement = $this->pdo->prepare(
            'SELECT a.id, a.event_type, a.from_status, a.to_status, a.reason, a.transaction_id,
                    a.borrowing_item_id, a.print_batch_id, a.metadata, a.occurred_at,
                    u.id AS actor_id, u.firstname AS actor_firstname, u.lastname AS actor_lastname
             FROM audit_events a LEFT JOIN users u ON u.id = a.actor_user_id
             WHERE ' . $eventWhere . ' ORDER BY a.occurred_at DESC, a.id DESC'
        );
        $eventStatement->execute($parameters);
        /** @var list<array<string, mixed>> $eventRows */
        $eventRows = $eventStatement->fetchAll(PDO::FETCH_ASSOC);
        if ($copyRow === false && $eventRows === []) {
            return null;
        }

        $copy = $copyRow === false ? $this->copyFromEvent($eventRows) : $this->copyPayload($copyRow);
        $events = [];
        foreach ($eventRows as $row) {
            $metadata = $this->metadata($row['metadata'] ?? null);
            $actorName = trim($this->stringValue($row['actor_firstname'] ?? null) . ' ' . $this->stringValue($row['actor_lastname'] ?? null));
            $events[] = [
                'id' => $this->intValue($row['id'] ?? null),
                'type' => $this->stringValue($row['event_type'] ?? null),
                'label' => $this->eventLabel($this->stringValue($row['event_type'] ?? null)),
                'occurred_at' => $this->stringValue($row['occurred_at'] ?? null),
                'actor_user_id' => $this->intValue($row['actor_id'] ?? null) ?: null,
                'actor' => $actorName !== '' ? $actorName : 'Actor not recorded',
                'from_status' => $this->nullableValue($row['from_status'] ?? null),
                'to_status' => $this->nullableValue($row['to_status'] ?? null),
                'reason' => $this->nullableValue($row['reason'] ?? null),
                'transaction_id' => $this->intValue($row['transaction_id'] ?? null),
                'borrowing_item_id' => $this->intValue($row['borrowing_item_id'] ?? null),
                'print_batch_id' => $this->intValue($row['print_batch_id'] ?? null),
                'metadata' => $metadata,
            ];
        }

        return new CopyHistoryResult($copy, $events);
    }

    /** @param list<array<string, mixed>> $events @return array<string, mixed> */
    private function copyFromEvent(array $events): array
    {
        $metadata = $this->metadata($events[0]['metadata'] ?? null);

        return [
            'copy_id' => null,
            'barcode' => $this->stringValue($metadata['barcode'] ?? null),
            'accession_no' => $this->stringValue($metadata['accession_no'] ?? null),
            'title' => $this->stringValue($metadata['title'] ?? null),
            'author' => $this->stringValue($metadata['author'] ?? null),
            'status' => 'Deleted',
            'location' => $this->location($metadata),
            'deleted_at' => null,
        ];
    }

    /** @param array<string, mixed> $copy @return array<string, mixed> */
    private function copyPayload(array $copy): array
    {
        return [
            'copy_id' => $this->intValue($copy['copy_id'] ?? null),
            'barcode' => $this->stringValue($copy['barcode'] ?? null),
            'accession_no' => $this->stringValue($copy['accession_no'] ?? null),
            'title' => $this->stringValue($copy['title'] ?? null),
            'author' => $this->stringValue($copy['author'] ?? null),
            'status' => $this->stringValue($copy['status'] ?? null),
            'location' => $this->location($copy),
            'deleted_at' => $this->nullableValue($copy['deleted_at'] ?? null),
        ];
    }

    /** @param array<string, mixed> $values */
    private function location(array $values): string
    {
        $parts = [];
        foreach (['floor_no', 'section_name', 'shelf_no', 'row_no'] as $key) {
            $value = $this->stringValue($values[$key] ?? null);
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        return implode(' / ', $parts);
    }

    /** @return array<string, mixed> */
    private function metadata(mixed $value): array
    {
        if (!is_string($value) || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function eventLabel(string $type): string
    {
        foreach (\App\Domain\Audit\AuditEventType::cases() as $eventType) {
            if ($eventType->value === $type) {
                return $eventType->label();
            }
        }

        return 'Audit event';
    }

    private function intValue(mixed $value): int
    {
        return is_int($value) ? $value : (is_string($value) && is_numeric($value) ? (int) $value : 0);
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? (string) $value : '';
    }

    private function nullableValue(mixed $value): ?string
    {
        $string = $this->stringValue($value);

        return $string === '' ? null : $string;
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
}
