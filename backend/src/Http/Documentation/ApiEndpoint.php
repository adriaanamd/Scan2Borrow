<?php

declare(strict_types=1);

namespace App\Http\Documentation;

final readonly class ApiEndpoint
{
    /**
     * @param list<string> $parameters
     */
    public function __construct(
        private string $method,
        private string $path,
        private string $tag,
        private string $summary,
        private string $description,
        private string $auth,
        private array $parameters,
        private string $response,
    ) {
    }

    /** @return array{method: string, path: string, tag: string, summary: string, description: string, auth: string, parameters: list<string>, response: string} */
    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'path' => $this->path,
            'tag' => $this->tag,
            'summary' => $this->summary,
            'description' => $this->description,
            'auth' => $this->auth,
            'parameters' => $this->parameters,
            'response' => $this->response,
        ];
    }
}
