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
        if (BookStatus::tryFrom($request->status) === null) {
            return 'Copy status is invalid.';
        }
        if (in_array($request->status, [BookStatus::LOST->value, BookStatus::DAMAGED->value], true)
            && trim($request->reason) === '') {
            return 'A reason is required when marking a copy lost or damaged.';
        }

        return null;
    }

    public function status(BookCopyMutationRequest $request): BookStatus
    {
        return BookStatus::tryFrom($request->status) ?? BookStatus::AVAILABLE;
    }
}
