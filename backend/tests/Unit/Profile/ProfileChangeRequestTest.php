<?php

declare(strict_types=1);

namespace Tests\Unit\Profile;

use App\Domain\Profile\ProfileChangeRequest;
use App\Domain\Profile\ProfileChangeRequestStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ProfileChangeRequestTest extends TestCase
{
    public function testStatusLabelsAndValuesAreStable(): void
    {
        self::assertSame('pending', ProfileChangeRequestStatus::PENDING->value);
        self::assertSame('Pending review', ProfileChangeRequestStatus::PENDING->label());
        self::assertSame('Approved', ProfileChangeRequestStatus::APPROVED->label());
        self::assertSame('Rejected', ProfileChangeRequestStatus::REJECTED->label());
    }

    public function testRecordPreservesNullablePhotoAndReviewFields(): void
    {
        $requestedAt = new DateTimeImmutable('2026-08-31 14:32:00');
        $request = new ProfileChangeRequest(
            4,
            9,
            ProfileChangeRequestStatus::PENDING,
            ['firstname' => 'Ada'],
            ['firstname' => 'Grace'],
            null,
            null,
            $requestedAt,
            null,
            null,
            null,
        );

        self::assertSame(4, $request->id);
        self::assertSame(9, $request->userId);
        self::assertSame(['firstname' => 'Grace'], $request->requestedValues);
        self::assertNull($request->reviewedAt);
        self::assertNull($request->reviewNote);
    }
}
