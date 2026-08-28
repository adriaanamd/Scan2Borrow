<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Auth\Role;

final readonly class BorrowRequest
{
    public function __construct(
        public int $userId,
        public Role $role,
        public string $bookBarcode,
        public ?string $requestedDueDate = null,
    ) {
    }
}
