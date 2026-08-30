<?php

declare(strict_types=1);

namespace Tests\Unit\Renewal;

use App\Domain\Renewal\RenewalRecord;
use PHPUnit\Framework\TestCase;

final class RenewalRecordTest extends TestCase
{
    public function testRenewalRecordNormalizesDatesAndSerializesItsAuditFields(): void
    {
        $record = RenewalRecord::fromRow([
            'id' => 12,
            'loan_id' => 88,
            'user_id' => 7,
            'title' => 'Clean Code',
            'author' => 'Robert C. Martin',
            'transaction_code' => 'S2B-001',
            'original_due_date' => '2026-08-30',
            'requested_due_date' => '2026-09-06',
            'status' => 'pending',
            'reason' => 'Project deadline',
            'decision_note' => null,
        ]);

        self::assertSame(88, $record->loanId());
        self::assertSame('2026-09-06', $record->requestedDueDate()->format('Y-m-d'));
        self::assertSame('Project deadline', $record->reason());
        self::assertSame('Awaiting librarian approval', $record->toArray()['status_label']);
    }
}
