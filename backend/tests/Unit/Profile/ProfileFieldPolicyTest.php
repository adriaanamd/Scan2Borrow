<?php

declare(strict_types=1);

namespace Tests\Unit\Profile;

use App\Domain\Profile\ProfileFieldPolicy;
use PHPUnit\Framework\TestCase;

final class ProfileFieldPolicyTest extends TestCase
{
    public function testListsAllRequestableProfileFields(): void
    {
        self::assertSame([
            'firstname' => 'firstname',
            'middlename' => 'middlename',
            'lastname' => 'lastname',
            'email' => 'email',
            'contact_no' => 'contact_no',
            'course' => 'course',
            'year_level' => 'year_level',
            'department' => 'department',
            'position' => 'position',
            'photo' => 'photo',
        ], ProfileFieldPolicy::requestable());
    }

    public function testNeverTreatsIdentityOrSecurityFieldsAsRequestable(): void
    {
        foreach (['barcode', 'role', 'status', 'password', 'password_hash', 'id', 'unknown'] as $field) {
            self::assertFalse(ProfileFieldPolicy::isRequestable($field), $field);
        }
    }

    public function testReturnsTheAdminOnlyFieldList(): void
    {
        self::assertContains('barcode', ProfileFieldPolicy::adminOnly());
        self::assertContains('password', ProfileFieldPolicy::adminOnly());
        self::assertContains('status', ProfileFieldPolicy::adminOnly());
    }
}
