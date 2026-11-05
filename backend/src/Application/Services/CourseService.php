<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\CreateCourseRequest;
use App\Domain\Course\Course;
use App\Infrastructure\Persistence\CourseRepositoryInterface;
use InvalidArgumentException;

final readonly class CourseService
{
    public function __construct(private CourseRepositoryInterface $courses)
    {
    }

    /** @return list<Course> */
    public function all(): array
    {
        return $this->courses->all();
    }

    public function create(CreateCourseRequest $request): Course
    {
        $name = trim($request->name);
        if ($name === '') {
            throw new InvalidArgumentException('Course name is required.');
        }
        if (mb_strlen($name) > 120) {
            throw new InvalidArgumentException('Course name must be 120 characters or fewer.');
        }
        if ($this->courses->existsByName($name)) {
            throw new InvalidArgumentException('That course already exists.');
        }

        return $this->courses->create($name);
    }

    public function exists(string $name): bool
    {
        return $this->courses->existsByName(trim($name));
    }
}
