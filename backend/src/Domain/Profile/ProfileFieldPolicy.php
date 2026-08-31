<?php

declare(strict_types=1);

namespace App\Domain\Profile;

final class ProfileFieldPolicy
{
    /** @var array<string, string> */
    private const REQUESTABLE = [
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
    ];

    /** @var list<string> */
    private const ADMIN_ONLY = [
        'id',
        'barcode',
        'role',
        'status',
        'password',
        'password_hash',
        'created_at',
    ];

    private function __construct()
    {
    }

    /** @return array<string, string> */
    public static function requestable(): array
    {
        return self::REQUESTABLE;
    }

    /** @return list<string> */
    public static function adminOnly(): array
    {
        return self::ADMIN_ONLY;
    }

    public static function isRequestable(string $field): bool
    {
        return array_key_exists($field, self::REQUESTABLE);
    }
}
