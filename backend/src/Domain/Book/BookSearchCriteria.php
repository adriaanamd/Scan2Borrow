<?php

declare(strict_types=1);

namespace App\Domain\Book;

final readonly class BookSearchCriteria
{
    private function __construct(
        private string $search,
        private ?BookStatus $status,
        private bool $isArchived,
        private int $page,
        private int $perPage,
        private string $sort,
        private string $direction,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     */
    public static function fromArray(array $filters): self
    {
        $search = $filters['search'] ?? '';
        $statusValue = $filters['status'] ?? '';
        $archived = $filters['archived'] ?? '0';
        $sort = $filters['sort'] ?? '';
        $direction = $filters['dir'] ?? 'desc';
        $status = is_string($statusValue) && $statusValue !== ''
            ? BookStatus::tryFrom($statusValue)
            : null;
        $sortValue = is_string($sort) && in_array($sort, self::sortableFields(), true)
            ? $sort
            : 'created_at';
        $directionValue = is_string($direction) && strtolower($direction) === 'asc' ? 'ASC' : 'DESC';

        return new self(
            is_string($search) ? trim($search) : '',
            $status,
            $archived === '1',
            max(1, self::integerValue($filters['page'] ?? 1, 1)),
            min(50, max(5, self::integerValue($filters['per_page'] ?? 10, 10))),
            $sortValue,
            $directionValue,
        );
    }

    public function search(): string
    {
        return $this->search;
    }

    public function status(): ?BookStatus
    {
        return $this->status;
    }

    public function archived(): bool
    {
        return $this->isArchived;
    }

    public function page(): int
    {
        return $this->page;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function sort(): string
    {
        return $this->sort;
    }

    public function direction(): string
    {
        return $this->direction;
    }

    /**
     * @return list<string>
     */
    private static function sortableFields(): array
    {
        return ['title', 'author', 'publisher', 'category_name', 'status', 'barcode', 'accession_no', 'created_at'];
    }

    private static function integerValue(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && filter_var($value, FILTER_VALIDATE_INT) !== false) {
            return (int) $value;
        }

        return $default;
    }
}
