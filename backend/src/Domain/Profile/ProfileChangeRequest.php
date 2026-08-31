<?php

declare(strict_types=1);

namespace App\Domain\Profile;

use DateTimeImmutable;

final readonly class ProfileChangeRequest
{
    /**
     * @param array<string, string> $originalValues
     * @param array<string, string> $requestedValues
     */
    public function __construct(
        public int $id,
        public int $userId,
        public ProfileChangeRequestStatus $status,
        public array $originalValues,
        public array $requestedValues,
        public ?string $originalPhoto,
        public ?string $requestedPhoto,
        public DateTimeImmutable $requestedAt,
        public ?DateTimeImmutable $reviewedAt,
        public ?int $reviewedBy,
        public ?string $reviewNote,
    ) {
    }
}
