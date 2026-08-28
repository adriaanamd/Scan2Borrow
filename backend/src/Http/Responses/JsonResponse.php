<?php

declare(strict_types=1);

namespace App\Http\Responses;

use JsonException;

final readonly class JsonResponse implements ResponseInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private int $status,
        private array $payload,
    ) {
    }

    public function statusCode(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return ['Content-Type' => 'application/json; charset=utf-8'];
    }

    /**
     * @throws JsonException
     */
    public function toString(): string
    {
        return json_encode($this->payload, JSON_THROW_ON_ERROR);
    }
}
