<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Auth\Role;

final readonly class BulkBorrowRequest
{
    /** @param list<BulkBorrowItem> $items */
    public function __construct(
        public int $userId,
        public Role $role,
        public array $items,
        public ?string $requestedDueDate = null,
    ) {
    }
}
