<?php

declare(strict_types=1);

namespace Tests\Unit\Registration;

use App\Application\DTO\RegistrationRequest;
use App\Application\Validators\RegistrationValidator;
use PHPUnit\Framework\TestCase;

final class RegistrationValidatorTest extends TestCase
{
    public function testRequiresBarcodeAndNames(): void
    {
        $request = new RegistrationRequest('', '', '', '', 'student');

        self::assertSame(
            'Barcode, first name and last name are required.',
            (new RegistrationValidator())->firstError($request),
        );
    }

    public function testRejectsUnknownRole(): void
    {
        $request = new RegistrationRequest('2024', 'Juan', '', 'Cruz', 'visitor');

        self::assertSame('Please select a valid role.', (new RegistrationValidator())->firstError($request));
    }

    public function testRequiresStudentCourseAndYear(): void
    {
        $request = new RegistrationRequest('2024', 'Juan', '', 'Cruz', 'student', otpChannel: 'phone', contactNo: '09170000001');

        self::assertSame(
            'Please select course and year level for students.',
            (new RegistrationValidator())->firstError($request),
        );
    }

    public function testRequiresTeacherDepartmentAndPosition(): void
    {
        $request = new RegistrationRequest('T2024', 'Ana', '', 'Reyes', 'teacher', otpChannel: 'phone', contactNo: '09170000002');

        self::assertSame(
            'Please enter department and position for teachers.',
            (new RegistrationValidator())->firstError($request),
        );
    }

    public function testRejectsInvalidEmailAndContactNumber(): void
    {
        $validator = new RegistrationValidator();
        $request = new RegistrationRequest(
            '2024',
            'Juan',
            '',
            'Cruz',
            'student',
            otpChannel: 'email',
            course: 'BSIT',
            yearLevel: '3',
            email: 'not-an-email',
            contactNo: 'bad',
        );

        self::assertSame('Please enter a valid email address.', $validator->firstError($request));
        self::assertSame(
            'Please enter a valid contact number (7-15 digits, may include +, -, spaces, or parentheses).',
            $validator->firstError($request->withEmail('valid@example.com')),
        );
    }

    public function testRequiresAnOtpChannelAndItsSelectedDestination(): void
    {
        $validator = new RegistrationValidator();

        self::assertSame(
            'Please choose how to receive your verification code.',
            $validator->firstError(new RegistrationRequest(
                '2024', 'Juan', '', 'Cruz', 'student',
                otpChannel: '', course: 'BSIT', yearLevel: '3', contactNo: '09170000001',
            )),
        );
        self::assertSame(
            'Please enter an email address to receive your verification code.',
            $validator->firstError(new RegistrationRequest(
                '2024', 'Juan', '', 'Cruz', 'student',
                otpChannel: 'email', course: 'BSIT', yearLevel: '3', contactNo: '09170000001',
            )),
        );
        self::assertSame(
            'Please enter a cellphone number to receive your verification code.',
            $validator->firstError(new RegistrationRequest(
                '2024', 'Juan', '', 'Cruz', 'student',
                otpChannel: 'phone', course: 'BSIT', yearLevel: '3', email: 'juan@example.test',
            )),
        );
    }

    public function testAcceptsCompleteStudentAndTeacherPayloads(): void
    {
        $validator = new RegistrationValidator();

        self::assertNull($validator->firstError(new RegistrationRequest(
            '2024', 'Juan', '', 'Cruz', 'student', otpChannel: 'phone', course: 'BSIT', yearLevel: '3', contactNo: '09170000001',
        )));
        self::assertNull($validator->firstError(new RegistrationRequest(
            'T2024', 'Ana', '', 'Reyes', 'teacher', otpChannel: 'phone', department: 'Science', position: 'College Teacher',
            contactNo: '09170000002',
        )));
    }
}
