<?php

declare(strict_types=1);

namespace Tests\Unit\Book;

use App\Application\DTO\BookMutationRequest;
use App\Application\Validators\BookMutationValidator;
use App\Domain\Book\BookStatus;
use PHPUnit\Framework\TestCase;

final class BookMutationValidatorTest extends TestCase
{
    public function testRequiresBarcodeAndTitle(): void
    {
        $validator = new BookMutationValidator();

        self::assertSame('Title is required.', $validator->firstError(new BookMutationRequest()));
    }

    public function testInvalidStatusFallsBackToAvailableAndPreservesKnownStatuses(): void
    {
        $validator = new BookMutationValidator();
        $request = new BookMutationRequest('BK-1', 'Clean Code', status: 'invalid');

        self::assertNull($validator->firstError($request));
        self::assertSame(BookStatus::AVAILABLE, $validator->status($request));
        self::assertSame(BookStatus::BORROWED, $validator->status($request->withStatus('Borrowed')));
        self::assertSame(BookStatus::RESERVED, $validator->status($request->withStatus('Reserved')));
    }

    public function testAcceptsOptionalInventoryFields(): void
    {
        $request = new BookMutationRequest(
            'BK-2',
            'The Great Gatsby',
            accessionNo: 'ACC-2',
            isbn: '9780743273565',
            author: 'F. Scott Fitzgerald',
            categoryName: 'Literature',
            keywords: ['classic', 'fiction'],
        );

        self::assertNull((new BookMutationValidator())->firstError($request));
    }
}
