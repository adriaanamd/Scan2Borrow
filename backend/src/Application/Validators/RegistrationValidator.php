<?php

declare(strict_types=1);

namespace App\Application\Validators;

use App\Application\DTO\RegistrationRequest;

final class RegistrationValidator
{
    public function firstError(RegistrationRequest $request): ?string
    {
        if ($request->barcode === '' || $request->firstname === '' || $request->lastname === '') {
            return 'Barcode, first name and last name are required.';
        }

        if (!in_array($request->role, ['student', 'teacher'], true)) {
            return 'Please select a valid role.';
        }

        if ($request->role === 'student' && ($request->course === '' || $request->yearLevel === '')) {
            return 'Please select course and year level for students.';
        }

        if ($request->role === 'teacher' && ($request->department === '' || $request->position === '')) {
            return 'Please enter department and position for teachers.';
        }

        if ($request->email !== '' && filter_var($request->email, FILTER_VALIDATE_EMAIL) === false) {
            return 'Please enter a valid email address.';
        }

        if ($request->contactNo !== '' && preg_match('/^[0-9+\-\s()]{7,15}$/', $request->contactNo) !== 1) {
            return 'Please enter a valid contact number (7-15 digits, may include +, -, spaces, or parentheses).';
        }

        return null;
    }
}
