<?php

declare(strict_types=1);

namespace Tests\Unit\Profile;

use App\Application\Validators\ProfileChangeRequestValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ProfileChangeRequestValidatorTest extends TestCase
{
    private ProfileChangeRequestValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ProfileChangeRequestValidator();
    }

    public function testReturnsTrimmedChangedTextFields(): void
    {
        $changed = $this->validator->validate([
            'firstname' => '  Grace ',
            'middlename' => '',
            'lastname' => 'Hopper',
            'email' => 'grace@example.test',
            'contact_no' => '0917',
            'course' => 'CS',
            'year_level' => '4',
            'department' => 'IT',
            'position' => '',
            'csrf' => 'transport-only',
        ], [
            'firstname' => 'Ada',
            'middlename' => '',
            'lastname' => 'Hopper',
            'email' => '',
            'contact_no' => '',
            'course' => 'CS',
            'year_level' => '4',
            'department' => 'IT',
            'position' => '',
        ]);

        self::assertSame(['firstname' => 'Grace', 'email' => 'grace@example.test', 'contact_no' => '0917'], $changed);
    }

    public function testRejectsPrivilegedFieldsAndUnknownInput(): void
    {
        foreach ([['role' => 'admin'], ['barcode' => 'ADMIN'], ['unknown' => 'value']] as $input) {
            $this->expectException(InvalidArgumentException::class);
            $this->validator->validate($input, ['firstname' => 'Ada', 'lastname' => 'Lovelace']);
        }
    }

    public function testRejectsInvalidEmailAndMissingRequiredName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validate(['firstname' => '', 'lastname' => 'Lovelace'], ['firstname' => 'Ada', 'lastname' => 'Lovelace']);
    }

    public function testRejectsNoChangesAndInvalidReviewDecision(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->validate(['firstname' => 'Ada', 'lastname' => 'Lovelace'], ['firstname' => 'Ada', 'lastname' => 'Lovelace']);
    }

    public function testReviewNoteIsBounded(): void
    {
        self::assertSame('Approved.', $this->validator->validateReview('approve', ' Approved. '));
        self::assertSame(500, strlen($this->validator->validateReview('reject', str_repeat('x', 700))));
    }
}
