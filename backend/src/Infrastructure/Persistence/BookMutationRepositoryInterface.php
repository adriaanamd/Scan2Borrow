<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\DTO\BookMutationRequest;

interface BookMutationRepositoryInterface
{
    public function barcodeExists(string $barcode, ?int $exceptId = null): bool;

    public function accessionExists(string $accessionNo, ?int $exceptId = null): bool;

    public function create(BookMutationRequest $request): int;

    public function update(int $id, BookMutationRequest $request): void;
}
