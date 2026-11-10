<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Domain\Auth\Role;
use App\Domain\Auth\SessionIdentity;
use App\Domain\Auth\StaffAccessPolicy;
use App\Domain\Auth\StaffAccessProfile;
use PHPUnit\Framework\TestCase;

final class StaffAccessPolicyTest extends TestCase
{
    public function testLibraryAideIsOperationalStaffButNotSystemAdmin(): void
    {
        $policy = new StaffAccessPolicy();
        $identity = new SessionIdentity(
            11,
            Role::ADMIN,
            'library-aide-session',
            StaffAccessProfile::LIBRARY_AIDE,
        );

        self::assertTrue($policy->isStaff($identity));
        self::assertTrue($policy->isLibraryAide($identity));
        self::assertFalse($policy->isSystemAdmin($identity));
        self::assertFalse($policy->canMutateInventory($identity, 'archive'));
        self::assertFalse($policy->canMutateInventory($identity, 'delete'));
        self::assertTrue($policy->canMutateInventory($identity, 'update'));
    }

    public function testLegacyAdminWithoutProfileRemainsSystemAdmin(): void
    {
        $policy = new StaffAccessPolicy();
        $identity = new SessionIdentity(12, Role::ADMIN, 'legacy-admin-session');

        self::assertTrue($policy->isSystemAdmin($identity));
        self::assertFalse($policy->isLibraryAide($identity));
        self::assertTrue($policy->canMutateInventory($identity, 'delete'));
    }
}
