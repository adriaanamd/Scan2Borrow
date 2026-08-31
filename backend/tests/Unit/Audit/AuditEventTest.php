<?php

declare(strict_types=1);

namespace Tests\Unit\Audit;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditEventType;
use App\Domain\Book\BookStatus;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AuditEventTest extends TestCase
{
    public function testLostAndDamagedAreValidStatuses(): void
    {
        self::assertSame('Lost', BookStatus::LOST->value);
        self::assertSame('Damaged', BookStatus::DAMAGED->value);
    }

    public function testStatusEventRejectsUnknownStatuses(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AuditEvent(
            1,
            7,
            AuditEventType::STATUS_CHANGED,
            'Unknown',
            'Lost',
            'Missing',
            null,
            null,
            null,
            [],
            new DateTimeImmutable('2026-08-31 14:32:00'),
        );
    }

    public function testLossAndDamageTransitionsRequireAReason(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AuditEvent(
            1,
            7,
            AuditEventType::STATUS_CHANGED,
            'Available',
            'Lost',
            null,
            null,
            null,
            null,
            [],
            new DateTimeImmutable('2026-08-31 14:32:00'),
        );
    }

    public function testEventTypesExposeEightStableValues(): void
    {
        self::assertCount(8, AuditEventType::cases());
        self::assertSame('barcode_printed', AuditEventType::BARCODE_PRINTED->value);
    }
}
