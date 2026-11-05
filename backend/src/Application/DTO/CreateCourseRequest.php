<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class CreateCourseRequest
{
    public function __construct(public string $name)
    {
    }
}
