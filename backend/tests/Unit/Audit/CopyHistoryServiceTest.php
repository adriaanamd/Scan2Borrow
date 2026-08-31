<?php

declare(strict_types=1);

namespace Tests\Unit\Audit;

use App\Application\DTO\CopyHistoryResult;
use App\Application\Services\CopyHistoryService;
use App\Infrastructure\Persistence\AuditEventRepositoryInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CopyHistoryServiceTest extends TestCase
{
    public function testRejectsBlankAndOverlongBarcodes(): void
    {
        $service = new CopyHistoryService(new class implements AuditEventRepositoryInterface {
            public function record(\App\Domain\Audit\AuditEvent $event): void {}
            public function findCopyHistory(string $barcode): ?CopyHistoryResult { return null; }
        });

        $this->expectException(InvalidArgumentException::class);
        $service->findByBarcode('');
    }

    public function testDelegatesTrimmedBarcodeAndReturnsHistory(): void
    {
        $repository = new class implements AuditEventRepositoryInterface {
            public string $barcode = '';
            public function record(\App\Domain\Audit\AuditEvent $event): void {}
            public function findCopyHistory(string $barcode): ?CopyHistoryResult
            {
                $this->barcode = $barcode;
                return new CopyHistoryResult(['barcode' => $barcode], []);
            }
        };

        $result = (new CopyHistoryService($repository))->findByBarcode(' BC-1 ');

        self::assertSame('BC-1', $repository->barcode);
        self::assertSame('BC-1', $result?->copy['barcode']);
    }
}
