<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\DTO\BarcodePrintBatch;
use PDO;

final class PdoBarcodePrintRepository implements BarcodePrintRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function createBatch(int $titleId, int $staffId, string $token): ?BarcodePrintBatch
    {
        $this->pdo->beginTransaction();
        try {
            $lockSuffix = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $copies = $this->pdo->prepare(
                'SELECT c.id AS copy_id, t.title, t.author, c.barcode, c.accession_no, c.floor_no, '
                . 'c.section_name, c.shelf_no, c.row_no '
                . 'FROM book_copies c JOIN book_titles t ON t.id = c.title_id '
                . 'WHERE c.title_id = :title_id AND c.deleted_at IS NULL AND c.printed_at IS NULL '
                . 'ORDER BY c.id' . $lockSuffix
            );
            $copies->execute(['title_id' => $titleId]);
            /** @var list<array<string, mixed>> $labels */
            $labels = $copies->fetchAll(PDO::FETCH_ASSOC);
            if ($labels === []) {
                $this->pdo->commit();

                return null;
            }

            $batchStatement = $this->pdo->prepare(
                'INSERT INTO barcode_print_batches (batch_token, title_id, printed_by) '
                . 'VALUES (:batch_token, :title_id, :printed_by)'
            );
            $batchStatement->execute([
                'batch_token' => $token,
                'title_id' => $titleId,
                'printed_by' => $staffId,
            ]);
            $batchId = (int) $this->pdo->lastInsertId();

            $itemStatement = $this->pdo->prepare(
                'INSERT INTO barcode_print_batch_items '
                . '(batch_id, copy_id, title, author, barcode, accession_no, floor_no, section_name, shelf_no, row_no) '
                . 'VALUES (:batch_id, :copy_id, :title, :author, :barcode, :accession_no, :floor_no, :section_name, :shelf_no, :row_no)'
            );
            $copyIds = [];
            foreach ($labels as $label) {
                $copyId = (int) ($label['copy_id'] ?? 0);
                $copyIds[] = $copyId;
                $itemStatement->execute([
                    'batch_id' => $batchId,
                    'copy_id' => $copyId,
                    'title' => (string) ($label['title'] ?? ''),
                    'author' => $this->nullableString($label['author'] ?? null),
                    'barcode' => (string) ($label['barcode'] ?? ''),
                    'accession_no' => $this->nullableString($label['accession_no'] ?? null),
                    'floor_no' => $this->nullableString($label['floor_no'] ?? null),
                    'section_name' => $this->nullableString($label['section_name'] ?? null),
                    'shelf_no' => $this->nullableString($label['shelf_no'] ?? null),
                    'row_no' => $this->nullableString($label['row_no'] ?? null),
                ]);
            }

            $placeholders = implode(',', array_fill(0, count($copyIds), '?'));
            $printed = $this->pdo->prepare(
                'UPDATE book_copies SET printed_at = CURRENT_TIMESTAMP '
                . 'WHERE id IN (' . $placeholders . ') AND printed_at IS NULL'
            );
            $printed->execute($copyIds);
            $this->pdo->commit();

            $createdAt = $this->createdAt($batchId);

            return new BarcodePrintBatch(
                $batchId,
                $token,
                $titleId,
                (string) ($labels[0]['title'] ?? ''),
                $createdAt,
                $this->withoutCopyIds($labels),
            );
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function findBatch(string $token): ?BarcodePrintBatch
    {
        $batchStatement = $this->pdo->prepare(
            'SELECT id, batch_token, title_id, created_at FROM barcode_print_batches WHERE batch_token = :token LIMIT 1'
        );
        $batchStatement->execute(['token' => $token]);
        /** @var array<string, mixed>|false $batch */
        $batch = $batchStatement->fetch(PDO::FETCH_ASSOC);
        if ($batch === false) {
            return null;
        }

        $itemStatement = $this->pdo->prepare(
            'SELECT title, author, barcode, accession_no, floor_no, section_name, shelf_no, row_no '
            . 'FROM barcode_print_batch_items WHERE batch_id = :batch_id ORDER BY id'
        );
        $itemStatement->execute(['batch_id' => (int) $batch['id']]);
        /** @var list<array<string, mixed>> $labels */
        $labels = $itemStatement->fetchAll(PDO::FETCH_ASSOC);

        return new BarcodePrintBatch(
            (int) $batch['id'],
            (string) $batch['batch_token'],
            (int) $batch['title_id'],
            (string) ($labels[0]['title'] ?? ''),
            (string) $batch['created_at'],
            $labels,
        );
    }

    public function history(int $titleId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT b.batch_token, b.created_at, COUNT(i.id) AS label_count '
            . 'FROM barcode_print_batches b JOIN barcode_print_batch_items i ON i.batch_id = b.id '
            . 'WHERE b.title_id = :title_id GROUP BY b.id ORDER BY b.created_at DESC, b.id DESC'
        );
        $statement->execute(['title_id' => $titleId]);
        /** @var list<array<string, mixed>> $history */
        $history = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($history as &$entry) {
            $entry['label_count'] = (int) ($entry['label_count'] ?? 0);
        }
        unset($entry);

        return $history;
    }

    private function createdAt(int $batchId): string
    {
        $statement = $this->pdo->prepare('SELECT created_at FROM barcode_print_batches WHERE id = :id');
        $statement->execute(['id' => $batchId]);

        return (string) $statement->fetchColumn();
    }

    /** @param list<array<string, mixed>> $labels @return list<array<string, mixed>> */
    private function withoutCopyIds(array $labels): array
    {
        foreach ($labels as &$label) {
            unset($label['copy_id']);
        }
        unset($label);

        return $labels;
    }

    private function nullableString(mixed $value): ?string
    {
        $string = is_string($value) ? trim($value) : '';

        return $string === '' ? null : $string;
    }
}
