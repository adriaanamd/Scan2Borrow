<?php

declare(strict_types=1);

namespace App\Application\Validators;

use App\Application\DTO\BookMutationRequest;
use App\Domain\Book\BookStatus;

final class BookMutationValidator
{
    public function firstError(BookMutationRequest $request): ?string
    {
        if ($request->title === '') {
            return 'Title is required.';
        }
        if ($request->quantity < 1) return 'Quantity must be positive.';
        if (in_array($request->status, [BookStatus::LOST->value, BookStatus::DAMAGED->value], true)) {
            return 'Lost or damaged status must be recorded on an individual copy with a reason.';
        }

        return null;
    }

    public function status(BookMutationRequest $request): BookStatus
    {
        return BookStatus::tryFrom($request->status) ?? BookStatus::AVAILABLE;
    }
}
