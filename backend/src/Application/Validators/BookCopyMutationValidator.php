<?php

declare(strict_types=1);

namespace App\Application\Validators;

use App\Application\DTO\BookCopyMutationRequest;
use App\Domain\Book\BookStatus;

final class BookCopyMutationValidator
{
    public function firstError(BookCopyMutationRequest $request): ?string
    {
        if ($request->copyId < 1) {
            return 'A valid copy is required.';
        }
        if ($request->barcode === '') {
            return 'Copy barcode is required.';
        }

        return null;
    }

    public function status(BookCopyMutationRequest $request): BookStatus
    {
        return BookStatus::tryFrom($request->status) ?? BookStatus::AVAILABLE;
    }
}
