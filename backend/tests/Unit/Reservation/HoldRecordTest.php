<?php

declare(strict_types=1);

namespace Tests\Unit\Reservation;

use App\Domain\Reservation\HoldRecord;
use App\Domain\Reservation\HoldStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class HoldRecordTest extends TestCase
{
    public function testHydratesQueuePositionAndExpiryFromDatabaseRow(): void
    {
        $record = HoldRecord::fromRow([
            'id' => '12',
            'user_id' => '7',
            'title_id' => '4',
            'title' => 'Clean Code',
            'author' => 'Robert C. Martin',
            'status' => 'offered',
            'queue_position' => '1',
            'hold_expires_at' => '2026-08-31 10:00:00',
        ]);

        self::assertSame(12, $record->id());
        self::assertSame(7, $record->userId());
        self::assertSame(4, $record->titleId());
        self::assertSame('Clean Code', $record->title());
        self::assertSame('Robert C. Martin', $record->author());
        self::assertSame(HoldStatus::OFFERED, $record->status());
        self::assertSame(1, $record->queuePosition());
        self::assertEquals(new DateTimeImmutable('2026-08-31 10:00:00'), $record->holdExpiresAt());
    }

    public function testNullableQueueDataIsRepresentedWithoutInventingValues(): void
    {
        $record = HoldRecord::fromRow([
            'id' => 12,
            'user_id' => 7,
            'title_id' => 4,
            'title' => 'Clean Code',
            'status' => 'queued',
            'queue_position' => null,
            'hold_expires_at' => null,
        ]);

        self::assertNull($record->queuePosition());
        self::assertNull($record->holdExpiresAt());
        self::assertSame('', $record->author());
    }
}
