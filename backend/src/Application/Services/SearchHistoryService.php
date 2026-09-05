<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Infrastructure\Persistence\SearchHistoryRepositoryInterface;
use InvalidArgumentException;

final class SearchHistoryService
{
    public function __construct(private readonly SearchHistoryRepositoryInterface $history)
    {
    }

    public function record(int $userId, string $query): void
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('A valid borrower is required.');
        }

        $normalized = preg_replace('/\s+/u', ' ', trim($query));
        $normalized = is_string($normalized) ? $normalized : trim($query);
        $length = function_exists('mb_strlen') ? mb_strlen($normalized, 'UTF-8') : strlen($normalized);
        if ($length < 1 || $length > 255) {
            throw new InvalidArgumentException('Search must contain between 1 and 255 characters.');
        }

        $this->history->record($userId, $normalized);
    }
}
