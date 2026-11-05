<?php

declare(strict_types=1);

namespace App\Domain\Course;

final readonly class Course
{
    public function __construct(
        public int $id,
        public string $name,
        public string $createdAt,
    ) {
    }
}
