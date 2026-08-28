<?php

declare(strict_types=1);

namespace App\Application\Validators;

use App\Application\DTO\BookMutationRequest;
use App\Domain\Book\BookStatus;

final class BookMutationValidator
{
    public function firstError(BookMutationRequest $request): ?string
    {
        if ($request->barcode === '' || $request->title === '') {
            return 'Barcode and title are required.';
        }

        return null;
    }

    public function status(BookMutationRequest $request): BookStatus
    {
        return BookStatus::tryFrom($request->status) ?? BookStatus::AVAILABLE;
    }
}
