<?php

declare(strict_types=1);

namespace App\Application\Validators;

use App\Application\DTO\BookMutationRequest;
use App\Domain\Book\BookStatus;

final class BookMutationValidator
{
    public function firstError(BookMutationRequest $request): ?string
    {
        if ($request->title === '' || ($request->barcode === '' && $request->quantity === 1)) {
            return 'Barcode and title are required.';
        }
        if ($request->quantity < 1) return 'Quantity must be positive.';

        return null;
    }

    public function status(BookMutationRequest $request): BookStatus
    {
        return BookStatus::tryFrom($request->status) ?? BookStatus::AVAILABLE;
    }
}
