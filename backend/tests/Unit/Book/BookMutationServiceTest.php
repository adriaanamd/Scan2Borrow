<?php

declare(strict_types=1);

namespace Tests\Unit\Book;

use App\Application\DTO\BookMutationRequest;
use App\Application\Services\BookMutationService;
use App\Application\Validators\BookMutationValidator;
use App\Infrastructure\Persistence\BookMutationRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class BookMutationServiceTest extends TestCase
{
    public function testCreateRejectsDuplicateBarcodeAndAccession(): void
    {
        $repository = new FakeBookMutationRepository();
        $repository->barcodeExists = true;
        $service = new BookMutationService(new BookMutationValidator(), $repository);

        self::assertSame(
            'A book with this barcode already exists.',
            $service->create(new BookMutationRequest('BK-1', 'Title'))->message(),
        );

        $repository->barcodeExists = false;
        $repository->accessionExists = true;
        self::assertSame(
            'A book with this accession number already exists.',
            $service->create(new BookMutationRequest('BK-2', 'Title', accessionNo: 'ACC-2'))->message(),
        );
    }

    public function testValidCreateAndUpdateDelegateTypedRequest(): void
    {
        $repository = new FakeBookMutationRepository();
        $service = new BookMutationService(new BookMutationValidator(), $repository);
        $request = new BookMutationRequest('BK-3', 'Title');

        self::assertTrue($service->create($request)->successful());
        self::assertTrue($service->update(3, $request)->successful());
        self::assertSame(3, $repository->updatedId);
    }
}

final class FakeBookMutationRepository implements BookMutationRepositoryInterface
{
    public bool $barcodeExists = false;

    public bool $accessionExists = false;

    public ?int $updatedId = null;

    public function barcodeExists(string $barcode, ?int $exceptId = null): bool
    {
        return $this->barcodeExists;
    }

    public function accessionExists(string $accessionNo, ?int $exceptId = null): bool
    {
        return $this->accessionExists;
    }

    public function create(BookMutationRequest $request): int
    {
        return 1;
    }

    public function update(int $id, BookMutationRequest $request): void
    {
        $this->updatedId = $id;
    }
}
