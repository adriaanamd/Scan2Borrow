<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class SearchProfile
{
    /** @param array<string, int> $weights */
    private function __construct(private array $weights)
    {
    }

    /** @param list<string> $recentQueries */
    public static function fromRecentSearches(array $recentQueries): self
    {
        $weights = [];
        $queries = array_values(array_filter($recentQueries, 'is_string'));
        $position = 0;
        foreach ($queries as $query) {
            $position++;
            foreach (self::tokens($query) as $term) {
                $weights[$term] = ($weights[$term] ?? 0) + $position;
            }
        }

        uksort($weights, static function (string $left, string $right) use ($weights): int {
            $weightComparison = $weights[$right] <=> $weights[$left];

            return $weightComparison !== 0 ? $weightComparison : $left <=> $right;
        });

        return new self(array_slice($weights, 0, 25, true));
    }

    /** @return array<string, int> */
    public function weights(): array
    {
        return $this->weights;
    }

    /** @return list<string> */
    public function terms(): array
    {
        return array_keys($this->weights);
    }

    public function isEmpty(): bool
    {
        return $this->weights === [];
    }

    public function fullTextQuery(): string
    {
        $parts = [];
        $maximumWeight = $this->weights === [] ? 1 : max($this->weights);
        $boostCutoff = max(2, intdiv($maximumWeight + 1, 2));
        foreach ($this->weights as $term => $weight) {
            $safe = preg_replace('/[^\p{L}\p{N}+#]/u', '', $term) ?? '';
            if ($safe === '') {
                continue;
            }
            $operator = '';
            if ($maximumWeight > 1 && $weight >= $boostCutoff) {
                $operator = '>';
            } elseif ($maximumWeight > 1 && $weight * 2 <= $maximumWeight) {
                $operator = '<';
            }
            $queryTerm = strpbrk($safe, '+#') !== false
                ? '"' . str_replace('"', '', $safe) . '"'
                : $safe . '*';
            $parts[] = $operator . $queryTerm;
        }

        return implode(' ', $parts);
    }

    /** @return list<string> */
    private static function tokens(string $query): array
    {
        $parts = preg_split('/[^\p{L}\p{N}+#]+/u', $query, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts)) {
            return [];
        }

        $tokens = [];
        foreach ($parts as $part) {
            $term = function_exists('mb_strtolower') ? mb_strtolower($part, 'UTF-8') : strtolower($part);
            $hasLetterOrNumber = preg_match('/[\p{L}\p{N}]/u', $term) === 1;
            $length = function_exists('mb_strlen') ? mb_strlen($term, 'UTF-8') : strlen($term);
            if (!$hasLetterOrNumber || ($length < 2 && $term !== 'c#')) {
                continue;
            }
            $tokens[] = substr($term, 0, 50);
        }

        return array_values(array_unique($tokens));
    }
}
