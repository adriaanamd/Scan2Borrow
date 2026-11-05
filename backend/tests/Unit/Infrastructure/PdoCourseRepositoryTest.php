<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Domain\Course\Course;
use App\Infrastructure\Persistence\PdoCourseRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoCourseRepositoryTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE courses ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'name TEXT NOT NULL UNIQUE, '
            . 'created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)'
        );
    }

    public function testAllReturnsCoursesInNameOrder(): void
    {
        $this->pdo->exec("INSERT INTO courses (name, created_at) VALUES ('BSA', '2026-11-05 00:00:00'), ('BSIT', '2026-11-05 00:00:01')");

        $courses = (new PdoCourseRepository($this->pdo))->all();

        self::assertCount(2, $courses);
        self::assertSame(['BSA', 'BSIT'], array_map(static fn (Course $course): string => $course->name, $courses));
    }

    public function testExistsByNameTrimsAndIgnoresCase(): void
    {
        $this->pdo->exec("INSERT INTO courses (name) VALUES ('BSIT')");

        $repository = new PdoCourseRepository($this->pdo);

        self::assertTrue($repository->existsByName(' bsit '));
        self::assertFalse($repository->existsByName('BSEd'));
    }

    public function testCreatePersistsAndReturnsTheNewCourse(): void
    {
        $course = (new PdoCourseRepository($this->pdo))->create('BSEd Major in Science');

        self::assertSame('BSEd Major in Science', $course->name);
        self::assertGreaterThan(0, $course->id);
        self::assertNotSame('', $course->createdAt);
    }
}
