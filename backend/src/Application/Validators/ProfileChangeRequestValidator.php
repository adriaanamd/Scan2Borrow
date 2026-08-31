<?php

declare(strict_types=1);

namespace App\Application\Validators;

use App\Domain\Profile\ProfileFieldPolicy;
use InvalidArgumentException;

final class ProfileChangeRequestValidator
{
    /** @var array<string, int> */
    private const LIMITS = [
        'firstname' => 80,
        'middlename' => 80,
        'lastname' => 80,
        'email' => 120,
        'contact_no' => 30,
        'course' => 100,
        'year_level' => 20,
        'department' => 120,
        'position' => 120,
    ];

    /**
     * @param array<string, mixed> $input
     * @param array<string, string> $current
     * @return array<string, string>
     */
    public function validate(array $input, array $current): array
    {
        foreach (array_keys($input) as $key) {
            if (in_array($key, ['csrf', 'photo_data'], true)) {
                continue;
            }
            if (!ProfileFieldPolicy::isRequestable($key) || $key === 'photo') {
                throw new InvalidArgumentException('This profile field cannot be changed from Settings.');
            }
        }

        $changed = [];
        foreach (array_keys(self::LIMITS) as $field) {
            if (!array_key_exists($field, $input)) {
                continue;
            }
            if (!is_string($input[$field])) {
                throw new InvalidArgumentException('Profile values must be text.');
            }
            $value = trim($input[$field]);
            if (strlen($value) > self::LIMITS[$field]) {
                throw new InvalidArgumentException(ucfirst($field) . ' is too long.');
            }
            if ($value === '' && in_array($field, ['firstname', 'lastname'], true)) {
                throw new InvalidArgumentException('First name and last name are required.');
            }
            if ($field === 'email' && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException('Please enter a valid email address.');
            }
            if ($value !== ($current[$field] ?? '')) {
                $changed[$field] = $value;
            }
        }

        if ($changed === []) {
            throw new InvalidArgumentException('Make at least one profile change before submitting.');
        }

        return $changed;
    }

    public function validateReview(string $decision, string $note): string
    {
        if (!in_array($decision, ['approve', 'reject'], true)) {
            throw new InvalidArgumentException('Choose approve or reject.');
        }

        return substr(trim($note), 0, 500);
    }
}
