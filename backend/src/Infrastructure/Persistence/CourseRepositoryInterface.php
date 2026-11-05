<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Course\Course;

interface CourseRepositoryInterface
{
    /** @return list<Course> */
    public function all(): array;

    public function existsByName(string $name): bool;

    public function create(string $name): Course;
}
