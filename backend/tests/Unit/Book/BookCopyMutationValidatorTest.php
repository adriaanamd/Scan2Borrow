<?php

declare(strict_types=1);

namespace Tests\Unit\Book;

use App\Application\DTO\BookCopyMutationRequest;
use App\Application\Validators\BookCopyMutationValidator;
use PHPUnit\Framework\TestCase;

final class BookCopyMutationValidatorTest extends TestCase
{
    public function testCopyBarcodeIsRequiredForPhysicalCopyEdits(): void
    {
        $error = (new BookCopyMutationValidator())->firstError(
            new BookCopyMutationRequest(12, ''),
        );

        self::assertSame('Copy barcode is required.', $error);
    }

    public function testValidPhysicalCopyRequestHasNoValidationError(): void
    {
        $error = (new BookCopyMutationValidator())->firstError(
            new BookCopyMutationRequest(12, 'COPY-12'),
        );

        self::assertNull($error);
    }

    public function testLostStatusRequiresAReason(): void
    {
        $error = (new BookCopyMutationValidator())->firstError(
            new BookCopyMutationRequest(12, 'COPY-12', status: 'Lost'),
        );

        self::assertSame('A reason is required when marking a copy lost or damaged.', $error);
    }

    public function testDamagedStatusAcceptsAReason(): void
    {
        $error = (new BookCopyMutationValidator())->firstError(
            new BookCopyMutationRequest(12, 'COPY-12', status: 'Damaged', reason: 'Water damage'),
        );

        self::assertNull($error);
    }

    public function testUnknownStatusIsRejected(): void
    {
        $error = (new BookCopyMutationValidator())->firstError(
            new BookCopyMutationRequest(12, 'COPY-12', status: 'Unknown'),
        );

        self::assertSame('Copy status is invalid.', $error);
    }
}
