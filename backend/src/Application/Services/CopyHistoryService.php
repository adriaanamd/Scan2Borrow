<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\CopyHistoryResult;
use App\Infrastructure\Persistence\AuditEventRepositoryInterface;
use InvalidArgumentException;

final readonly class CopyHistoryService
{
    public function __construct(private AuditEventRepositoryInterface $repository)
    {
    }

    public function findByBarcode(string $barcode): ?CopyHistoryResult
    {
        $barcode = trim($barcode);
        if ($barcode === '' || mb_strlen($barcode) > 50) {
            throw new InvalidArgumentException('A valid copy barcode is required.');
        }

        return $this->repository->findCopyHistory($barcode);
    }
}
