<?php

declare(strict_types=1);

namespace App\Domain\Audit;

enum AuditEventType: string
{
    case ACQUIRED = 'acquired';
    case STATUS_CHANGED = 'status_changed';
    case LOANED = 'loaned';
    case RETURNED = 'returned';
    case BARCODE_PRINTED = 'barcode_printed';
    case ARCHIVED = 'archived';
    case RESTORED = 'restored';
    case DELETED = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::ACQUIRED => 'Copy acquired',
            self::STATUS_CHANGED => 'Status changed',
            self::LOANED => 'Copy loaned',
            self::RETURNED => 'Copy returned',
            self::BARCODE_PRINTED => 'Barcode printed',
            self::ARCHIVED => 'Copy archived',
            self::RESTORED => 'Copy restored',
            self::DELETED => 'Copy deleted',
        };
    }
}
