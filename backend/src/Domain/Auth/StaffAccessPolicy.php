<?php

declare(strict_types=1);

namespace App\Domain\Auth;

final class StaffAccessPolicy
{
    public function isStaff(Principal|SessionIdentity|null $identity): bool
    {
        return $identity !== null
            && in_array($identity->role(), [Role::ADMIN, Role::LIBRARIAN], true);
    }

    public function isSystemAdmin(Principal|SessionIdentity|null $identity): bool
    {
        return $identity !== null
            && $identity->role() === Role::ADMIN
            && ($identity->accessProfile() === null
                || $identity->accessProfile() === StaffAccessProfile::SYSTEM_ADMIN);
    }

    public function isLibraryAide(Principal|SessionIdentity|null $identity): bool
    {
        return $identity !== null
            && $identity->role() === Role::ADMIN
            && $identity->accessProfile() === StaffAccessProfile::LIBRARY_AIDE;
    }

    public function canUpdateOperationalData(Principal|SessionIdentity|null $identity): bool
    {
        return $this->isStaff($identity);
    }

    public function canMutateInventory(Principal|SessionIdentity|null $identity, string $action): bool
    {
        if (!$this->isStaff($identity)) {
            return false;
        }

        return !$this->isLibraryAide($identity) || !in_array($action, ['archive', 'delete'], true);
    }
}
