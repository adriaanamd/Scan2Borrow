<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\BookMutationRequest;
use App\Application\DTO\BookMutationResult;
use App\Application\Validators\BookMutationValidator;
use App\Infrastructure\Persistence\BookMutationRepositoryInterface;

final class BookMutationService
{
    public function __construct(
        private readonly BookMutationValidator $validator,
        private readonly BookMutationRepositoryInterface $books,
    ) {
    }

    public function create(BookMutationRequest $request): BookMutationResult
    {
        $validationError = $this->validator->firstError($request);
        if ($validationError !== null) {
            return BookMutationResult::failure($validationError);
        }

        if ($this->books->barcodeExists($request->barcode)) {
            return BookMutationResult::failure('A book with this barcode already exists.');
        }

        if ($request->accessionNo !== '' && $this->books->accessionExists($request->accessionNo)) {
            return BookMutationResult::failure('A book with this accession number already exists.');
        }

        return BookMutationResult::success($this->books->create($request));
    }

    public function update(int $id, BookMutationRequest $request): BookMutationResult
    {
        if ($id <= 0) {
            return BookMutationResult::failure('Invalid book id.');
        }

        $validationError = $this->validator->firstError($request);
        if ($validationError !== null) {
            return BookMutationResult::failure($validationError);
        }

        if ($this->books->barcodeExists($request->barcode, $id)) {
            return BookMutationResult::failure('Another book already uses this barcode.');
        }

        if ($request->accessionNo !== '' && $this->books->accessionExists($request->accessionNo, $id)) {
            return BookMutationResult::failure('Another book already uses this accession number.');
        }

        $this->books->update($id, $request);

        return BookMutationResult::success($id);
    }
}
