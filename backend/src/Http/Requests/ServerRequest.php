<?php

declare(strict_types=1);

namespace App\Http\Requests;

final readonly class ServerRequest
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    private function __construct(
        private string $method,
        private string $path,
        private array $query,
        private array $body,
        private array $headers,
    ) {
    }

    public static function fromGlobals(): self
    {
        $uriValue = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = is_string($uriValue) ? $uriValue : '/';
        $parsedUri = parse_url($uri);
        $path = is_array($parsedUri) && is_string($parsedUri['path'] ?? null)
            ? $parsedUri['path']
            : '/';

        $path = self::removeApplicationPrefix($path);
        $normalizedPath = rtrim($path, '/');
        if ($normalizedPath === '') {
            $normalizedPath = '/';
        }

        $queryString = is_array($parsedUri) && is_string($parsedUri['query'] ?? null)
            ? $parsedUri['query']
            : '';
        $query = [];
        if ($queryString !== '') {
            parse_str($queryString, $query);
        }

        $methodValue = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $method = is_string($methodValue) ? strtoupper($methodValue) : 'GET';
        $postValue = $GLOBALS['_POST'] ?? [];
        $post = is_array($postValue) ? $postValue : [];

        return new self(
            $method,
            $normalizedPath,
            self::normalizeInput($query),
            self::normalizeInput($post),
            self::requestHeaders(),
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, mixed>
     */
    public function query(): array
    {
        return $this->query;
    }

    /**
     * @return array<string, mixed>
     */
    public function body(): array
    {
        return $this->body;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * @param array<array-key, mixed> $input
     * @return array<string, mixed>
     */
    private static function normalizeInput(array $input): array
    {
        $normalized = [];

        foreach ($input as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    private static function requestHeaders(): array
    {
        if (!function_exists('getallheaders')) {
            return [];
        }

        $headers = getallheaders();
        $normalized = [];

        foreach ($headers as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private static function removeApplicationPrefix(string $path): string
    {
        $scriptValue = $_SERVER['SCRIPT_NAME'] ?? '';
        $script = is_string($scriptValue) ? str_replace('\\', '/', $scriptValue) : '';
        $marker = '/backend/public/index.php';
        $markerPosition = strripos($script, $marker);
        if ($markerPosition === false) {
            return $path;
        }

        $prefix = rtrim(substr($script, 0, $markerPosition), '/');
        if ($prefix === '' || !str_starts_with($path, $prefix . '/')) {
            return $path;
        }

        $relative = substr($path, strlen($prefix));

        return $relative === '' ? '/' : $relative;
    }
}
