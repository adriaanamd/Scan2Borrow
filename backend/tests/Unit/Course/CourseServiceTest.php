<?php

declare(strict_types=1);

namespace Tests\Unit\Course;

use App\Application\DTO\CreateCourseRequest;
use App\Application\Services\CourseService;
use App\Domain\Course\Course;
use App\Infrastructure\Persistence\CourseRepositoryInterface;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CourseServiceTest extends TestCase
{
    /** @var CourseRepositoryInterface&MockObject */
    private CourseRepositoryInterface $courses;

    private CourseService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->courses = $this->createMock(CourseRepositoryInterface::class);
        $this->service = new CourseService($this->courses);
    }

    public function testCreateTrimsAndPersistsAValidCourseName(): void
    {
        $course = new Course(1, 'BSIT', '2026-11-05 00:00:00');
        $this->courses->expects(self::once())
            ->method('existsByName')
            ->with('BSIT')
            ->willReturn(false);
        $this->courses->expects(self::once())
            ->method('create')
            ->with('BSIT')
            ->willReturn($course);

        self::assertSame($course, $this->service->create(new CreateCourseRequest('  BSIT  ')));
    }

    public function testCreateRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Course name is required.');

        $this->service->create(new CreateCourseRequest('   '));
    }

    public function testCreateRejectsNamesLongerThanOneHundredTwentyCharacters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('120 characters or fewer');

        $this->service->create(new CreateCourseRequest(str_repeat('A', 121)));
    }

    public function testCreateRejectsCaseInsensitiveDuplicates(): void
    {
        $this->courses->expects(self::once())
            ->method('existsByName')
            ->with('BSIT')
            ->willReturn(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already exists');

        $this->service->create(new CreateCourseRequest('  BSIT  '));
    }

    public function testExistsDelegatesToTheRepository(): void
    {
        $this->courses->expects(self::once())
            ->method('existsByName')
            ->with('BSA')
            ->willReturn(true);

        self::assertTrue($this->service->exists(' BSA '));
    }
}
