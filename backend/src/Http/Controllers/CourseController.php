<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\DTO\CreateCourseRequest;
use App\Application\Services\CourseService;
use App\Application\Services\CsrfService;
use App\Application\Services\SessionService;
use App\Domain\Course\Course;
use App\Domain\Auth\StaffAccessPolicy;
use App\Http\Requests\ServerRequest;
use App\Http\Responses\JsonResponse;
use InvalidArgumentException;
use RuntimeException;

final readonly class CourseController
{
    public function __construct(
        private SessionService $sessions,
        private CsrfService $csrf,
        private CourseService $courses,
        private ?StaffAccessPolicy $access = null,
    ) {
    }

    public function index(ServerRequest $request): JsonResponse
    {
        return new JsonResponse(200, ['ok' => true, 'data' => [
            'courses' => $this->courseRows(),
        ]]);
    }

    public function adminIndex(ServerRequest $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return $this->unauthorized();
        }

        return new JsonResponse(200, ['ok' => true, 'data' => [
            'courses' => $this->courseRows(),
        ]]);
    }

    public function store(ServerRequest $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return $this->unauthorized();
        }

        try {
            $this->csrf->assertValid($this->bodyString($request, 'csrf'));
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(419, ['ok' => false, 'errors' => [$exception->getMessage()]]);
        }

        try {
            $course = $this->courses->create(new CreateCourseRequest($this->bodyString($request, 'name')));
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(422, ['ok' => false, 'errors' => [$exception->getMessage()]]);
        } catch (RuntimeException) {
            return new JsonResponse(422, ['ok' => false, 'errors' => ['Unable to save the course.']]);
        }

        return new JsonResponse(201, ['ok' => true, 'data' => [
            'course' => $this->courseRow($course),
        ]]);
    }

    /** @return list<array{id: int, name: string, created_at: string}> */
    private function courseRows(): array
    {
        return array_map(fn (Course $course): array => $this->courseRow($course), $this->courses->all());
    }

    /** @return array{id: int, name: string, created_at: string} */
    private function courseRow(Course $course): array
    {
        return [
            'id' => $course->id,
            'name' => $course->name,
            'created_at' => $course->createdAt,
        ];
    }

    private function isAdmin(): bool
    {
        $identity = $this->sessions->current();

        return ($this->access ?? new StaffAccessPolicy())->isSystemAdmin($identity);
    }

    private function unauthorized(): JsonResponse
    {
        return new JsonResponse(401, ['ok' => false, 'errors' => ['Staff authentication required.']]);
    }

    private function bodyString(ServerRequest $request, string $key): string
    {
        $value = $request->body()[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }
}
