<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\DTO\CopyHistoryResult;
use App\Domain\Audit\AuditEvent;

interface AuditEventRepositoryInterface
{
    public function record(AuditEvent $event): void;

    public function findCopyHistory(string $barcode): ?CopyHistoryResult;
}
