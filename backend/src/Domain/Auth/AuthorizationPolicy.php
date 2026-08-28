<?php

declare(strict_types=1);

namespace App\Domain\Auth;

final class AuthorizationPolicy
{
    /**
     * @param list<Role> $allowedRoles
     */
    public function allows(
        ?Principal $principal,
        array $allowedRoles,
        bool $guestOnly = false,
    ): bool {
        if ($principal === null) {
            return false;
        }

        if ($guestOnly) {
            return $principal->role() === Role::GUEST;
        }

        if ($allowedRoles === []) {
            return true;
        }

        return in_array($principal->role(), $allowedRoles, true);
    }
}
