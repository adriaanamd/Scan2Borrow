<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Course\Course;
use PDO;
use RuntimeException;

final class PdoCourseRepository implements CourseRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<Course> */
    public function all(): array
    {
        $statement = $this->pdo->query(
            'SELECT id, name, created_at FROM courses ORDER BY name ASC, id ASC'
        );
        if ($statement === false) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn (array $row): Course => $this->fromRow($row), $rows);
    }

    public function existsByName(string $name): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM courses WHERE LOWER(TRIM(name)) = LOWER(TRIM(:name)) LIMIT 1'
        );
        $statement->execute(['name' => trim($name)]);

        return $statement->fetchColumn() !== false;
    }

    public function create(string $name): Course
    {
        $statement = $this->pdo->prepare('INSERT INTO courses (name) VALUES (:name)');
        $statement->execute(['name' => trim($name)]);
        $id = (int) $this->pdo->lastInsertId();

        $created = $this->pdo->prepare(
            'SELECT id, name, created_at FROM courses WHERE id = :id LIMIT 1'
        );
        $created->execute(['id' => $id]);
        $row = $created->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('The course could not be read after creation.');
        }

        return $this->fromRow($row);
    }

    /** @param array<string, mixed> $row */
    private function fromRow(array $row): Course
    {
        return new Course(
            id: is_numeric($row['id'] ?? null) ? (int) $row['id'] : 0,
            name: is_string($row['name'] ?? null) ? $row['name'] : '',
            createdAt: is_string($row['created_at'] ?? null) ? $row['created_at'] : '',
        );
    }
}
