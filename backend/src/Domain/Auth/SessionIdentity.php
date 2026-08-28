<?php

declare(strict_types=1);

namespace App\Domain\Auth;

final readonly class SessionIdentity
{
    public function __construct(
        private int $userId,
        private Role $role,
        private string $sessionId,
    ) {
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function role(): Role
    {
        return $this->role;
    }

    public function sessionId(): string
    {
        return $this->sessionId;
    }
}
