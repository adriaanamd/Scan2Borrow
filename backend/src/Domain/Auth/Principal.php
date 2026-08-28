<?php

declare(strict_types=1);

namespace App\Domain\Auth;

final readonly class Principal
{
    public function __construct(
        private int $id,
        private Role $role,
    ) {
    }

    public function id(): int
    {
        return $this->id;
    }

    public function role(): Role
    {
        return $this->role;
    }
}
